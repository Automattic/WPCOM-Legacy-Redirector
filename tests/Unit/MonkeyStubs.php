<?php

namespace Automattic\LegacyRedirector\Tests\Unit;

use Brain\Monkey;
use Yoast\WPTestUtils\BrainMonkey\YoastTestCase;

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
				'wp_parse_url' => static function ( $url, $component ) {
					return parse_url( $url, $component );
				},
				'esc_url_raw', // Return 1st param unchanged.
			)
		);
	}
}
