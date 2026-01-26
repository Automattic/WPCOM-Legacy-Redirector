<?php
/**
 * ListCommand unit tests.
 *
 * @package Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli
 *
 * Note: ListCommand directly uses WP_Query, so we use a WP_Query stub that
 * captures constructor arguments for verification.
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use Brain\Monkey\Functions;
use WP_CLI;
use WP_Query;

/**
 * ListCommandTest class.
 *
 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand
 */
final class ListCommandTest extends MonkeyStubs {

	/**
	 * The command under test.
	 *
	 * @var ListCommand
	 */
	private ListCommand $command;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->command = new ListCommand();

		// Reset WP_CLI and WP_Query trackers.
		WP_CLI::reset();
		WP_Query::reset();
		$GLOBALS['wp_cli_format_items_calls'] = array();

		// Stub wp_list_pluck.
		Functions\when( 'wp_list_pluck' )->alias(
			function ( $list, $field ) {
				return array_map(
					function ( $item ) use ( $field ) {
						return is_object( $item ) ? $item->$field : $item[ $field ];
					},
					$list
				);
			}
		);
	}

	// =========================================================================
	// Tests for status filtering
	// =========================================================================

	/**
	 * Test invoke maps 'enabled' status to 'publish' post_status.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
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
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
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
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
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
	// Tests for destination type filtering
	// =========================================================================

	/**
	 * Test invoke filters by post destination type.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_filters_by_post_destination_type(): void {
		$this->command->__invoke(
			array(),
			array(
				'destination-type' => 'post',
				'format'           => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertArrayHasKey( 'post_parent__not_in', WP_Query::$last_args );
		$this->assertSame( array( 0 ), WP_Query::$last_args['post_parent__not_in'] );
	}

	/**
	 * Test invoke filters by URL destination type.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_filters_by_url_destination_type(): void {
		$this->command->__invoke(
			array(),
			array(
				'destination-type' => 'url',
				'format'           => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertArrayHasKey( 'post_parent', WP_Query::$last_args );
		$this->assertSame( 0, WP_Query::$last_args['post_parent'] );
	}

	// =========================================================================
	// Tests for pagination
	// =========================================================================

	/**
	 * Test invoke applies limit correctly.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_applies_limit(): void {
		$this->command->__invoke(
			array(),
			array(
				'limit'  => '50',
				'format' => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 50, WP_Query::$last_args['posts_per_page'] );
	}

	/**
	 * Test invoke applies offset correctly.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_applies_offset(): void {
		$this->command->__invoke(
			array(),
			array(
				'offset' => '100',
				'format' => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 100, WP_Query::$last_args['offset'] );
	}

	/**
	 * Test invoke defaults to limit of 100.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_defaults_to_limit_100(): void {
		$this->command->__invoke( array(), array( 'format' => 'count' ) );

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 100, WP_Query::$last_args['posts_per_page'] );
	}

	// =========================================================================
	// Tests for search
	// =========================================================================

	/**
	 * Test invoke applies search query.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_applies_search_query(): void {
		$this->command->__invoke(
			array(),
			array(
				'search' => 'blog',
				'format' => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertArrayHasKey( 's', WP_Query::$last_args );
		$this->assertSame( 'blog', WP_Query::$last_args['s'] );
	}

	/**
	 * Test invoke does not add search when empty.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_does_not_add_empty_search(): void {
		$this->command->__invoke(
			array(),
			array(
				'search' => '',
				'format' => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertArrayNotHasKey( 's', WP_Query::$last_args );
	}

	// =========================================================================
	// Tests for ordering
	// =========================================================================

	/**
	 * Test invoke applies orderby correctly.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_applies_orderby(): void {
		$this->command->__invoke(
			array(),
			array(
				'orderby' => 'title',
				'format'  => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 'title', WP_Query::$last_args['orderby'] );
	}

	/**
	 * Test invoke applies order correctly.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_applies_order(): void {
		$this->command->__invoke(
			array(),
			array(
				'order'  => 'asc',
				'format' => 'count',
			)
		);

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 'ASC', WP_Query::$last_args['order'] );
	}

	// =========================================================================
	// Tests for output formats
	// =========================================================================

	/**
	 * Test invoke outputs count format.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_outputs_count_format(): void {
		WP_Query::mock_results( array(), 42 );

		$this->command->__invoke( array(), array( 'format' => 'count' ) );

		$this->assertTrue( WP_CLI::was_called( 'line' ), 'WP_CLI::line should have been called' );
		$line_call = WP_CLI::get_call( 'line' );
		$this->assertSame( '42', $line_call[1] );
	}

	/**
	 * Test invoke outputs ids format.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_outputs_ids_format(): void {
		$posts = array(
			(object) array( 'ID' => 1 ),
			(object) array( 'ID' => 2 ),
			(object) array( 'ID' => 3 ),
		);
		WP_Query::mock_results( $posts, 3 );

		$this->command->__invoke( array(), array( 'format' => 'ids' ) );

		$this->assertTrue( WP_CLI::was_called( 'line' ), 'WP_CLI::line should have been called' );
		$line_call = WP_CLI::get_call( 'line' );
		$this->assertSame( '1 2 3', $line_call[1] );
	}

	/**
	 * Test invoke shows warning when no redirects found.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_shows_warning_when_empty(): void {
		WP_Query::mock_results( array(), 0 );

		$this->command->__invoke( array(), array( 'format' => 'table' ) );

		$this->assertTrue( WP_CLI::was_called( 'warning' ), 'WP_CLI::warning should have been called' );
		$warning_call = WP_CLI::get_call( 'warning' );
		$this->assertStringContainsString( 'No redirects found', $warning_call[1] );
	}

	/**
	 * Test invoke formats table output correctly.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_formats_table_output(): void {
		$posts = array(
			(object) array(
				'ID'           => 1,
				'post_title'   => '/old-page',
				'post_excerpt' => '/new-page',
				'post_parent'  => 0,
				'post_status'  => 'publish',
			),
		);
		WP_Query::mock_results( $posts, 1 );

		$this->command->__invoke( array(), array( 'format' => 'table' ) );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items_calls'], 'format_items should have been called' );
		$format_call = $GLOBALS['wp_cli_format_items_calls'][0];
		$this->assertSame( 'table', $format_call[0] );

		// Check the formatted item has correct fields.
		$item = $format_call[1][0];
		$this->assertSame( 1, $item['ID'] );
		$this->assertSame( '/old-page', $item['from'] );
		$this->assertSame( '/new-page', $item['to'] );
		$this->assertSame( 'url', $item['type'] );
		$this->assertSame( 'enabled', $item['status'] );
	}

	/**
	 * Test invoke correctly identifies post type destination.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_identifies_post_type_destination(): void {
		$posts = array(
			(object) array(
				'ID'           => 1,
				'post_title'   => '/old-page',
				'post_excerpt' => '',
				'post_parent'  => 456, // Post ID destination.
				'post_status'  => 'draft',
			),
		);
		WP_Query::mock_results( $posts, 1 );

		$this->command->__invoke( array(), array( 'format' => 'table' ) );

		$format_call = $GLOBALS['wp_cli_format_items_calls'][0];
		$item        = $format_call[1][0];
		$this->assertSame( 456, $item['to'] );
		$this->assertSame( 'post', $item['type'] );
		$this->assertSame( 'disabled', $item['status'] );
	}

	/**
	 * Test invoke uses correct post type.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ListCommand::__invoke
	 */
	public function test_invoke_uses_correct_post_type(): void {
		$this->command->__invoke( array(), array( 'format' => 'count' ) );

		$this->assertNotNull( WP_Query::$last_args );
		$this->assertSame( 'vip-legacy-redirect', WP_Query::$last_args['post_type'] );
	}
}
