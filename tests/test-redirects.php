<?php

class WpcomLegacyRedirectsTest extends WP_UnitTestCase {

	/**
	 * Makes sure the foundational stuff is sorted so tests work
	 */
	function setUp() {

		// We need to trick the plugin into thinking it's run by WP-CLI
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		// We need to trick the plugin into thinking we're in admin
		if ( ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}

	}

	public function get_redirect_data() {
		return array(
			'redirect_simple' => array(
				'/simple-redirect',
				'http://example.com'
			),

			'redirect_with_querystring' => array(
				'/a-redirect?with=query-string',
				'http://example.com'
			),

			'redirect_with_hashes' => array(
				// The plugin should strip the hash and only store the URL path.
				'/hash-redirect#with-hash',
				'http://example.com'
			),

			'redirect_unicode_in_path' => array(
				// https://www.w3.org/International/articles/idn-and-iri/
				'/JP納豆',
				'http://example.com',
			),
		);
	}

	/**
	 * @dataProvider get_redirect_data
	 */
	function test_redirect( $from, $to ) {
		$redirect = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from, $to, false );
		$this->assertTrue( $redirect, 'insert_legacy_redirect failed' );

		$redirect = WPCOM_Legacy_Redirector::get_redirect_uri( $from );
		$this->assertEquals( $redirect, $to, 'get_redirect_uri failed' );
	}


	/**
	 * Data Provider of Redirect Rules and test urls for Protected Params
	 *
	 * @return array
	 */
 	public function get_protected_redirect_data() {
		return array(
			'redirect_simple_protected' => array(
 				'/simple-redirectA/',
 				'http://example.com/',
 				'/simple-redirectA/?utm_source=XYZ',
				'http://example.com/?utm_source=XYZ'
			),

			'redirect_protected_with_querystring' => array(
				'/b-redirect/?with=query-string',
				'http://example.com/',
				'/b-redirect/?with=query-string&utm_medium=123',
				'http://example.com/?utm_medium=123'
			),

			'redirect_protected_with_hashes' => array(
				// The plugin should strip the hash and only store the URL path.
				'/hash-redirectA/#with-hash',
				'http://example.com/',
				'/hash-redirectA/?utm_source=SDF#with-hash',
 				'http://example.com/?utm_source=SDF'
			),

			'redirect_multiple_protected' => array(
				'/simple-redirectC/',
				'http://example.com/',
				'/simple-redirectC/?utm_source=XYZ&utm_medium=FALSE&utm_campaign=543',
				'http://example.com/?utm_source=XYZ&utm_medium=FALSE&utm_campaign=543'
			)
		);
	}

	/**
	 * Verify that whitelisted parameters are maintained on final redirect urls.
	 *
	 * @dataProvider get_protected_redirect_data
	 */
	function test_protected_query_redirect( $from, $to, $protected_from, $protected_to ) {
		add_filter( 'wpcom_legacy_redirector_preserve_query_params', function( $preserved_params ){
 			array_push( $preserved_params,
				'utm_source',
				'utm_medium',
				'utm_campaign'
			);
			return $preserved_params;
		} );

		$redirect = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from, $to, false );
		$this->assertTrue( $redirect, 'insert_legacy_redirect failed' );

		$redirect = WPCOM_Legacy_Redirector::get_redirect_uri( $protected_from );
		$this->assertEquals( $redirect, $protected_to, 'get_redirect_uri failed' );
	}

	public function get_external_url_redirects() {
		return array(
			'external_url' => array(
				'redirect' => array(
					'to' => array(
						'raw' => 'http://google.com',
						'formatted' => 'http://google.com',
					),
				),
			),
			'external_url_with_force_ssl' => array(
				'redirect' => array(
					'to' => array(
						'raw' => 'http://google.com',
						'formatted' => 'https://google.com',
					),
				),
			),
		);
	}

	/**
	 * Test that URLs to invalid hosts will not validate.
	 *
	 * @dataProvider get_external_url_redirects
	 */
	function test_invalid_url_redirect( $redirect ) {
		$validation = WPCOM_Legacy_Redirector::validate_url_redirect( $redirect, get_post_types() );
		$this->assertTrue( is_wp_error( $validation ) );
	}

	function allow_redirects_to_google( $hosts ) {
		$hosts[] = 'google.com';
		return $hosts;
	}

	/**
	 * Test that URLs to valid hosts will validate.
	 *
	 * @dataProvider get_external_url_redirects
	 */
	function test_valid_url_redirect( $redirect ) {
		add_filter( 'allowed_redirect_hosts' , array( $this, 'allow_redirects_to_google' ) , 10 );

		$validation = WPCOM_Legacy_Redirector::validate_url_redirect( $redirect, get_post_types() );
		$this->assertTrue( $validation );
	}

	public function get_invalid_post_redirects() {
		return array(
			'no_parent' => array(
				'redirect' => array(
					'parent' => array(
						'id' => 0,
						'status' => 'publish',
						'post_type' => 'post',
					),
				),
			),
			'unpublished_post' => array(
				'redirect' => array(
					'parent' => array(
						'id' => 1,
						'status' => 'draft',
						'post_type' => 'post',
					),

				),
			),
			'private_post' => array(
				'redirect' => array(
					'parent' => array(
						'id' => 1,
						'status' => 'private',
						'post_type' => 'post',
					),
				),
			),
			'auto_draft' => array(
				'redirect' => array(
					'parent' => array(
						'id' => 1,
						'status' => 'auto_draft',
						'post_type' => 'post',
					),
				),
			),
			'non_public_post_type' => array(
				'redirect' => array(
					'parent' => array(
						'id' => 1,
						'status' => 'publish',
						'post_type' => 'not_a_post_type',
					),
				),
			),
		);
	}

	/**
	 * Test that URLs to valid hosts will validate.
	 *
	 * @dataProvider get_invalid_post_redirects
	 */
	function test_invalid_post_redirect( $redirect ) {
		$validation = WPCOM_Legacy_Redirector::validate_post_redirect( $redirect, get_post_types() );
		$this->assertTrue( is_wp_error( $validation ) );
	}

	public function get_valid_post_redirects() {
		return array(
			'published' => array(
				'redirect' => array(
					'parent' => array(
						'id' => 1,
						'status' => 'publish',
						'post_type' => 'post',
					),
				),
			),
			'attachment' => array(
				'redirect' => array(
					'parent' => array(
						'id' => 1,
						'status' => 'inherit',
						'post_type' => 'attachment',
					),
				),
			),
		);
	}

	/**
	 * Test that URLs to valid hosts will validate.
	 *
	 * @dataProvider get_valid_post_redirects
	 */
	function test_valid_post_redirect( $redirect ) {
		$validation = WPCOM_Legacy_Redirector::validate_post_redirect( $redirect, get_post_types() );
		$this->assertTrue( $validation );
	}

	public function get_failing_verified_post_redirects() {
		return array(
			'no_redirect_status' => array(
				'redirect' => array(
					'to' => array(
						'formatted' => home_url( '/redirect_here' ),
					),
				),
			),
			'trailing_slash' => array(
				'redirect' => array(
					'from' => array(
						'raw' => '1',
						'formatted' => '/post1',
					),
					'to' => array(
						'formatted' => 'http://google.com',
					),
					'redirect' => array(
						'status' => 200,
						'count' => 1,
						'resulting_url' => 'http://google.com/',
					),
				),
			),
			'mismatch' => array(
				'redirect' => array(
					'from' => array(
						'raw' => '1',
						'formatted' => '/post1',
					),
					'to' => array(
						'formatted' => 'http://google.com',
					),
					'redirect' => array(
						'status' => 200,
						'count' => 1,
						'resulting_url' => '/post1',
					),
				),
			),
			'too_many_redirects' => array(
				'redirect' => array(
					'from' => array(
						'raw' => '1',
						'formatted' => '/post1',
					),
					'to' => array(
						'formatted' => '/redirect_location',
					),
					'redirect' => array(
						'status' => 200,
						'count' => 2,
						'resulting_url' => '/somewhere_else',
					),
				),
			),
			'redirect_to_404' => array(
				'redirect' => array(
					'from' => array(
						'raw' => '1',
						'formatted' => '/post1',
					),
					'to' => array(
						'formatted' => '/somewhere_else',
					),
					'redirect' => array(
						'status' => 404,
						'count' => 1,
						'resulting_url' => '/somewhere_else',
					),
				),
			),
		);
	}

	/**
	 * Test for verification failure notices.
	 *
	 * @dataProvider get_failing_verified_post_redirects
	 */
	function test_verify_failing_redirect_status( $redirect ) {
		$verification = WPCOM_Legacy_Redirector::verify_redirect_status( $redirect, get_post_types() );
		$this->assertTrue( is_wp_error( $verification ) );
	}

	public function get_passing_verified_post_redirects() {
		return array(
			'match' => array(
				'redirect' => array(
					'from' => array(
						'raw' => '1',
						'formatted' => '/post1',
					),
					'to' => array(
						'formatted' => 'http://google.com',
					),
					'redirect' => array(
						'status' => 200,
						'count' => 1,
						'resulting_url' => 'http://google.com',
					),
				),
			),
		);
	}

	/**
	 * Test for verification failure notices.
	 *
	 * @dataProvider get_passing_verified_post_redirects
	 */
	function test_verify_bad_redirect_status( $redirect ) {
		$verification = WPCOM_Legacy_Redirector::verify_redirect_status( $redirect, get_post_types() );
		$this->assertTrue( $verification );
	}

}
