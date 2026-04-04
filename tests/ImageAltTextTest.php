<?php

abcc_test(
	'image alt text helper builds the expected format',
	function () {
		abcc_assert_same( 'My Title - keyword', abcc_build_featured_image_alt_text( 'My Title', 'keyword' ) );
		abcc_assert_same( '', abcc_build_featured_image_alt_text( 'My Title', '' ) );
	}
);
