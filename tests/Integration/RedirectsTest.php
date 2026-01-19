<?php
/**
 * Redirects tests
 *
 * @package Automattic\LegacyRedirector
 */

namespace Automattic\LegacyRedirector\Tests\Integration;

use Automattic\LegacyRedirector\Lookup;
use WPCOM_Legacy_Redirector;

/**
 * Redirects tests class.
 */
final class RedirectsTest extends TestCase {

	/**
	 * Data provider.
	 *
	 * Each item in the outermost array should be an array containing:
	 * - $from path
	 * - $to destination
	 *
	 * @return array<string, array>
	 */
	public function get_redirect_data() {
		return array(
			'redirect_relative_path'  => array(
				'/non-existing-page',
				'/test2',
				home_url() . '/test2',
			),

			'redirect_unicode_in_path'  => array(
				// https://www.w3.org/International/articles/idn-and-iri/ .
				'/JP納豆',
				'http://example.com',
			),

			'redirect Arabic in path'  => array(
				// https://www.w3.org/International/articles/idn-and-iri/ .
				'/فوتوغرافيا/?test=فوتوغرافيا',
				'http://example.com',
			),

			'redirect_simple'           => array(
				'/simple-redirect',
				'http://example.com',
			),

			'redirect_with_querystring' => array(
				'/a-redirect?with=query-string',
				'http://example.com',
			),

			'redirect_with_hashes'      => array(
				// The plugin should strip the hash and only store the URL path.
				'/hash-redirect#with-hash',
				'http://example.com',
			),
		);
	}

	/**
	 * Test redirect is inserted successfully and returns true.
	 *
	 * @dataProvider get_redirect_data
	 * @covers       WPCOM_Legacy_Redirector::insert_legacy_redirect
	 * @param string $from From path.
	 * @param string $to   Destination.
	 */
	public function test_redirect_is_inserted_successfully_and_returns_true( $from, $to, $expected = null ) {
		$redirect = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from, $to, false );
		$this->assertTrue( $redirect, 'insert_legacy_redirect() and return true, failed' );

		$redirect = Lookup::get_redirect_uri( $from );

		if ( \is_null( $expected ) ) {
			$expected = $to;
		}
		$this->assertEquals( $expected, $redirect, 'get_redirect_uri(), failed - got "' . $redirect . '", expected "' . $to . '"' );
	}

	/**
	 * Test redirect is inserted successfully and returns a post ID.
	 *
	 * @covers WPCOM_Legacy_Redirector::insert_legacy_redirect
	 */
	public function test_redirect_is_inserted_successfully_and_returns_post_id() {
		$redirect = WPCOM_Legacy_Redirector::insert_legacy_redirect( '/simple-redirect', 'http://example.com', false, true );
		self::assertIsInt( $redirect, 'insert_legacy_redirect() and return post ID, failed' );
	}

	/**
	 * Data Provider of Redirect Rules and test urls for Protected Params
	 *
	 * @return array
	 */
	public function get_protected_redirect_data() {
		return array(
			'redirect_simple_protected'           => array(
				'/simple-redirectA/',
				'http://example.com/',
				'/simple-redirectA/?utm_source=XYZ',
				'http://example.com/?utm_source=XYZ',
			),

			'redirect_protected_with_querystring' => array(
				'/b-redirect/?with=query-string',
				'http://example.com/',
				'/b-redirect/?with=query-string&utm_medium=123',
				'http://example.com/?utm_medium=123',
			),

			'redirect_protected_with_hashes'      => array(
				// The plugin should strip the hash and only store the URL path.
				'/hash-redirectA/#with-hash',
				'http://example.com/',
				'/hash-redirectA/?utm_source=SDF#with-hash',
				'http://example.com/?utm_source=SDF',
			),

			'redirect_multiple_protected'         => array(
				'/simple-redirectC/',
				'http://example.com/',
				'/simple-redirectC/?utm_source=XYZ&utm_medium=FALSE&utm_campaign=543',
				'http://example.com/?utm_source=XYZ&utm_medium=FALSE&utm_campaign=543',
			),
		);
	}

	/**
	 * Verify that safelisted parameters are maintained on final redirect URLs.
	 *
	 * @dataProvider get_protected_redirect_data
	 * @covers       WPCOM_Legacy_Redirector::insert_legacy_redirect
	 * @covers       \Automattic\LegacyRedirector\Lookup::get_redirect_uri
	 * @param string $from           From path.
	 * @param string $to             Destination.
	 * @param string $protected_from From path with preserved params.
	 * @param string $protected_to   Destination. with preserved params.
	 */
	public function test_protected_query_redirect( $from, $to, $protected_from, $protected_to ) {
		add_filter(
			'wpcom_legacy_redirector_preserve_query_params',
			function ( $preserved_params ) {
				array_push(
					$preserved_params,
					'utm_source',
					'utm_medium',
					'utm_campaign'
				);
				return $preserved_params;
			}
		);

		$redirect = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from, $to, false );
		$this->assertTrue( $redirect, 'insert_legacy_redirect failed' );

		$redirect = Lookup::get_redirect_uri( $protected_from );
		$this->assertEquals( $redirect, $protected_to, 'get_redirect_uri failed' );
	}

	/**
	 * Test redirect to a post ID works correctly (covers CLI use case).
	 *
	 * @covers WPCOM_Legacy_Redirector::insert_legacy_redirect
	 * @covers WPCOM_Legacy_Redirector::validate_destination_post_id
	 */
	public function test_redirect_to_post_id_with_validation() {
		// Create a published post to redirect to.
		$destination_post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Destination Post',
			)
		);

		// Insert redirect to post ID (simulates CLI: wp wpcom-legacy-redirector insert-redirect /foo 123).
		$result = WPCOM_Legacy_Redirector::insert_legacy_redirect( '/redirect-to-post-id', $destination_post_id, true );
		$this->assertTrue( $result, 'insert_legacy_redirect() to post ID should succeed' );

		// Verify the redirect works.
		$redirect_uri = Lookup::get_redirect_uri( '/redirect-to-post-id' );
		$this->assertEquals( get_permalink( $destination_post_id ), $redirect_uri );
	}

	/**
	 * Test redirect to non-existent post ID fails validation.
	 *
	 * @covers WPCOM_Legacy_Redirector::insert_legacy_redirect
	 * @covers WPCOM_Legacy_Redirector::validate_destination_post_id
	 */
	public function test_redirect_to_nonexistent_post_id_fails() {
		// Use a very high post ID that doesn't exist.
		$result = WPCOM_Legacy_Redirector::insert_legacy_redirect( '/redirect-to-nonexistent', 999999999, true );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'empty-postid', $result->get_error_code() );
	}

	/**
	 * Test redirect to draft post ID fails validation.
	 *
	 * @covers WPCOM_Legacy_Redirector::insert_legacy_redirect
	 * @covers WPCOM_Legacy_Redirector::validate_destination_post_id
	 */
	public function test_redirect_to_draft_post_id_fails() {
		// Create a draft post.
		$draft_post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_title'  => 'Draft Post',
			)
		);

		$result = WPCOM_Legacy_Redirector::insert_legacy_redirect( '/redirect-to-draft', $draft_post_id, true );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'empty-postid', $result->get_error_code() );
	}
}
