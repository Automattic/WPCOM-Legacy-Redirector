<?php
$vendor_dir = dirname( dirname( __DIR__ ) ) . '/vendor';
\Polevaultweb\PHPUnit_WP_CLI_Runner\Runner::init( $vendor_dir );
require_once $vendor_dir . '/yoast/wp-test-utils/src/BrainMonkey/bootstrap.php';
require_once $vendor_dir . '/autoload.php';
require_once __DIR__ . '/MonkeyStubs.php';
