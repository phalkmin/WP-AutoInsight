<?php
/**
 * Connectors links resolve through abcc_get_connectors_url().
 */

abcc_test(
	'connectors URL falls back to the Settings submenu when core has no options-connectors.php',
	function () {
		$expected = file_exists( ABSPATH . 'wp-admin/options-connectors.php' )
			? admin_url( 'options-connectors.php' )
			: admin_url( 'options-general.php?page=ai-connectors' );

		abcc_assert_same( $expected, abcc_get_connectors_url() );
	}
);

abcc_test(
	'no Connectors link is hardcoded outside the helper',
	function () {
		$root  = dirname( __DIR__ );
		$files = array_merge(
			glob( $root . '/*.php' ),
			glob( $root . '/includes/*.php' ),
			glob( $root . '/includes/admin/*.php' ),
			glob( $root . '/includes/admin/onboarding/*.php' ),
			glob( $root . '/js/*.js' )
		);

		foreach ( $files as $file ) {
			if ( $root . '/includes/api-keys.php' === $file ) {
				continue;
			}
			$source = file_get_contents( $file );
			abcc_assert_false(
				false !== strpos( $source, 'page=ai-connectors' ) || false !== strpos( $source, 'options-connectors.php' ),
				basename( $file ) . ' must link to Connectors via abcc_get_connectors_url().'
			);
		}
	}
);
