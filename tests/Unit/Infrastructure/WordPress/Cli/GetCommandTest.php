<?php
/**
 * GetCommand unit tests.
 *
 * @package Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Domain\Destination;
use Automattic\LegacyRedirector\Domain\DestinationPostId;
use Automattic\LegacyRedirector\Domain\DestinationUrl;
use Automattic\LegacyRedirector\Domain\Redirect;
use Automattic\LegacyRedirector\Domain\RedirectRepositoryInterface;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use Mockery;
use WP_CLI;

/**
 * GetCommandTest class.
 *
 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand
 */
final class GetCommandTest extends MonkeyStubs {

	/**
	 * The mock repository.
	 *
	 * @var RedirectRepositoryInterface&Mockery\MockInterface
	 */
	private $repository;

	/**
	 * The command under test.
	 *
	 * @var GetCommand
	 */
	private GetCommand $command;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->repository = Mockery::mock( RedirectRepositoryInterface::class );
		$this->command    = new GetCommand( $this->repository );

		// Reset WP_CLI call tracker.
		WP_CLI::reset();
		$GLOBALS['wp_cli_format_items_calls'] = array();
	}

	// =========================================================================
	// Tests for lookup by ID
	// =========================================================================

	/**
	 * Test invoke finds redirect by ID successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_finds_redirect_by_id(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'publish' );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->once()
			->with( 123 )
			->andReturn( $redirect );

		$this->command->__invoke(
			array( '123' ),
			array(
				'by'     => 'id',
				'format' => 'table',
			)
		);

		// Should have called format_items with table data.
		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items_calls'], 'format_items should have been called' );
		$this->assertSame( 'table', $GLOBALS['wp_cli_format_items_calls'][0][0] );
	}

	/**
	 * Test invoke outputs error when redirect not found by ID.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_outputs_error_when_not_found_by_id(): void {
		$this->repository
			->shouldReceive( 'find_by_id' )
			->once()
			->with( 999 )
			->andReturn( null );

		$this->command->__invoke( array( '999' ), array( 'by' => 'id' ) );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'not found', $error_call[1] );
	}

	// =========================================================================
	// Tests for lookup by source
	// =========================================================================

	/**
	 * Test invoke finds redirect by source path successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_finds_redirect_by_source(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'publish' );

		$this->repository
			->shouldReceive( 'find_by_source' )
			->once()
			->andReturn( $redirect );

		$this->command->__invoke(
			array( '/old-page' ),
			array(
				'by'     => 'source',
				'format' => 'json',
			)
		);

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items_calls'], 'format_items should have been called' );
		$this->assertSame( 'json', $GLOBALS['wp_cli_format_items_calls'][0][0] );
	}

	/**
	 * Test invoke outputs error when redirect not found by source.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_outputs_error_when_not_found_by_source(): void {
		$this->repository
			->shouldReceive( 'find_by_source' )
			->once()
			->andReturn( null );

		$this->command->__invoke( array( '/nonexistent' ), array( 'by' => 'source' ) );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'not found', $error_call[1] );
	}

	/**
	 * Test invoke outputs error for invalid source path.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_invalid_source_path(): void {
		$this->repository->shouldNotReceive( 'find_by_source' );

		// Empty string should fail SourceUrl validation.
		$this->command->__invoke( array( '' ), array( 'by' => 'source' ) );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'Invalid source path', $error_call[1] );
	}

	// =========================================================================
	// Tests for --field option
	// =========================================================================

	/**
	 * Test invoke returns single field when requested.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_returns_single_field(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'publish' );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->andReturn( $redirect );

		$this->command->__invoke(
			array( '123' ),
			array(
				'by'    => 'id',
				'field' => 'destination',
			)
		);

		$this->assertTrue( WP_CLI::was_called( 'line' ), 'WP_CLI::line should have been called' );
		$line_call = WP_CLI::get_call( 'line' );
		$this->assertSame( '/new-page', $line_call[1] );
	}

	/**
	 * Test invoke outputs error for invalid field.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_invalid_field(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'publish' );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->andReturn( $redirect );

		$this->command->__invoke(
			array( '123' ),
			array(
				'by'    => 'id',
				'field' => 'invalid_field',
			)
		);

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'Invalid field', $error_call[1] );
	}

	// =========================================================================
	// Tests for different destination types
	// =========================================================================

	/**
	 * Test invoke displays post ID destination correctly.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_displays_post_id_destination(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_post_id( DestinationPostId::from_int( 456 ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'publish' );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->andReturn( $redirect );

		$this->command->__invoke(
			array( '123' ),
			array(
				'by'    => 'id',
				'field' => 'type',
			)
		);

		$this->assertTrue( WP_CLI::was_called( 'line' ), 'WP_CLI::line should have been called' );
		$line_call = WP_CLI::get_call( 'line' );
		$this->assertSame( 'post', $line_call[1] );
	}

	/**
	 * Test invoke displays disabled status correctly.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_displays_disabled_status(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'draft' );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->andReturn( $redirect );

		$this->command->__invoke(
			array( '123' ),
			array(
				'by'    => 'id',
				'field' => 'status',
			)
		);

		$this->assertTrue( WP_CLI::was_called( 'line' ), 'WP_CLI::line should have been called' );
		$line_call = WP_CLI::get_call( 'line' );
		$this->assertSame( 'disabled', $line_call[1] );
	}

	/**
	 * Test invoke defaults to source lookup when --by not specified.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_defaults_to_source_lookup(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'publish' );

		$this->repository
			->shouldReceive( 'find_by_source' )
			->once()
			->andReturn( $redirect );

		// No --by argument, should default to 'source'.
		$this->command->__invoke( array( '/old-page' ), array() );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items_calls'], 'format_items should have been called' );
	}

	/**
	 * Test invoke defaults to table format when --format not specified.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\GetCommand::__invoke
	 */
	public function test_invoke_defaults_to_table_format(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'publish' );

		$this->repository
			->shouldReceive( 'find_by_id' )
			->andReturn( $redirect );

		// No --format argument, should default to 'table'.
		$this->command->__invoke( array( '123' ), array( 'by' => 'id' ) );

		$this->assertNotEmpty( $GLOBALS['wp_cli_format_items_calls'], 'format_items should have been called' );
		$this->assertSame( 'table', $GLOBALS['wp_cli_format_items_calls'][0][0] );
	}
}
