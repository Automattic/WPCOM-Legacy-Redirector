<?php
/**
 * List_Redirects tests
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Integration;

use Automattic\LegacyRedirector\Capability;
use Automattic\LegacyRedirector\List_Redirects;
use Automattic\LegacyRedirector\Post_Type;
use WPCOM_Legacy_Redirector;

/**
 * List_Redirects tests class.
 *
 * @covers \Automattic\LegacyRedirector\List_Redirects
 */
final class ListRedirectsTest extends TestCase {

	/**
	 * Instance of List_Redirects.
	 *
	 * @var List_Redirects
	 */
	private List_Redirects $list_redirects;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->list_redirects = new List_Redirects();
	}

	/**
	 * Test set_columns returns expected column keys.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::set_columns
	 */
	public function test_set_columns_returns_expected_columns(): void {
		$columns = $this->list_redirects->set_columns();

		$this->assertIsArray( $columns );
		$this->assertArrayHasKey( 'cb', $columns );
		$this->assertArrayHasKey( 'from', $columns );
		$this->assertArrayHasKey( 'to', $columns );
		$this->assertArrayHasKey( 'date', $columns );
		$this->assertCount( 4, $columns );
	}

	/**
	 * Test set_columns returns translated labels.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::set_columns
	 */
	public function test_set_columns_returns_translated_labels(): void {
		$columns = $this->list_redirects->set_columns();

		$this->assertSame( 'Redirect From', $columns['from'] );
		$this->assertSame( 'Redirect To', $columns['to'] );
		$this->assertSame( 'Date', $columns['date'] );
	}

	/**
	 * Test posts_custom_column displays from URL for 'from' column.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::posts_custom_column
	 */
	public function test_posts_custom_column_displays_from_url(): void {
		$from_url = '/test-from-url';
		$to_url   = 'http://example.com/destination';

		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false, true );
		$this->assertIsInt( $post_id );

		ob_start();
		$this->list_redirects->posts_custom_column( 'from', $post_id );
		$output = ob_get_clean();

		$this->assertSame( $from_url, $output );
	}

	/**
	 * Test posts_custom_column displays external URL for 'to' column.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::posts_custom_column
	 */
	public function test_posts_custom_column_displays_external_to_url(): void {
		$from_url = '/test-from-external';
		$to_url   = 'http://example.com/external';

		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false, true );
		$this->assertIsInt( $post_id );

		ob_start();
		$this->list_redirects->posts_custom_column( 'to', $post_id );
		$output = ob_get_clean();

		$this->assertSame( $to_url, $output );
	}

	/**
	 * Test posts_custom_column displays relative path for 'to' column.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::posts_custom_column
	 */
	public function test_posts_custom_column_displays_relative_to_url(): void {
		$from_url = '/test-from-relative';
		$to_url   = '/destination-path';

		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false, true );
		$this->assertIsInt( $post_id );

		ob_start();
		$this->list_redirects->posts_custom_column( 'to', $post_id );
		$output = ob_get_clean();

		$this->assertSame( $to_url, $output );
	}

	/**
	 * Test posts_custom_column displays home URL redirect.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::posts_custom_column
	 */
	public function test_posts_custom_column_displays_home_redirect(): void {
		$from_url = '/redirect-to-home';
		$to_url   = '/';

		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false, true );
		$this->assertIsInt( $post_id );

		ob_start();
		$this->list_redirects->posts_custom_column( 'to', $post_id );
		$output = ob_get_clean();

		$this->assertSame( '/', $output );
	}

	/**
	 * Test posts_custom_column displays post parent redirect (internal).
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::posts_custom_column
	 */
	public function test_posts_custom_column_displays_internal_post_redirect(): void {
		// Create a destination post.
		$destination_post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_name'   => 'destination-post',
				'post_title'  => 'Destination Post',
			)
		);

		$from_url = '/redirect-to-internal-post';

		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $destination_post_id, false, true );
		$this->assertIsInt( $post_id );

		ob_start();
		$this->list_redirects->posts_custom_column( 'to', $post_id );
		$output = ob_get_clean();

		// Should display a link to the destination post (plain or pretty permalink).
		// With plain permalinks it's /?p=X, with pretty permalinks it's /destination-post/.
		$this->assertTrue(
			str_contains( $output, 'destination-post' ) || str_contains( $output, '?p=' . $destination_post_id ),
			'Expected output to contain either "destination-post" or "?p=' . $destination_post_id . '", got: ' . $output
		);
	}

	/**
	 * Test posts_custom_column shows warning for private internal redirect.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::posts_custom_column
	 */
	public function test_posts_custom_column_shows_warning_for_private_internal_redirect(): void {
		// Create a draft destination post.
		$destination_post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_name'   => 'private-destination',
				'post_title'  => 'Private Destination',
			)
		);

		$from_url = '/redirect-to-private';

		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $destination_post_id, false, true );
		$this->assertIsInt( $post_id );

		ob_start();
		$this->list_redirects->posts_custom_column( 'to', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Warning', $output );
		$this->assertStringContainsString( 'not a public URL', $output );
	}

	/**
	 * Test posts_custom_column shows error for nonexistent post parent.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::posts_custom_column
	 */
	public function test_posts_custom_column_shows_error_for_nonexistent_post_parent(): void {
		// Create redirect post directly to simulate orphaned redirect.
		// Must set post_excerpt to empty string explicitly to simulate internal redirect.
		$redirect_post_id = self::factory()->post->create(
			array(
				'post_type'    => Post_Type::POST_TYPE,
				'post_status'  => 'publish',
				'post_name'    => WPCOM_Legacy_Redirector::get_url_hash( '/orphaned-redirect' ),
				'post_title'   => '/orphaned-redirect',
				'post_parent'  => 999999999, // Nonexistent post ID.
				'post_excerpt' => '', // Empty excerpt = internal redirect via post_parent.
				'post_content' => '', // Empty content to prevent auto-excerpt.
			)
		);

		ob_start();
		$this->list_redirects->posts_custom_column( 'to', $redirect_post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Post ID that does not exist', $output );
	}

	/**
	 * Test modify_list_row_actions adds validate and follow links.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::modify_list_row_actions
	 */
	public function test_modify_list_row_actions_adds_custom_actions(): void {
		// Register capabilities first.
		$capability = new Capability();
		$capability->register();

		// Create an admin user with the capability.
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Force refresh the user's capabilities.
		$user = wp_get_current_user();
		$user->get_role_caps();

		// Verify the user has the capability.
		$this->assertTrue(
			current_user_can( Capability::MANAGE_REDIRECTS_CAPABILITY ),
			'User should have manage_redirects capability'
		);

		$from_url = '/test-row-actions';
		$to_url   = '/internal-destination'; // Use internal URL to avoid validation errors.

		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false, true );
		$this->assertIsInt( $post_id, 'Should create redirect successfully' );

		$post = get_post( $post_id );

		$default_actions = array(
			'edit'  => '<a href="#">Edit</a>',
			'trash' => '<a href="#">Trash</a>',
		);

		$actions = $this->list_redirects->modify_list_row_actions( $default_actions, $post );

		$this->assertArrayHasKey( 'validate', $actions, 'Actions should have validate key. Got: ' . wp_json_encode( array_keys( $actions ) ) );
		$this->assertArrayHasKey( 'follow', $actions );
		$this->assertArrayHasKey( 'trash', $actions );
		$this->assertArrayNotHasKey( 'edit', $actions ); // Edit should be removed.

		// Validate link should contain nonce.
		$this->assertStringContainsString( 'action=validate', $actions['validate'] );
		$this->assertStringContainsString( '_validate_redirect', $actions['validate'] );

		// Follow link should contain the from URL.
		$this->assertStringContainsString( $from_url, $actions['follow'] );
		$this->assertStringContainsString( 'target="_blank"', $actions['follow'] );

		// Clean up.
		$capability->unregister();
	}

	/**
	 * Test modify_list_row_actions returns unchanged actions for non-redirect post types.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::modify_list_row_actions
	 */
	public function test_modify_list_row_actions_unchanged_for_other_post_types(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$post    = get_post( $post_id );

		$default_actions = array(
			'edit'  => '<a href="#">Edit</a>',
			'trash' => '<a href="#">Trash</a>',
		);

		$actions = $this->list_redirects->modify_list_row_actions( $default_actions, $post );

		$this->assertSame( $default_actions, $actions );
	}

	/**
	 * Test modify_list_row_actions preserves actions when in trash view.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::modify_list_row_actions
	 */
	public function test_modify_list_row_actions_preserves_trash_view_actions(): void {
		$_GET['post_status'] = 'trash';

		$from_url = '/trashed-redirect-row';
		$to_url   = 'http://example.com/';

		$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url, false, true );
		wp_trash_post( $post_id );
		$post = get_post( $post_id );

		$default_actions = array(
			'untrash' => '<a href="#">Restore</a>',
			'delete'  => '<a href="#">Delete Permanently</a>',
		);

		$actions = $this->list_redirects->modify_list_row_actions( $default_actions, $post );

		$this->assertSame( $default_actions, $actions );

		unset( $_GET['post_status'] );
	}

	/**
	 * Test init method registers hooks.
	 *
	 * @covers \Automattic\LegacyRedirector\List_Redirects::init
	 */
	public function test_init_registers_hooks(): void {
		// Remove any existing hooks first.
		remove_all_filters( 'manage_vip-legacy-redirect_posts_columns' );
		remove_all_actions( 'manage_vip-legacy-redirect_posts_custom_column' );
		remove_all_filters( 'post_row_actions' );

		$list_redirects = new List_Redirects();
		$list_redirects->init();

		$this->assertNotFalse( has_filter( 'manage_vip-legacy-redirect_posts_columns', array( $list_redirects, 'set_columns' ) ) );
		$this->assertNotFalse( has_action( 'manage_vip-legacy-redirect_posts_custom_column', array( $list_redirects, 'posts_custom_column' ) ) );
		$this->assertNotFalse( has_filter( 'post_row_actions', array( $list_redirects, 'modify_list_row_actions' ) ) );
	}
}
