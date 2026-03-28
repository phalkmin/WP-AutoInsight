<?php
/**
 * Tab: Audio Transcription
 *
 * @package WP-AutoInsight
 * @since 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="tab-pane active">
	<h2><?php esc_html_e( 'Audio Transcription Settings', 'automated-blog-content-creator' ); ?></h2>
	<p><?php esc_html_e( 'Configure audio transcription and automatic post creation from audio files.', 'automated-blog-content-creator' ); ?></p>
	
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="abcc_enable_audio_transcription">
						<?php esc_html_e( 'Enable Audio Transcription', 'automated-blog-content-creator' ); ?>
					</label>
				</th>
				<td>
					<input type="checkbox" id="abcc_enable_audio_transcription" 
						name="abcc_enable_audio_transcription" 
						<?php checked( get_option( 'abcc_enable_audio_transcription', true ) ); ?>>
					<p class="description">
						<?php esc_html_e( 'Allow transcribing audio files and converting them to blog posts using OpenAI Whisper', 'automated-blog-content-creator' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="abcc_supported_audio_formats">
						<?php esc_html_e( 'Supported Audio Formats', 'automated-blog-content-creator' ); ?>
					</label>
				</th>
				<td>
					<?php
					$supported_formats = get_option( 'abcc_supported_audio_formats', array( 'mp3', 'wav', 'm4a', 'webm' ) );
					$available_formats = array(
						'mp3'  => 'MP3',
						'wav'  => 'WAV',
						'mp4'  => 'MP4',
						'm4a'  => 'M4A',
						'webm' => 'WebM',
						'flac' => 'FLAC',
					);

					foreach ( $available_formats as $format => $label ) {
						$checked = in_array( $format, $supported_formats, true ) ? 'checked' : '';
						printf(
							'<label style="display: block; margin-bottom: 5px;"><input type="checkbox" name="abcc_supported_audio_formats[]" value="%s" %s> %s</label>',
							esc_attr( $format ),
							esc_attr( $checked ),
							esc_html( $label )
						);
					}
					?>
					<p class="description">
						<?php esc_html_e( 'Select which audio formats to enable for transcription. OpenAI Whisper supports most common audio formats.', 'automated-blog-content-creator' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="abcc_transcription_language">
						<?php esc_html_e( 'Transcription Language', 'automated-blog-content-creator' ); ?>
					</label>
				</th>
				<td>
					<select id="abcc_transcription_language" name="abcc_transcription_language">
						<?php
						$current_lang = get_option( 'abcc_transcription_language', 'en' );
						$languages    = array(
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

						foreach ( $languages as $code => $name ) {
							printf(
								'<option value="%s"%s>%s</option>',
								esc_attr( $code ),
								selected( $current_lang, $code, false ),
								esc_html( $name )
							);
						}
						?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select the primary language for transcription. Auto-detect will let OpenAI determine the language.', 'automated-blog-content-creator' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Usage Information', 'automated-blog-content-creator' ); ?>
				</th>
				<td>
					<div style="background: #f0f0f1; padding: 15px; border-radius: 5px;">
						<h4 style="margin-top: 0;"><?php esc_html_e( 'How to use Audio Transcription:', 'automated-blog-content-creator' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'Upload an audio file to your Media Library', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Go to the audio file\'s edit page', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Click "Transcribe & Create Post" in the publish box', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'The AI will transcribe the audio and create a formatted blog post', 'automated-blog-content-creator' ); ?></li>
						</ol>
						<p><strong><?php esc_html_e( 'Note:', 'automated-blog-content-creator' ); ?></strong> <?php esc_html_e( 'Audio files must be under 25MB (OpenAI Whisper limit). Longer files may take several minutes to process.', 'automated-blog-content-creator' ); ?></p>
					</div>
				</td>
			</tr>
		</table>
		<?php submit_button( esc_html__( 'Save Audio Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
