<?php
/**
 * Per-call usage records (`_abcc_job_usage`) and cost estimates for the job log.
 *
 * @package WP-AutoInsight
 * @since 4.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estimated USD cost of one call from the registry's per-1M-token prices.
 *
 * Gemini reports thought tokens apart from output; the other transports count
 * them inside output. Unknown pricing yields null, never 0.
 *
 * @since 4.6.0
 * @param string $provider Provider ID.
 * @param string $model    Bare model ID or selection.
 * @param array  $tokens   { input, output, reasoning } (ints or null).
 * @return float|null
 */
function abcc_estimate_call_cost( $provider, $model, $tokens ) {
	$definition = abcc_get_model_definition( $model, $provider );

	if ( ! isset( $definition['price_input'], $definition['price_output'] ) ) {
		return null;
	}

	$input      = isset( $tokens['input'] ) ? $tokens['input'] : null;
	$output     = isset( $tokens['output'] ) ? $tokens['output'] : null;
	$reasoning  = isset( $tokens['reasoning'] ) ? (int) $tokens['reasoning'] : null;
	$is_gemini  = 'gemini' === abcc_get_provider_transport( $provider );
	$has_tokens = null !== $input || null !== $output || ( $is_gemini && null !== $reasoning );

	if ( ! $has_tokens ) {
		return null;
	}

	if ( $is_gemini ) {
		$output = (int) $output + (int) $reasoning;
	}

	$cost = ( (int) $input * (float) $definition['price_input'] + (int) $output * (float) $definition['price_output'] ) / 1000000;

	return round( $cost, 8 );
}

/**
 * Append one usage record to a job after a provider attempt.
 *
 * @since 4.6.0
 * @param array  $context  Hook context; only job_id is read.
 * @param string $provider Provider that served (or refused) the call.
 * @param string $model    Model that was sent.
 * @param array  $result   Wrapper tuple (tokens, error).
 * @param string $operation Operation slug.
 * @return array|null The record, or null when there is no job.
 */
function abcc_record_generation_usage( $context, $provider, $model, $result, $operation = 'article' ) {
	$job_id = isset( $context['job_id'] ) ? (int) $context['job_id'] : 0;
	if ( $job_id <= 0 ) {
		return null;
	}

	$provider = sanitize_key( (string) $provider );
	$model    = abcc_get_model_id_from_selection( $model );
	$tokens   = isset( $result['tokens'] ) && is_array( $result['tokens'] ) ? $result['tokens'] : array();
	$tokens   = array(
		'input'     => isset( $tokens['input'] ) ? (int) $tokens['input'] : null,
		'output'    => isset( $tokens['output'] ) ? (int) $tokens['output'] : null,
		'reasoning' => isset( $tokens['reasoning'] ) ? (int) $tokens['reasoning'] : null,
	);
	$config   = abcc_get_provider( $provider );

	$definition     = abcc_get_model_definition( $model, $provider );
	$pricing_source = ! empty( $definition['pricing_source'] ) ? (string) $definition['pricing_source'] : 'registry';
	$pricing_date   = ! empty( $definition['pricing_date'] ) ? (string) $definition['pricing_date'] : ( ! empty( $config['pricing_date'] ) ? (string) $config['pricing_date'] : '' );
	$cost           = abcc_estimate_call_cost( $provider, $model, $tokens );

	// A gateway that reports what it charged beats any price list.
	if ( ! empty( $config['gateway'] ) && isset( $result['usage']['cost'] ) && is_numeric( $result['usage']['cost'] ) && (float) $result['usage']['cost'] >= 0 ) {
		$cost           = round( (float) $result['usage']['cost'], 8 );
		$pricing_source = 'provider';
		$pricing_date   = gmdate( 'Y-m-d' );
	}

	$record = array(
		'operation'        => sanitize_key( (string) $operation ),
		'provider'         => $provider,
		'model'            => $model,
		'input_tokens'     => $tokens['input'],
		'output_tokens'    => $tokens['output'],
		'reasoning_tokens' => $tokens['reasoning'],
		'pricing_source'   => $pricing_source,
		'pricing_date'     => $pricing_date,
		'estimated_cost'   => $cost,
		'error'            => isset( $result['error'] ) && is_wp_error( $result['error'] ) ? (string) $result['error']->get_error_code() : '',
		'recorded_at'      => time(),
	);

	$usage = get_post_meta( $job_id, '_abcc_job_usage', true );
	$usage = is_array( $usage ) ? $usage : array();

	$usage[] = $record;
	update_post_meta( $job_id, '_abcc_job_usage', $usage );

	return $record;
}

/**
 * Usage records stored on a job.
 *
 * @since 4.6.0
 * @param int $job_id Job ID.
 * @return array
 */
function abcc_get_job_usage( $job_id ) {
	$usage = get_post_meta( (int) $job_id, '_abcc_job_usage', true );

	return is_array( $usage ) ? array_values( $usage ) : array();
}

/**
 * Totals across a job's usage records.
 *
 * @since 4.6.0
 * @param int $job_id Job ID.
 * @return array{calls: int, errors: int, input_tokens: int, output_tokens: int, cost: float|null, unpriced: int, pricing_date: string}
 */
function abcc_summarize_job_usage( $job_id ) {
	$summary = array(
		'calls'         => 0,
		'errors'        => 0,
		'input_tokens'  => 0,
		'output_tokens' => 0,
		'cost'          => null,
		'unpriced'      => 0,
		'pricing_date'  => '',
	);

	foreach ( abcc_get_job_usage( $job_id ) as $record ) {
		++$summary['calls'];
		if ( ! empty( $record['error'] ) ) {
			++$summary['errors'];
		}
		$summary['input_tokens']  += (int) $record['input_tokens'];
		$summary['output_tokens'] += (int) $record['output_tokens'];
		// Only Gemini reports thought tokens outside output; the others already include them.
		if ( isset( $record['provider'] ) && 'gemini' === abcc_get_provider_transport( (string) $record['provider'] ) ) {
			$summary['output_tokens'] += (int) $record['reasoning_tokens'];
		}

		if ( null === $record['estimated_cost'] ) {
			++$summary['unpriced'];
		} else {
			$summary['cost'] = (float) $summary['cost'] + (float) $record['estimated_cost'];
		}

		if ( '' === $summary['pricing_date'] && ! empty( $record['pricing_date'] ) ) {
			$summary['pricing_date'] = (string) $record['pricing_date'];
		}
	}

	return $summary;
}

/**
 * One-line, translatable usage note for the job log.
 *
 * @since 4.6.0
 * @param int $job_id Job ID.
 * @return string '' when the job made no calls, or every call was refused without using tokens.
 */
function abcc_format_job_usage_note( $job_id ) {
	$summary = abcc_summarize_job_usage( $job_id );

	if ( 0 === $summary['calls'] ) {
		return '';
	}

	// Calls refused before the model ran (bad key, outage) used nothing. A call that
	// succeeded without reporting usage still gets its "cost unknown" line.
	if ( $summary['errors'] === $summary['calls'] && 0 === $summary['input_tokens'] + $summary['output_tokens'] && empty( $summary['cost'] ) ) {
		return '';
	}

	if ( null === $summary['cost'] ) {
		$cost = __( 'cost unknown', 'automated-blog-content-creator' );
	} else {
		/* translators: %s: estimated cost in US dollars */
		$cost = sprintf( __( 'about $%s', 'automated-blog-content-creator' ), number_format_i18n( $summary['cost'], $summary['cost'] < 0.01 ? 4 : 2 ) );
		if ( $summary['unpriced'] > 0 ) {
			$cost .= ' ' . __( '(some calls unpriced)', 'automated-blog-content-creator' );
		}
	}

	$note = sprintf(
		/* translators: 1: "N calls" phrase, 2: input tokens, 3: output tokens, 4: cost phrase */
		__( 'Usage: %1$s, %2$s tokens in, %3$s out, %4$s', 'automated-blog-content-creator' ),
		sprintf(
			/* translators: %d: number of provider calls */
			_n( '%d call', '%d calls', $summary['calls'], 'automated-blog-content-creator' ),
			$summary['calls']
		),
		number_format_i18n( $summary['input_tokens'] ),
		number_format_i18n( $summary['output_tokens'] ),
		$cost
	);

	if ( '' !== $summary['pricing_date'] ) {
		/* translators: %s: date the token prices were last checked */
		$note .= ' ' . sprintf( __( '(prices checked %s).', 'automated-blog-content-creator' ), $summary['pricing_date'] );
	}

	if ( '' === $summary['pricing_date'] ) {
		$note .= '.';
	}

	$note .= ' ' . __( 'Text calls only; images and provider search fees are not included.', 'automated-blog-content-creator' );

	return $note;
}

/**
 * Approximate cost of one generated post, before it runs.
 *
 * "Per post" is the title (or title + SEO) call plus the article body. A
 * featured image, a truncation repair and a fallback retry are not included.
 * Unknown pricing yields a null cost, never 0.
 *
 * @since 4.6.0
 * @param string $selection Model selection ('provider::model' or a bare ID).
 * @param int    $words     Visible word target.
 * @param array  $flags     Optional: 'seo' (bool), 'locale' (string), 'prompt_tokens' (int, body input).
 * @return array{words: int, cost: float|null, currency: string, includes: string[], excludes: string[],
 *               pricing_date: string, pricing_source: string, approximate: bool, provider: string,
 *               model: string, model_name: string, output_tokens: int, notes: string[]}
 */
function abcc_estimate_post_cost( $selection, $words, $flags = array() ) {
	$words    = max( 1, (int) $words );
	$parsed   = abcc_parse_model_selection( $selection );
	$provider = $parsed['provider'];
	$model    = '' !== $provider ? abcc_resolve_retired_model( $provider, $parsed['model'] ) : $parsed['model'];
	$config   = abcc_get_provider( $provider );
	$locale   = isset( $flags['locale'] ) ? (string) $flags['locale'] : '';

	$with_seo = isset( $flags['seo'] )
		? (bool) $flags['seo']
		: ( abcc_get_setting( 'openai_generate_seo', true ) && 'none' !== abcc_get_active_seo_plugin() );

	if ( isset( $flags['prompt_tokens'] ) ) {
		$body_input = max( 0, (int) $flags['prompt_tokens'] );
	} else {
		$default    = abcc_get_default_content_template();
		$parts      = abcc_build_article_prompt(
			(string) $default['prompt'],
			'A representative post title',
			array( 'keyword' ),
			array( 'char_limit' => (int) round( $words / 0.75 ) )
		);
		$body_input = abcc_estimate_tokens( $parts['system'] . "\n" . $parts['user'] );
	}

	// Hidden reasoning is billed as output. One reserve per post is an
	// assumption, not a measurement; _abcc_job_usage holds the real numbers.
	$model_flags = abcc_get_model_flags( $provider, $model );
	$reasoning   = 0;
	if ( null !== $model_flags['thinking_budget'] && (int) $model_flags['thinking_budget'] > 0 ) {
		$reasoning = (int) $model_flags['thinking_budget'];
	} elseif ( ! empty( $model_flags['reasoning'] ) ) {
		$reasoning = (int) floor( (int) $model_flags['min_output_tokens'] / 2 );
	}

	$body_output  = (int) ceil( $words * abcc_get_tokens_per_word( $locale ) ) + $reasoning;
	$title_input  = $with_seo ? 220 : 130;
	$title_output = $with_seo ? 150 : 25;

	$definition = abcc_get_model_definition( $model, $provider );
	$cost       = null;
	if ( isset( $definition['price_input'], $definition['price_output'] ) ) {
		$cost = ( ( $body_input + $title_input ) * (float) $definition['price_input'] + ( $body_output + $title_output ) * (float) $definition['price_output'] ) / 1000000;
		$cost = round( $cost, 6 );
	}

	$notes = array();
	if ( ! empty( $config['cost_note_callback'] ) && is_callable( $config['cost_note_callback'] ) ) {
		$notes[] = (string) call_user_func( $config['cost_note_callback'] );
	}

	$estimate = array(
		'words'          => $words,
		'cost'           => $cost,
		'currency'       => 'USD',
		'includes'       => array( $with_seo ? 'title_seo' : 'title', 'body' ),
		'excludes'       => array( 'image', 'repair', 'fallback' ),
		'pricing_date'   => ! empty( $definition['pricing_date'] ) ? (string) $definition['pricing_date'] : ( ! empty( $config['pricing_date'] ) ? (string) $config['pricing_date'] : '' ),
		'pricing_source' => ! empty( $definition['pricing_source'] ) ? (string) $definition['pricing_source'] : 'registry',
		'approximate'    => true,
		'provider'       => $provider,
		'model'          => $model,
		'model_name'     => abcc_get_model_display_name( abcc_build_model_selection( $provider, $model ) ),
		'output_tokens'  => abcc_estimate_output_tokens(
			$words,
			$selection,
			array(
				'locale'        => $locale,
				'prompt_tokens' => $body_input,
			)
		),
		'notes'          => $notes,
	);

	/**
	 * Filter the pre-run cost estimate for one post.
	 *
	 * @since 4.6.0
	 * @param array  $estimate  Estimate (see abcc_estimate_post_cost()).
	 * @param string $selection Model selection.
	 * @param int    $words     Word target.
	 */
	return apply_filters( 'abcc_post_cost_estimate', $estimate, $selection, $words );
}

/**
 * Format a small dollar amount: four decimals under a cent, two otherwise.
 *
 * @since 4.6.0
 * @param float $amount Amount in USD.
 * @return string Without the currency sign.
 */
function abcc_format_cost_amount( $amount ) {
	$amount = (float) $amount;

	return number_format_i18n( $amount, $amount < 0.01 ? 4 : 2 );
}

/**
 * Translatable text for a cost estimate: a one-line summary and what it covers.
 *
 * @since 4.6.0
 * @param array $estimate From abcc_estimate_post_cost().
 * @return array{summary: string, cost: string, detail: string} 'cost' is the summary without the word count.
 */
function abcc_format_post_cost_estimate( $estimate ) {
	$words = number_format_i18n( (int) $estimate['words'] );
	$model = '' !== (string) $estimate['model_name'] ? (string) $estimate['model_name'] : (string) $estimate['model'];

	if ( null === $estimate['cost'] ) {
		/* translators: %s: model name */
		$cost = sprintf( __( 'cost unknown with %s', 'automated-blog-content-creator' ), $model );
	} elseif ( 0.0 === (float) $estimate['cost'] ) {
		// Only a free model prices at zero; its catalog name already ends in "(free)".
		/* translators: %s: model name */
		$cost = sprintf( __( 'free with %s', 'automated-blog-content-creator' ), (string) preg_replace( '/\s*\(free\)\s*$/i', '', $model ) );
	} else {
		$cost = sprintf(
			/* translators: 1: cost in US dollars, 2: model name */
			__( '≈$%1$s per post with %2$s', 'automated-blog-content-creator' ),
			abcc_format_cost_amount( $estimate['cost'] ),
			$model
		);
	}

	$summary = sprintf(
		/* translators: 1: word count, 2: cost phrase, e.g. "≈$0.004 per post with GPT-5.4 mini" */
		__( 'About %1$s words · %2$s', 'automated-blog-content-creator' ),
		$words,
		$cost
	);

	$detail = in_array( 'title_seo', (array) $estimate['includes'], true )
		? __( 'Estimate for the title, SEO fields and article text.', 'automated-blog-content-creator' )
		: __( 'Estimate for the title and article text.', 'automated-blog-content-creator' );

	$detail .= ' ' . __( 'Images and retries cost extra.', 'automated-blog-content-creator' );

	if ( null === $estimate['cost'] ) {
		$detail .= ' ' . __( 'No price is known for this model.', 'automated-blog-content-creator' );
	} elseif ( '' !== (string) $estimate['pricing_date'] ) {
		/* translators: %s: date the token prices were last checked */
		$detail .= ' ' . sprintf( __( 'Prices checked %s.', 'automated-blog-content-creator' ), $estimate['pricing_date'] );
	}

	foreach ( (array) $estimate['notes'] as $note ) {
		$detail .= ' ' . $note;
	}

	return array(
		'summary' => $summary,
		'cost'    => $cost,
		'detail'  => $detail,
	);
}

/**
 * Cost estimate text for a content-length setting on a model.
 *
 * @since 4.6.0
 * @param int    $char_limit Content length setting.
 * @param string $selection  Model selection; '' means the global model.
 * @return array{summary: string, cost_text: string, detail: string, cost: float|null, words: int}
 */
function abcc_get_length_cost_text( $char_limit, $selection = '' ) {
	$selection = '' !== (string) $selection ? (string) $selection : (string) abcc_get_setting( 'prompt_select', abcc_get_default_text_model() );
	$estimate  = abcc_estimate_post_cost( $selection, abcc_get_word_target( (int) $char_limit ) );
	$text      = abcc_format_post_cost_estimate( $estimate );

	return array(
		'summary'   => $text['summary'],
		'cost_text' => $text['cost'],
		'detail'    => $text['detail'],
		'cost'      => $estimate['cost'],
		'words'     => (int) $estimate['words'],
	);
}
