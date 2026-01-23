<?php
/**
 * EnableCommand unit tests.
 *
 * @package Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Application\RedirectManager;
use Automattic\LegacyRedirector\Domain\Destination;
use Automattic\LegacyRedirector\Domain\DestinationUrl;
use Automattic\LegacyRedirector\Domain\Redirect;
use Automattic\LegacyRedirector\Domain\RedirectRepositoryInterface;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\EnableCommand;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use Mockery;
use WP_CLI;

/**
 * EnableCommandTest class.
 *
 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\EnableCommand
 */
final class EnableCommandTest extends MonkeyStubs {

	/**
	 * The mock manager.
	 *
	 * @var RedirectManager&Mockery\MockInterface
	 */
	private $manager;

	/**
	 * The mock repository.
	 *
	 * @var RedirectRepositoryInterface&Mockery\MockInterface
	 */
	private $repository;

	/**
	 * The command under test.
	 *
	 * @var EnableCommand
	 */
	private EnableCommand $command;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->manager    = Mockery::mock( RedirectManager::class );
		$this->repository = Mockery::mock( RedirectRepositoryInterface::class );
		$this->command    = new EnableCommand( $this->manager, $this->repository );

		// Reset WP_CLI call tracker.
		WP_CLI::reset();
	}

	// =========================================================================
	// Tests for enable by ID
	// =========================================================================

	/**
	 * Test invoke enables redirect by ID successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\EnableCommand::__invoke
	 */
	public function test_invoke_enables_redirect_by_id(): void {
		$this->manager
			->shouldReceive( 'enable' )
			->once()
			->with( 123 )
			->andReturn( true );

		$this->command->__invoke( array( '123' ), array( 'by' => 'id' ) );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( '123', $success_call[1] );
	}

	/**
	 * Test invoke outputs error when enable by ID fails.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\EnableCommand::__invoke
	 */
	public function test_invoke_outputs_error_when_enable_by_id_fails(): void {
		$this->manager
			->shouldReceive( 'enable' )
			->once()
			->with( 999 )
			->andReturn( false );

		$this->command->__invoke( array( '999' ), array( 'by' => 'id' ) );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'Could not enable', $error_call[1] );
	}

	// =========================================================================
	// Tests for enable by source
	// =========================================================================

	/**
	 * Test invoke enables redirect by source path successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\EnableCommand::__invoke
	 */
	public function test_invoke_enables_redirect_by_source(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'draft' );

		$this->repository
			->shouldReceive( 'find_by_source' )
			->once()
			->andReturn( $redirect );

		$this->manager
			->shouldReceive( 'enable' )
			->once()
			->with( 123 )
			->andReturn( true );

		$this->command->__invoke( array( '/old-page' ), array( 'by' => 'source' ) );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( '/old-page', $success_call[1] );
	}

	/**
	 * Test invoke outputs error when redirect not found by source.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\EnableCommand::__invoke
	 */
	public function test_invoke_outputs_error_when_not_found_by_source(): void {
		$this->repository
			->shouldReceive( 'find_by_source' )
			->once()
			->andReturn( null );

		$this->manager->shouldNotReceive( 'enable' );

		$this->command->__invoke( array( '/nonexistent' ), array( 'by' => 'source' ) );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'not found', $error_call[1] );
	}

	/**
	 * Test invoke outputs error for invalid source path.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\EnableCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_invalid_source_path(): void {
		$this->repository->shouldNotReceive( 'find_by_source' );
		$this->manager->shouldNotReceive( 'enable' );

		// Empty string should fail SourceUrl validation.
		$this->command->__invoke( array( '' ), array( 'by' => 'source' ) );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'Invalid source path', $error_call[1] );
	}

	// =========================================================================
	// Tests for default behavior
	// =========================================================================

	/**
	 * Test invoke defaults to source lookup when --by not specified.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\EnableCommand::__invoke
	 */
	public function test_invoke_defaults_to_source_lookup(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'draft' );

		$this->repository
			->shouldReceive( 'find_by_source' )
			->once()
			->andReturn( $redirect );

		$this->manager
			->shouldReceive( 'enable' )
			->once()
			->andReturn( true );

		// No --by argument, should default to 'source'.
		$this->command->__invoke( array( '/old-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
	}
}
