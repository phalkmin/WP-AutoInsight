<?php
/**
 * Settings sub-tab: Permissions
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$allowed_roles = abcc_get_setting( 'abcc_allowed_roles', array( 'administrator', 'editor' ) );
$wp_connectors = abcc_wp_ai_client_available();
?>
<div class="tab-pane active">
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<input type="hidden" name="abcc_subtab" value="permissions">

		<h2><?php esc_html_e( 'Who can use AI generation tools?', 'automated-blog-content-creator' ); ?>
			<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Controls which user roles see the AI generation buttons and meta boxes in the post editor.', 'automated-blog-content-creator' ) ) ); ?>
		</h2>

		<fieldset>
			<?php
			$roles = array(
				'administrator' => __( 'Administrators', 'automated-blog-content-creator' ),
				'editor'        => __( 'Editors', 'automated-blog-content-creator' ),
				'author'        => __( 'Authors', 'automated-blog-content-creator' ),
				'contributor'   => __( 'Contributors', 'automated-blog-content-creator' ),
			);
			foreach ( $roles as $role_key => $label ) :
				$is_admin = 'administrator' === $role_key;
				?>
				<label class="abcc-label-block--lg">
					<input type="checkbox" name="abcc_allowed_roles[]" value="<?php echo esc_attr( $role_key ); ?>"
						<?php checked( in_array( $role_key, $allowed_roles, true ) || $is_admin ); ?>
						<?php disabled( $is_admin ); ?>>
					<?php echo esc_html( $label ); ?>
					<?php if ( $is_admin ) : ?>
						<span class="description"><?php esc_html_e( '(always enabled)', 'automated-blog-content-creator' ); ?></span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<?php if ( $wp_connectors ) : ?>
			<p class="description abcc-description-below">
				<?php esc_html_e( 'On WordPress 7.0+, this also controls access via the WP AI Connectors permission system.', 'automated-blog-content-creator' ); ?>
			</p>
		<?php endif; ?>

		<?php submit_button( __( 'Save Permission Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
