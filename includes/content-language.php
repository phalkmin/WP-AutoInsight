<?php
/**
 * Content language resolution.
 *
 * Every generation prompt carries a {language} instruction so a non-English
 * install gets content in its own language instead of English.
 *
 * @package WP-AutoInsight
 * @since 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Locale to human-readable language name.
 *
 * Regional forms are used where the distinction changes the writing
 * ("Brazilian Portuguese" vs "European Portuguese"); otherwise the plain
 * language name. The model reads these names directly, so they must be the
 * names a fluent speaker would use, not ISO codes.
 *
 * @since 4.4.0
 * @return array Locale code => language name.
 */
function abcc_get_content_language_map() {
	return array(
		'pt_BR' => 'Brazilian Portuguese',
		'pt_PT' => 'European Portuguese',
		'pt'    => 'Portuguese',
		'es_MX' => 'Mexican Spanish',
		'es_AR' => 'Argentine Spanish',
		'es_ES' => 'European Spanish',
		'es'    => 'Spanish',
		'en_US' => 'American English',
		'en_GB' => 'British English',
		'en_AU' => 'Australian English',
		'en_CA' => 'Canadian English',
		'en'    => 'English',
		'fr_FR' => 'French',
		'fr_CA' => 'Canadian French',
		'fr'    => 'French',
		'de_DE' => 'German',
		'de_AT' => 'Austrian German',
		'de_CH' => 'Swiss German',
		'de'    => 'German',
		'it_IT' => 'Italian',
		'it'    => 'Italian',
		'nl_NL' => 'Dutch',
		'nl'    => 'Dutch',
		'pl_PL' => 'Polish',
		'pl'    => 'Polish',
		'ru_RU' => 'Russian',
		'ru'    => 'Russian',
		'tr_TR' => 'Turkish',
		'tr'    => 'Turkish',
		'sv_SE' => 'Swedish',
		'da_DK' => 'Danish',
		'nb_NO' => 'Norwegian',
		'fi'    => 'Finnish',
		'cs_CZ' => 'Czech',
		'el'    => 'Greek',
		'he_IL' => 'Hebrew',
		'ar'    => 'Arabic',
		'hi_IN' => 'Hindi',
		'id_ID' => 'Indonesian',
		'th'    => 'Thai',
		'vi'    => 'Vietnamese',
		'ja'    => 'Japanese',
		'ko_KR' => 'Korean',
		'zh_CN' => 'Simplified Chinese',
		'zh_TW' => 'Traditional Chinese',
		'zh_HK' => 'Traditional Chinese',
		'uk'    => 'Ukrainian',
		'ro_RO' => 'Romanian',
		'hu_HU' => 'Hungarian',
		'bg_BG' => 'Bulgarian',
		'ca'    => 'Catalan',
		'hr'    => 'Croatian',
		'sk_SK' => 'Slovak',
		'sl_SI' => 'Slovenian',
		'sr_RS' => 'Serbian',
		'lt_LT' => 'Lithuanian',
		'lv'    => 'Latvian',
		'et'    => 'Estonian',
		'fa_IR' => 'Persian',
		'ms_MY' => 'Malay',
		'bn_BD' => 'Bengali',
		'tl'    => 'Tagalog',
		'ur'    => 'Urdu',
	);
}

/**
 * Resolve a locale code into a language name for use in a prompt.
 *
 * Tries the full locale, then the language part alone, then the site locale,
 * then English. Never returns an empty string: an empty {language} substitution
 * would silently produce a prompt with a dangling instruction.
 *
 * @since 4.4.0
 * @param string $locale Locale code. Defaults to the site locale.
 * @return string Language name.
 */
function abcc_get_content_language_label( $locale = '' ) {
	$locale = '' !== (string) $locale ? (string) $locale : get_locale();

	$map = abcc_get_content_language_map();

	if ( isset( $map[ $locale ] ) ) {
		return $map[ $locale ];
	}

	// pt_AO -> pt.
	$language = strtok( $locale, '_' );
	if ( $language && isset( $map[ $language ] ) ) {
		return $map[ $language ];
	}

	// Unknown locale: fall back to the site locale, then English.
	$site = get_locale();
	if ( $site !== $locale && isset( $map[ $site ] ) ) {
		return $map[ $site ];
	}

	return 'English';
}

/**
 * Resolve the configured content language into a prompt-ready language name.
 *
 * @since 4.4.0
 * @return string Language name, never empty.
 */
function abcc_resolve_content_language() {
	$setting = abcc_sanitize_content_language( abcc_get_setting( 'abcc_content_language', 'site' ) );

	if ( 'site' === $setting ) {
		return abcc_get_content_language_label( get_locale() );
	}

	return abcc_get_content_language_label( $setting );
}

/**
 * Sanitize the content language setting.
 *
 * Accepts 'site' or a locale present in the language map; anything else
 * (including the removed 'auto' mode) falls back to 'site'.
 *
 * @since 4.4.0
 * @param mixed $value Raw value.
 * @return string
 */
function abcc_sanitize_content_language( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';

	if ( 'site' === $value ) {
		return 'site';
	}

	return array_key_exists( $value, abcc_get_content_language_map() ) ? $value : 'site';
}

/**
 * Choices for the content language dropdown.
 *
 * @since 4.4.0
 * @return array Value => label, 'site' first.
 */
function abcc_get_content_language_choices() {
	$choices = array(
		'site' => sprintf(
			/* translators: %s: language name resolved from the site locale */
			__( 'Follow site language (%s)', 'automated-blog-content-creator' ),
			abcc_get_content_language_label( get_locale() )
		),
	);

	// Bare language codes (pt, fr, de…) exist for the resolver's regional
	// fallback, but in the picker they duplicate the regional entries'
	// labels ("French" twice). Show each label once — prefer the entry the
	// map lists first, which is the regional form where one exists.
	$map  = abcc_get_content_language_map();
	$seen = array();
	foreach ( $map as $locale => $label ) {
		if ( isset( $seen[ $label ] ) ) {
			unset( $map[ $locale ] );
			continue;
		}
		$seen[ $label ] = true;
	}
	asort( $map );

	return $choices + $map;
}
