<?php
/**
 * ValidateCommand unit tests.
 *
 * @package Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli
 *
 * Note: ValidateCommand directly uses WP_Query and makes HTTP requests, so we use
 * stubs to capture arguments and simulate responses for verification.
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ValidateCommand;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use Brain\Monkey\Functions;
use WP_CLI;
use WP_Post;
use WP_Query;

/**
 * ValidateCommandTest class.
 *
 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ValidateCommand
 */
final class ValidateCommandTest extends MonkeyStubs {

	/**
	 * The command under test.
	 *
	 * @var ValidateCommand
	 */
	private ValidateCommand $command;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->command = new ValidateCommand();

		// Reset WP_CLI and WP_Query trackers.
		WP_CLI::reset();
		WP_Query::reset();
		$GLOBALS['wp_cli_format_items_calls'] = array();

		// Stub get_post for destination checking.
		Functions\when( 'get_post' )->justReturn( null );

		// Stub get_page_by_path for relative path checking.
		Functions\when( 'get_page_by_path' )->justReturn( null );

		// Stub home_url for full URL building.
		Functions\when( 'home_url' )->alias(
			function ( $path = '' ) {
				return 'https://example.com' . $path;
			}
		);

		// Stub wp_remote_head for URL checking.
		Functions\when( 'wp_remote_head' )->justReturn( array() );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'is_wp_error' )->justReturn( false );

		// Stub wp_update_post for fix functionality.
		Functions\when( 'wp_update_post' )->justReturn( 1 );

		// Stub stop_the_insanity (VIP memory management).
		Functions\when( 'stop_the_insanity' )->justReturn( null );
	}

	/**
	 * Create a WP_Post mock for testing.
	 *
	 * @param array $data Post data.
	 * @return WP_Post
	 */
	private function create_mock_post( array $data ): WP_Post {
		return WP_Post::from_array( $data );
	}

	// =========================================================================
	// Tests for status filtering
	// =========================================================================

	/**
	 * Test invoke maps 'enabled' status to 'publish' post_status.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_maps_enabled_status_to_publish(): void {
		$this->command->__invoke(
			array(),
			array(
				'status' => 'enabled',
				'format' => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 'publish', WP_Query::$last_args['post_status'] );
	}

	/**
	 * Test invoke maps 'disabled' status to 'draft' post_status.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_maps_disabled_status_to_draft(): void {
		$this->command->__invoke(
			array(),
			array(
				'status' => 'disabled',
				'format' => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 'draft', WP_Query::$last_args['post_status'] );
	}

	/**
	 * Test invoke uses array for 'any' status.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_uses_array_for_any_status(): void {
		$this->command->__invoke(
			array(),
			array(
				'status' => 'any',
				'format' => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertIsArray( WP_Query::$last_args['post_status'] );
		$this->assertContains( 'publish', WP_Query::$last_args['post_status'] );
		$this->assertContains( 'draft', WP_Query::$last_args['post_status'] );
	}

	// =========================================================================
	// Tests for limit
	// =========================================================================

	/**
	 * Test invoke applies limit correctly.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_applies_limit(): void {
		$this->command->__invoke(
			array(),
			array(
				'limit'  => '500',
				'format' => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 500, WP_Query::$last_args['posts_per_page'] );
	}

	/**
	 * Test invoke defaults to limit of 1000.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_defaults_to_limit_1000(): void {
		$this->command->__invoke( array(), array( 'format' => 'count' ) );

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 1000, WP_Query::$last_args['posts_per_page'] );
	}

	// =========================================================================
	// Tests for broken redirect detection
	// =========================================================================

	/**
	 * Test invoke detects deleted post destination.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_detects_deleted_post_destination(): void {
		$posts = array(
			$this->create_mock_post(
				array(
					'ID'           => 1,
					'post_title'   => '/old-page',
					'post_excerpt' => '',
					'post_parent'  => 999, // Points to non-existent post.
					'post_status'  => 'publish',
				)
			),
		);
		WP_Query::mock_results( $posts, 1 );

		// get_post returns null for deleted post.
		Functions\when( 'get_post' )->justReturn( null );

		$this->command->__invoke( array(), array( 'format' => 'table' ) );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items_calls'], 'format_items should have been called' );
		$format_call = $GLOBALS['wp_cli_format_items_calls'][0];
		$item        = $format_call[1][0];
		$this->assertSame( 'Post deleted', $item['issue'] );
	}

	/**
	 * Test invoke detects trashed post destination.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_detects_trashed_post_destination(): void {
		$posts = array(
			$this->create_mock_post(
				array(
					'ID'           => 1,
					'post_title'   => '/old-page',
					'post_excerpt' => '',
					'post_parent'  => 456, // Points to trashed post.
					'post_status'  => 'publish',
				)
			),
		);
		WP_Query::mock_results( $posts, 1 );

		// get_post returns trashed post.
		$trashed_post = $this->create_mock_post( array( 'post_status' => 'trash' ) );
		Functions\when( 'get_post' )->justReturn( $trashed_post );

		$this->command->__invoke( array(), array( 'format' => 'table' ) );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items_calls'], 'format_items should have been called' );
		$format_call = $GLOBALS['wp_cli_format_items_calls'][0];
		$item        = $format_call[1][0];
		$this->assertSame( 'Post trashed', $item['issue'] );
	}

	/**
	 * Test invoke detects unpublished post destination.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_detects_unpublished_post_destination(): void {
		$posts = array(
			$this->create_mock_post(
				array(
					'ID'           => 1,
					'post_title'   => '/old-page',
					'post_excerpt' => '',
					'post_parent'  => 456, // Points to draft post.
					'post_status'  => 'publish',
				)
			),
		);
		WP_Query::mock_results( $posts, 1 );

		// get_post returns draft post.
		$draft_post = $this->create_mock_post( array( 'post_status' => 'draft' ) );
		Functions\when( 'get_post' )->justReturn( $draft_post );

		$this->command->__invoke( array(), array( 'format' => 'table' ) );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items_calls'], 'format_items should have been called' );
		$format_call = $GLOBALS['wp_cli_format_items_calls'][0];
		$item        = $format_call[1][0];
		$this->assertStringContainsString( 'not published', $item['issue'] );
	}

	/**
	 * Test invoke detects empty URL destination.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_detects_empty_destination(): void {
		$posts = array(
			$this->create_mock_post(
				array(
					'ID'           => 1,
					'post_title'   => '/old-page',
					'post_excerpt' => '', // Empty URL destination.
					'post_parent'  => 0,
					'post_status'  => 'publish',
				)
			),
		);
		WP_Query::mock_results( $posts, 1 );

		$this->command->__invoke( array(), array( 'format' => 'table' ) );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items_calls'], 'format_items should have been called' );
		$format_call = $GLOBALS['wp_cli_format_items_calls'][0];
		$item        = $format_call[1][0];
		$this->assertSame( 'Empty destination', $item['issue'] );
	}

	/**
	 * Test invoke passes valid post destination.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ValidateCommand::__invoke
	 */
	public function test_invoke_passes_valid_post_destination(): void {
		$posts = array(
			$this->create_mock_post(
				array(
					'ID'           => 1,
					'post_title'   => '/old-page',
					'post_excerpt' => '',
					'post_parent'  => 456, // Points to published post.
					'post_status'  => 'publish',
				)
			),
		);
		WP_Query::mock_results( $posts, 1 );

		// get_post returns published post.
		$published_post = $this->create_mock_post( array( 'post_status' => 'publish' ) );
		Functions\when( 'get_post' )->justReturn( $published_post );

		$this->command->__invoke( array(), array( 'format' => 'table' ) );

		// No broken redirects found.
		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( 'no issues found', $success_call[1] );
	}

	// =========================================================================
	// Tests for output formats
	// =========================================================================

	/**
	 * Test invoke outputs count format.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_outputs_count_format(): void {
		$posts = array(
			$this->create_mock_post(
				array(
					'ID'           => 1,
					'post_title'   => '/old-page',
					'post_excerpt' => '',
					'post_parent'  => 999, // Broken.
					'post_status'  => 'publish',
				)
			),
			$this->create_mock_post(
				array(
					'ID'           => 2,
					'post_title'   => '/another-page',
					'post_excerpt' => '',
					'post_parent'  => 998, // Also broken.
					'post_status'  => 'publish',
				)
			),
		);
		WP_Query::mock_results( $posts, 2 );

		Functions\when( 'get_post' )->justReturn( null );

		$this->command->__invoke( array(), array( 'format' => 'count' ) );

		$this->assertTrue( WP_CLI::was_called( 'line' ), 'WP_CLI::line should have been called' );

		// Find the count line (the command outputs "Checking up to X redirects..." first).
		$line_calls  = WP_CLI::get_calls( 'line' );
		$found_count = false;
		foreach ( $line_calls as $call ) {
			if ( '2' === $call[1] ) {
				$found_count = true;
				break;
			}
		}
		$this->assertTrue( $found_count, 'Count output "2" should have been called' );
	}

	/**
	 * Test invoke shows success when no broken redirects.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ValidateCommand::__invoke
	 */
	public function test_invoke_shows_success_when_all_valid(): void {
		// URL destinations pass without --check-urls (can't verify without HTTP).
		$posts = array(
			$this->create_mock_post(
				array(
					'ID'           => 1,
					'post_title'   => '/old-page',
					'post_excerpt' => '/new-page',
					'post_parent'  => 0,
					'post_status'  => 'publish',
				)
			),
		);
		WP_Query::mock_results( $posts, 1 );

		$this->command->__invoke( array(), array( 'format' => 'table' ) );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( 'no issues found', $success_call[1] );
	}

	/**
	 * Test invoke shows warning when broken redirects found.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_shows_warning_when_broken_found(): void {
		$posts = array(
			$this->create_mock_post(
				array(
					'ID'           => 1,
					'post_title'   => '/old-page',
					'post_excerpt' => '',
					'post_parent'  => 999,
					'post_status'  => 'publish',
				)
			),
		);
		WP_Query::mock_results( $posts, 1 );

		Functions\when( 'get_post' )->justReturn( null );

		$this->command->__invoke( array(), array( 'format' => 'table' ) );

		// Check that we get the "broken redirect" warning, not just the "slow" warning.
		$warning_calls = WP_CLI::get_calls( 'warning' );
		$found_broken  = false;
		foreach ( $warning_calls as $call ) {
			if ( strpos( $call[1], 'broken' ) !== false ) {
				$found_broken = true;
				break;
			}
		}
		$this->assertTrue( $found_broken, 'WP_CLI::warning with "broken" should have been called' );
	}

	// =========================================================================
	// Tests for fix functionality
	// =========================================================================

	/**
	 * Test invoke disables broken redirects when --fix is used.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_fixes_broken_redirects(): void {
		$posts = array(
			$this->create_mock_post(
				array(
					'ID'           => 1,
					'post_title'   => '/old-page',
					'post_excerpt' => '',
					'post_parent'  => 999, // Broken.
					'post_status'  => 'publish', // Enabled redirect.
				)
			),
		);
		WP_Query::mock_results( $posts, 1 );

		Functions\when( 'get_post' )->justReturn( null );

		// Track wp_update_post calls.
		$update_calls = array();
		Functions\when( 'wp_update_post' )->alias(
			function ( $args ) use ( &$update_calls ) {
				$update_calls[] = $args;
				return $args['ID'];
			}
		);

		$this->command->__invoke(
			array(),
			array(
				'fix'    => true,
				'format' => 'table',
			)
		);

		$this->assertNotEmpty( $update_calls, 'wp_update_post should have been called' );
		$this->assertSame( 1, $update_calls[0]['ID'] );
		$this->assertSame( 'draft', $update_calls[0]['post_status'] );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_calls = WP_CLI::get_calls( 'success' );
		$last_success  = end( $success_calls );
		$this->assertStringContainsString( 'Disabled', $last_success[1] );
	}

	/**
	 * Test invoke skips fixing already disabled redirects.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_skips_fixing_disabled_redirects(): void {
		$posts = array(
			$this->create_mock_post(
				array(
					'ID'           => 1,
					'post_title'   => '/old-page',
					'post_excerpt' => '',
					'post_parent'  => 999, // Broken.
					'post_status'  => 'draft', // Already disabled.
				)
			),
		);
		WP_Query::mock_results( $posts, 1 );

		Functions\when( 'get_post' )->justReturn( null );

		// Track wp_update_post calls.
		$update_calls = array();
		Functions\when( 'wp_update_post' )->alias(
			function ( $args ) use ( &$update_calls ) {
				$update_calls[] = $args;
				return $args['ID'];
			}
		);

		$this->command->__invoke(
			array(),
			array(
				'fix'    => true,
				'format' => 'table',
			)
		);

		// Should not call wp_update_post for already disabled redirect.
		$this->assertEmpty( $update_calls, 'wp_update_post should not be called for disabled redirects' );
	}

	// =========================================================================
	// Tests for check-urls flag
	// =========================================================================

	/**
	 * Test invoke shows warning when check-urls is enabled.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_shows_warning_for_check_urls(): void {
		WP_Query::mock_results( array(), 0 );

		$this->command->__invoke(
			array(),
			array(
				'check-urls' => true,
				'format'     => 'count',
			)
		);

		$this->assertTrue( WP_CLI::was_called( 'warning' ), 'WP_CLI::warning should have been called' );
		$warning_call = WP_CLI::get_call( 'warning' );
		$this->assertStringContainsString( 'slow', $warning_call[1] );
	}

	/**
	 * Test invoke uses correct post type.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\VerifyCommand::__invoke
	 */
	public function test_invoke_uses_correct_post_type(): void {
		$this->command->__invoke( array(), array( 'format' => 'count' ) );

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 'vip-legacy-redirect', WP_Query::$last_args['post_type'] );
	}
}
