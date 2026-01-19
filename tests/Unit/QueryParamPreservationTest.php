<?php
/**
 * Query Parameter Preservation Unit Tests
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit;

use Automattic\LegacyRedirector\Lookup;

/**
 * QueryParamPreservationTest class.
 *
 * Tests the query parameter preservation logic from Lookup class.
 *
 * @covers \Automattic\LegacyRedirector\Lookup
 */
final class QueryParamPreservationTest extends MonkeyStubs {

	/**
	 * Test get_preservable_querystring_params_from_url returns empty array by default.
	 *
	 * @covers \Automattic\LegacyRedirector\Lookup::get_preservable_querystring_params_from_url
	 */
	public function test_returns_empty_array_when_no_filter(): void {
		// When no filter is applied, should return empty array.
		$result = Lookup::get_preservable_querystring_params_from_url( '/test?foo=bar' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_preservable_querystring_params_from_url extracts specified params.
	 *
	 * @covers \Automattic\LegacyRedirector\Lookup::get_preservable_querystring_params_from_url
	 */
	public function test_extracts_specified_params(): void {
		// Mock the filter to preserve specific params.
		\Brain\Monkey\Filters\expectApplied( 'wpcom_legacy_redirector_preserve_query_params' )
			->once()
			->andReturn( array( 'utm_source', 'utm_medium' ) );

		$result = Lookup::get_preservable_querystring_params_from_url( '/test?utm_source=google&utm_medium=cpc&other=ignored' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'utm_source', $result );
		$this->assertArrayHasKey( 'utm_medium', $result );
		$this->assertArrayNotHasKey( 'other', $result );
		$this->assertSame( 'google', $result['utm_source'] );
		$this->assertSame( 'cpc', $result['utm_medium'] );
	}

	/**
	 * Test get_preservable_querystring_params_from_url throws on non-array filter return.
	 *
	 * @covers \Automattic\LegacyRedirector\Lookup::get_preservable_querystring_params_from_url
	 */
	public function test_throws_on_non_array_filter_return(): void {
		// Mock the filter to return a non-array.
		\Brain\Monkey\Filters\expectApplied( 'wpcom_legacy_redirector_preserve_query_params' )
			->once()
			->andReturn( 'not-an-array' );

		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( 'must return an array' );

		Lookup::get_preservable_querystring_params_from_url( '/test?foo=bar' );
	}

	/**
	 * Test get_preservable_querystring_params_from_url throws on associative array filter return.
	 *
	 * @covers \Automattic\LegacyRedirector\Lookup::get_preservable_querystring_params_from_url
	 */
	public function test_throws_on_associative_array_filter_return(): void {
		// Mock the filter to return an associative array.
		\Brain\Monkey\Filters\expectApplied( 'wpcom_legacy_redirector_preserve_query_params' )
			->once()
			->andReturn( array( 'key' => 'value' ) );

		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( 'must return an indexed array' );

		Lookup::get_preservable_querystring_params_from_url( '/test?foo=bar' );
	}

	/**
	 * Test get_preservable_querystring_params_from_url returns empty for URL without query.
	 *
	 * @covers \Automattic\LegacyRedirector\Lookup::get_preservable_querystring_params_from_url
	 */
	public function test_returns_empty_for_url_without_query(): void {
		// Mock the filter to preserve specific params.
		\Brain\Monkey\Filters\expectApplied( 'wpcom_legacy_redirector_preserve_query_params' )
			->once()
			->andReturn( array( 'utm_source' ) );

		$result = Lookup::get_preservable_querystring_params_from_url( '/test' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_preservable_querystring_params_from_url handles missing params.
	 *
	 * @covers \Automattic\LegacyRedirector\Lookup::get_preservable_querystring_params_from_url
	 */
	public function test_handles_missing_params(): void {
		// Mock the filter to preserve params that don't exist in the URL.
		\Brain\Monkey\Filters\expectApplied( 'wpcom_legacy_redirector_preserve_query_params' )
			->once()
			->andReturn( array( 'utm_source', 'utm_campaign' ) );

		$result = Lookup::get_preservable_querystring_params_from_url( '/test?utm_source=google' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'utm_source', $result );
		$this->assertArrayNotHasKey( 'utm_campaign', $result );
		$this->assertSame( 'google', $result['utm_source'] );
	}

	/**
	 * Test get_preservable_querystring_params_from_url handles unicode params.
	 *
	 * @covers \Automattic\LegacyRedirector\Lookup::get_preservable_querystring_params_from_url
	 */
	public function test_handles_unicode_params(): void {
		// Mock the filter.
		\Brain\Monkey\Filters\expectApplied( 'wpcom_legacy_redirector_preserve_query_params' )
			->once()
			->andReturn( array( 'search' ) );

		$result = Lookup::get_preservable_querystring_params_from_url( '/test?search=فوتوغرافيا' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'search', $result );
		$this->assertSame( 'فوتوغرافيا', $result['search'] );
	}

	/**
	 * Test get_preservable_querystring_params_from_url handles URL encoded params.
	 *
	 * @covers \Automattic\LegacyRedirector\Lookup::get_preservable_querystring_params_from_url
	 */
	public function test_handles_url_encoded_params(): void {
		// Mock the filter.
		\Brain\Monkey\Filters\expectApplied( 'wpcom_legacy_redirector_preserve_query_params' )
			->once()
			->andReturn( array( 'redirect' ) );

		$result = Lookup::get_preservable_querystring_params_from_url( '/test?redirect=https%3A%2F%2Fexample.com' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'redirect', $result );
		// URL decodes the value.
		$this->assertSame( 'https://example.com', $result['redirect'] );
	}

	/**
	 * Test get_preservable_querystring_params_from_url handles multiple values for same param.
	 *
	 * @covers \Automattic\LegacyRedirector\Lookup::get_preservable_querystring_params_from_url
	 */
	public function test_handles_array_params(): void {
		// Mock the filter.
		\Brain\Monkey\Filters\expectApplied( 'wpcom_legacy_redirector_preserve_query_params' )
			->once()
			->andReturn( array( 'tags' ) );

		$result = Lookup::get_preservable_querystring_params_from_url( '/test?tags[]=one&tags[]=two' );

		$this->assertIsArray( $result );
		// Should have the tags key.
		$this->assertArrayHasKey( 'tags', $result );
	}

	/**
	 * Test get_preservable_querystring_params_from_url handles empty filter array.
	 *
	 * @covers \Automattic\LegacyRedirector\Lookup::get_preservable_querystring_params_from_url
	 */
	public function test_handles_empty_filter_array(): void {
		// Mock the filter to return empty array.
		\Brain\Monkey\Filters\expectApplied( 'wpcom_legacy_redirector_preserve_query_params' )
			->once()
			->andReturn( array() );

		$result = Lookup::get_preservable_querystring_params_from_url( '/test?foo=bar&baz=qux' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}
}
