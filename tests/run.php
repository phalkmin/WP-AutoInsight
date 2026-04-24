<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
require __DIR__ . '/bootstrap.php';

foreach ( glob( __DIR__ . '/*Test.php' ) as $test_file ) {
	require $test_file;
}

$failures = 0;

foreach ( $GLOBALS['abcc_tests'] as $test ) {
	try {
		$test['callback']();
		echo "[PASS] {$test['name']}\n";
	} catch ( Throwable $e ) {
		++$failures;
		echo "[FAIL] {$test['name']}: {$e->getMessage()}\n";
	}
}

if ( $failures > 0 ) {
	exit( 1 );
}

echo "All regression tests passed.\n";
