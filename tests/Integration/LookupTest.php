<?php

namespace Automattic\LegacyRedirector\Tests\Integration;

use Automattic\LegacyRedirector\Lookup;
use WPCOM_Legacy_Redirector;

/**
 * CapabilityTest class.
 */
final class LookupTest extends TestCase {

	/**
	 * Test Lookup::get_redirect_uri.
	 *
	 * @covers Lookup::get_redirect_uri
	 * @dataProvider get_protected_redirect_data
	 *
	 * @param string $from_url        Redirect From URL.
	 * @param string $to_url          Redirect To URL.
	 * @param int    $redirect_status Redirect Status Code.
	 * @return void
	 */
	public function test_get_redirect_uri( $from_url, $to_url, $redirect_status ) {

		WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false );

		$redirect_data = Lookup::get_redirect_data( $from_url );

		$this->assertEquals( $to_url, $redirect_data['redirect_uri'] );
		$this->assertEquals( $redirect_status, $redirect_data['redirect_status'] );

	}

	/**
	 * Data provider for tests methods
	 *
	 * @return array
	 */
	public function get_protected_redirect_data() {
		return array(
			'redirect unicode characters with querystring' => array(
				'/فوتوغرافيا/?test=فوتوغرافيا',
				'/some_other_page',
				'301',
			),
			'redirect_simple'                              => array(
				'/test',
				'/',
				'301',
			),
			'redirect_unicode_no_query'                    => array(
				'/فوتوغرافيا/',
				'/',
				'301',
			),
		);
	}

}
