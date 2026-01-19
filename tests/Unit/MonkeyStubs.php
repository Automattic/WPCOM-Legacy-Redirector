<?php
/**
 * Brain Monkey stubs for unit tests.
 *
 * @package Automattic\LegacyRedirector
 */

namespace Automattic\LegacyRedirector\Tests\Unit;

use Brain\Monkey;
use Yoast\WPTestUtils\BrainMonkey\YoastTestCase;

/**
 * Base test case class with Brain Monkey stubs for WordPress functions.
 */
class MonkeyStubs extends YoastTestCase {

	/**
	 * Sets up test fixtures and additional function stubs.
	 *
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();

		Monkey\Functions\stubs(
			array(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Stubbing WP function with PHP native.
				'wp_parse_url' => static function ( $url, $component ) {
					return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				},
				'esc_url_raw', // Return 1st param unchanged.
			)
		);
	}
}
