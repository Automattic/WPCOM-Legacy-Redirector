<?php
namespace Automattic\LegacyRedirector\Tests\Unit;

use Automattic\LegacyRedirector\Utils;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;

/**
 * Capability Class Unit Test
 */
final class UtilsTest extends MonkeyStubs {

	/**
	 * Tests Utils::mb_parse_url().
	 *
	 * @dataProvider get_protected_redirect_data
	 * @covers \Automattic\LegacyRedirector\Utils::mb_parse_url
	 *
	 * @param string $url             Full URL to parse.
	 * @param string $expected_schema Expected return schema.
	 * @param string $expected_domain Expected return domain.
	 * @param string $expected_path   Expected return path.
	 * @param string $expected_query  Expected return query.
	 * @return void
	 */
	public function test_mb_parse_url( $url, $expected_schema, $expected_domain, $expected_path, $expected_query ) {

		$this->do_assertion_mb_parse_url( $url, $expected_schema, $expected_domain, $expected_path, $expected_query );

	}

	/**
	 * Data provider for tests methods
	 *
	 * @return array
	 */
	public function get_protected_redirect_data() {
		return array(
			'redirect_simple_url_no_end_slash'   => array(
				'https://www.example1.org',
				'https',
				'www.example1.org',
				'',
				'',
			),
			'redirect_simple_url_with_end_slash' => array(
				'http://www.example2.org/',
				'http',
				'www.example2.org',
				'/',
				'',
			),
			'redirect_url_with_path'             => array(
				'https://www.example3.com/test',
				'https',
				'www.example3.com',
				'/test',
				'',
			),
			'redirect_unicode_url_with_query'    => array(
				'http://www.example4.com//فوتوغرافيا/?test=فوتوغرافيا',
				'http',
				'www.example4.com',
				'//فوتوغرافيا/',
				'test=فوتوغرافيا',
			),
			'redirect_unicode_path_with_query'   => array(
				'/فوتوغرافيا/?test=فوتوغرافيا',
				'',
				'',
				'/فوتوغرافيا/',
				'test=فوتوغرافيا',
			),
			'redirect_unicode_path_with_multiple_parameters' => array(
				'/فوتوغرافيا/?test2=فوتوغرافيا&test=فوتوغرافيا',
				'',
				'',
				'/فوتوغرافيا/',
				'test2=فوتوغرافيا&test=فوتوغرافيا',
			),
			'redirect_malformed_url'             => array(
				'http://',
				'exception',
				'InvalidArgumentException',
				'',
				'',
			),

		);
	}

	/**
	 * Do assertion method for testing mb_parse_url().
	 *
	 * @param string $url             URL to test redirection against, can be a full blown URL with schema.
	 * @param string $expected_scheme Expected URL schema return. | `exception` string for Exceptions.
	 * @param string $expected_host   Expected URL hostname return. | Exception Type String, like `InvalidArgumentException`.
	 * @param string $expected_path   Expected URL path return.
	 * @param string $expected_query  Expected URL query return.
	 * @return void
	 */
	private function do_assertion_mb_parse_url( $url, $expected_scheme, $expected_host, $expected_path, $expected_query ) {

		if ( 'exception' === $expected_scheme ) {
			$this->expectException( $expected_host );
		}

		$path_info = Utils::mb_parse_url( $url );

		if ( ! isset( $path_info['scheme'] ) ) {
			$path_info['scheme'] = '';
		}
		if ( ! isset( $path_info['host'] ) ) {
			$path_info['host'] = '';
		}
		if ( ! isset( $path_info['path'] ) ) {
			$path_info['path'] = '';
		}
		if ( ! isset( $path_info['query'] ) ) {
			$path_info['query'] = '';
		}

		$this->assertIsArray( $path_info );
		$this->assertGreaterThan( 1, count( $path_info ) );
		$this->assertSame( $expected_host, $path_info['host'] );
		$this->assertSame( $expected_path, $path_info['path'] );
		$this->assertSame( $expected_query, $path_info['query'] );
	}
}
