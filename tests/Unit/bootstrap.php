<?php
/**
 * Unit tests bootstrap
 *
 * @package Automattic\LegacyRedirector
 */

$vendor_dir = dirname( dirname( __DIR__ ) ) . '/vendor';
require_once $vendor_dir . '/yoast/wp-test-utils/src/BrainMonkey/bootstrap.php';
require_once $vendor_dir . '/autoload.php';
require_once __DIR__ . '/MonkeyStubs.php';
require_once __DIR__ . '/WordPressStubs.php';
require_once __DIR__ . '/WpCliUtilsStubs.php';
