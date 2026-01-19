<?php
/**
 * Lookup class integration tests.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Integration;

use Automattic\LegacyRedirector\Lookup;
use Automattic\LegacyRedirector\Post_Type;
use WPCOM_Legacy_Redirector;

/**
 * LookupTest class.
 *
 * @covers \Automattic\LegacyRedirector\Lookup
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
				'http://example.com/some_other_page',
				'301',
			),
			'redirect_simple'                              => array(
				'/test',
				'http://example.com/',
				'301',
			),
			'redirect_unicode_no_query'                    => array(
				'/فوتوغرافيا/',
				'http://example.com/',
				'301',
			),
		);
	}

	/**
	 * Test Lookup::get_redirect_data returns false for URLs without a path.
	 *
	 * @covers Lookup::get_redirect_data
	 * @dataProvider get_urls_without_path_data
	 *
	 * @param string $url URL without a path component.
	 */
	public function test_get_redirect_data_returns_false_for_urls_without_path( $url ) {
		$this->assertFalse( Lookup::get_redirect_data( $url ) );
	}

	/**
	 * Data provider for URLs without a path component.
	 *
	 * @return array
	 */
	public function get_urls_without_path_data() {
		return array(
			'empty string'      => array( '' ),
			'query string only' => array( '?foo=bar' ),
			'fragment only'     => array( '#section' ),
			'malformed url'     => array( '://invalid' ),
		);
	}

	/**
	 * Test that trashed redirects do not redirect.
	 *
	 * @covers Lookup::get_redirect_uri
	 */
	public function test_trashed_redirect_does_not_redirect() {
		$from_url = '/trashed-redirect-test';
		$to_url   = 'http://example.com/destination';

		// Insert a redirect.
		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false, true );
		$this->assertIsInt( $post_id );

		// Verify the redirect works initially.
		$redirect_data = Lookup::get_redirect_data( $from_url );
		$this->assertIsArray( $redirect_data );
		$this->assertEquals( $to_url, $redirect_data['redirect_uri'] );

		// Trash the redirect.
		wp_trash_post( $post_id );

		// Clear the cache to ensure we're testing the post_status check.
		$url_hash = WPCOM_Legacy_Redirector::get_url_hash( $from_url );
		wp_cache_delete( $url_hash, Lookup::CACHE_GROUP );

		// Verify the redirect no longer works.
		$redirect_data = Lookup::get_redirect_data( $from_url );
		$this->assertFalse( $redirect_data );
	}

	/**
	 * Test that draft redirects do not redirect.
	 *
	 * @covers Lookup::get_redirect_uri
	 */
	public function test_draft_redirect_does_not_redirect() {
		$from_url = '/draft-redirect-test';
		$to_url   = 'http://example.com/destination';

		// Insert a redirect.
		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false, true );
		$this->assertIsInt( $post_id );

		// Change status to draft.
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		// Clear the cache.
		$url_hash = WPCOM_Legacy_Redirector::get_url_hash( $from_url );
		wp_cache_delete( $url_hash, Lookup::CACHE_GROUP );

		// Verify the redirect does not work.
		$redirect_data = Lookup::get_redirect_data( $from_url );
		$this->assertFalse( $redirect_data );
	}

	/**
	 * Test get_redirect_uri with internal post parent redirect.
	 *
	 * @covers Lookup::get_redirect_uri
	 */
	public function test_get_redirect_uri_with_internal_post_parent(): void {
		// Create destination post.
		$destination_post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'internal-destination',
				'post_title'  => 'Internal Destination',
			)
		);

		$from_url = '/internal-redirect-test';

		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $destination_post_id, false, true );
		$this->assertIsInt( $post_id );

		$redirect_uri = Lookup::get_redirect_uri( $from_url );

		$this->assertSame( get_permalink( $destination_post_id ), $redirect_uri );
	}

	/**
	 * Test get_redirect_uri with relative path in excerpt prepends home_url.
	 *
	 * @covers Lookup::get_redirect_uri
	 */
	public function test_get_redirect_uri_with_relative_path_prepends_home_url(): void {
		$from_url = '/relative-excerpt-test';
		$to_url   = '/destination-path';

		WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false );

		$redirect_uri = Lookup::get_redirect_uri( $from_url );

		$this->assertSame( home_url() . $to_url, $redirect_uri );
	}

	/**
	 * Test caching behavior - second call should use cache.
	 *
	 * @covers Lookup::get_redirect_uri
	 */
	public function test_caching_behavior(): void {
		$from_url = '/cache-test-' . wp_generate_uuid4();
		$to_url   = 'http://example.com/cached';

		WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false );

		// First call should set cache.
		$first_result = Lookup::get_redirect_uri( $from_url );
		$this->assertSame( $to_url, $first_result );

		// Check cache is set.
		$url_hash  = WPCOM_Legacy_Redirector::get_url_hash( $from_url );
		$cached_id = wp_cache_get( $url_hash, Lookup::CACHE_GROUP );
		$this->assertNotFalse( $cached_id );

		// Second call should return same result (from cache).
		$second_result = Lookup::get_redirect_uri( $from_url );
		$this->assertSame( $to_url, $second_result );
	}

	/**
	 * Test cache is reset when redirect post is deleted.
	 *
	 * @covers Lookup::get_redirect_uri
	 */
	public function test_cache_behavior_with_deleted_post(): void {
		$from_url = '/delete-cache-test-' . wp_generate_uuid4();
		$to_url   = 'http://example.com/to-delete';

		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false, true );
		$this->assertIsInt( $post_id );

		// Prime the cache.
		$result = Lookup::get_redirect_uri( $from_url );
		$this->assertSame( $to_url, $result );

		// Delete the post permanently.
		wp_delete_post( $post_id, true );

		// The redirect should no longer work.
		// Note: Cache still holds the post ID, but get_post() returns null.
		$result_after_delete = Lookup::get_redirect_uri( $from_url );
		$this->assertFalse( $result_after_delete );
	}

	/**
	 * Test get_redirect_post_id returns correct ID.
	 *
	 * @covers Lookup::get_redirect_post_id
	 */
	public function test_get_redirect_post_id_returns_correct_id(): void {
		$from_url = '/post-id-lookup-test';
		$to_url   = 'http://example.com/destination';

		$expected_post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false, true );
		$this->assertIsInt( $expected_post_id );

		$actual_post_id = Lookup::get_redirect_post_id( $from_url );

		$this->assertEquals( $expected_post_id, $actual_post_id );
	}

	/**
	 * Test get_redirect_post_id returns 0 for nonexistent redirect.
	 *
	 * @covers Lookup::get_redirect_post_id
	 */
	public function test_get_redirect_post_id_returns_zero_for_nonexistent(): void {
		$nonexistent_url = '/this-redirect-does-not-exist-' . wp_generate_uuid4();

		$post_id = Lookup::get_redirect_post_id( $nonexistent_url );

		$this->assertEquals( 0, $post_id );
	}

	/**
	 * Test get_redirect_data applies wpcom_legacy_redirector_request_path filter.
	 *
	 * @covers Lookup::get_redirect_data
	 */
	public function test_get_redirect_data_applies_request_path_filter(): void {
		$original_from = '/original-path';
		$filtered_from = '/filtered-path';
		$to_url        = 'http://example.com/destination';

		// Create redirect for the filtered path.
		WPCOM_Legacy_Redirector::insert_legacy_redirect( $filtered_from, $to_url, false );

		// Add filter to modify the request path.
		add_filter(
			'wpcom_legacy_redirector_request_path',
			function ( $path ) use ( $original_from, $filtered_from ) {
				if ( $path === $original_from ) {
					return $filtered_from;
				}
				return $path;
			}
		);

		// Request with original path should be redirected via filtered path.
		$redirect_data = Lookup::get_redirect_data( $original_from );

		$this->assertIsArray( $redirect_data );
		$this->assertSame( $to_url, $redirect_data['redirect_uri'] );

		// Clean up filter.
		remove_all_filters( 'wpcom_legacy_redirector_request_path' );
	}

	/**
	 * Test get_redirect_data applies wpcom_legacy_redirector_redirect_status filter.
	 *
	 * @covers Lookup::get_redirect_data
	 */
	public function test_get_redirect_data_applies_redirect_status_filter(): void {
		$from_url = '/status-filter-test';
		$to_url   = 'http://example.com/destination';

		WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false );

		// Add filter to change status to 302.
		add_filter(
			'wpcom_legacy_redirector_redirect_status',
			function () {
				return 302;
			}
		);

		$redirect_data = Lookup::get_redirect_data( $from_url );

		$this->assertSame( 302, $redirect_data['redirect_status'] );

		// Clean up filter.
		remove_all_filters( 'wpcom_legacy_redirector_redirect_status' );
	}

	/**
	 * Test get_redirect_data returns false when filter returns falsy path.
	 *
	 * @covers Lookup::get_redirect_data
	 */
	public function test_get_redirect_data_returns_false_on_falsy_filter_path(): void {
		$from_url = '/filter-blocks-test';
		$to_url   = 'http://example.com/destination';

		WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false );

		// Add filter to return false (block the redirect).
		add_filter( 'wpcom_legacy_redirector_request_path', '__return_false' );

		$redirect_data = Lookup::get_redirect_data( $from_url );

		$this->assertFalse( $redirect_data );

		// Clean up filter.
		remove_all_filters( 'wpcom_legacy_redirector_request_path' );
	}
}
