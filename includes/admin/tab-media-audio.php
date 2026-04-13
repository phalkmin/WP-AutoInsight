<?php
/**
 * Media sub-tab: Audio
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$supported_formats = abcc_get_setting( 'abcc_supported_audio_formats', array( 'mp3', 'wav', 'm4a', 'webm' ) );
$available_formats = array(
	'mp3'  => 'MP3',
	'wav'  => 'WAV',
	'mp4'  => 'MP4',
	'm4a'  => 'M4A',
	'webm' => 'WebM',
	'flac' => 'FLAC',
);
$current_lang      = abcc_get_setting( 'abcc_transcription_language', 'en' );
$languages         = array(
	'en'   => 'English',
	'es'   => 'Spanish',
	'fr'   => 'French',
	'de'   => 'German',
	'it'   => 'Italian',
	'pt'   => 'Portuguese',
	'ru'   => 'Russian',
	'ja'   => 'Japanese',
	'ko'   => 'Korean',
	'zh'   => 'Chinese',
	'auto' => 'Auto-detect',
);
?>
<div class="tab-pane active">
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<input type="hidden" name="abcc_subtab" value="audio">

		<h2><?php esc_html_e( 'Audio Transcription', 'automated-blog-content-creator' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Enable Audio Transcription', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Allows converting audio files to blog posts using OpenAI Whisper. Files must be under 25MB.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" name="abcc_enable_audio_transcription"
							data-autosave-key="abcc_enable_audio_transcription"
							<?php checked( abcc_get_setting( 'abcc_enable_audio_transcription', true ) ); ?>>
						<?php esc_html_e( 'Enable audio transcription feature', 'automated-blog-content-creator' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Supported Formats', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'OpenAI Whisper supports most common audio formats. Restrict this list if you want to limit what users can upload.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<?php foreach ( $available_formats as $format => $label ) : ?>
						<label class="abcc-label-inline">
							<input type="checkbox" name="abcc_supported_audio_formats[]" value="<?php echo esc_attr( $format ); ?>"
								<?php checked( in_array( $format, $supported_formats, true ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="abcc_transcription_language">
						<?php esc_html_e( 'Transcription Language', 'automated-blog-content-creator' ); ?>
					</label>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Auto-detect lets OpenAI determine the language. Specifying a language improves accuracy.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<select id="abcc_transcription_language" name="abcc_transcription_language" data-autosave-key="abcc_transcription_language">
						<?php foreach ( $languages as $code => $name ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current_lang, $code ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<div class="abcc-how-it-works">
			<h3><?php esc_html_e( 'How it works', 'automated-blog-content-creator' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Upload an audio file from any post editor.', 'automated-blog-content-creator' ); ?></li>
				<li><?php esc_html_e( 'Choose "Transcribe only" or "Transcribe + Create Post".', 'automated-blog-content-creator' ); ?></li>
				<li><?php esc_html_e( 'The audio player is embedded in the generated post.', 'automated-blog-content-creator' ); ?></li>
			</ol>
			<p class="description"><?php esc_html_e( 'Audio files must be under 25MB (OpenAI Whisper limit). Longer files may take several minutes.', 'automated-blog-content-creator' ); ?></p>
		</div>

		<?php submit_button( __( 'Save Audio Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
