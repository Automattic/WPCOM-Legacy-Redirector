<?php
/**
 * DisableCommand unit tests.
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
use Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DisableCommand;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use Mockery;
use WP_CLI;

/**
 * DisableCommandTest class.
 *
 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DisableCommand
 */
final class DisableCommandTest extends MonkeyStubs {

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
	 * @var DisableCommand
	 */
	private DisableCommand $command;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->manager    = Mockery::mock( RedirectManager::class );
		$this->repository = Mockery::mock( RedirectRepositoryInterface::class );
		$this->command    = new DisableCommand( $this->manager, $this->repository );

		// Reset WP_CLI call tracker.
		WP_CLI::reset();
	}

	// =========================================================================
	// Tests for disable by ID
	// =========================================================================

	/**
	 * Test invoke disables redirect by ID successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DisableCommand::__invoke
	 */
	public function test_invoke_disables_redirect_by_id(): void {
		$this->manager
			->shouldReceive( 'disable' )
			->once()
			->with( 123 )
			->andReturn( true );

		$this->command->__invoke( array( '123' ), array( 'by' => 'id' ) );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( '123', $success_call[1] );
	}

	/**
	 * Test invoke outputs error when disable by ID fails.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DisableCommand::__invoke
	 */
	public function test_invoke_outputs_error_when_disable_by_id_fails(): void {
		$this->manager
			->shouldReceive( 'disable' )
			->once()
			->with( 999 )
			->andReturn( false );

		$this->command->__invoke( array( '999' ), array( 'by' => 'id' ) );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'Could not disable', $error_call[1] );
	}

	// =========================================================================
	// Tests for disable by source
	// =========================================================================

	/**
	 * Test invoke disables redirect by source path successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DisableCommand::__invoke
	 */
	public function test_invoke_disables_redirect_by_source(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'publish' );

		$this->repository
			->shouldReceive( 'find_by_source' )
			->once()
			->andReturn( $redirect );

		$this->manager
			->shouldReceive( 'disable' )
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
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DisableCommand::__invoke
	 */
	public function test_invoke_outputs_error_when_not_found_by_source(): void {
		$this->repository
			->shouldReceive( 'find_by_source' )
			->once()
			->andReturn( null );

		$this->manager->shouldNotReceive( 'disable' );

		$this->command->__invoke( array( '/nonexistent' ), array( 'by' => 'source' ) );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'not found', $error_call[1] );
	}

	/**
	 * Test invoke outputs error for invalid source path.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DisableCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_invalid_source_path(): void {
		$this->repository->shouldNotReceive( 'find_by_source' );
		$this->manager->shouldNotReceive( 'disable' );

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
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DisableCommand::__invoke
	 */
	public function test_invoke_defaults_to_source_lookup(): void {
		$source      = SourceUrl::from_string( '/old-page' );
		$destination = Destination::from_url( DestinationUrl::from_string( '/new-page' ) );
		$redirect    = Redirect::reconstitute( 123, $source, $destination, 'publish' );

		$this->repository
			->shouldReceive( 'find_by_source' )
			->once()
			->andReturn( $redirect );

		$this->manager
			->shouldReceive( 'disable' )
			->once()
			->andReturn( true );

		// No --by argument, should default to 'source'.
		$this->command->__invoke( array( '/old-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
	}
}
