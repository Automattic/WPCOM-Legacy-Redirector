<?php
/**
 * DeleteCommand unit tests.
 *
 * @package Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Application\RedirectManager;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DeleteCommand;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use Mockery;
use WP_CLI;

/**
 * DeleteCommandTest class.
 *
 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DeleteCommand
 */
final class DeleteCommandTest extends MonkeyStubs {

	/**
	 * The mock manager.
	 *
	 * @var RedirectManager&Mockery\MockInterface
	 */
	private $manager;

	/**
	 * The command under test.
	 *
	 * @var DeleteCommand
	 */
	private DeleteCommand $command;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->manager = Mockery::mock( RedirectManager::class );
		$this->command = new DeleteCommand( $this->manager );

		// Reset WP_CLI call tracker.
		WP_CLI::reset();
	}

	// =========================================================================
	// Tests for delete by ID
	// =========================================================================

	/**
	 * Test invoke deletes redirect by ID successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DeleteCommand::__invoke
	 */
	public function test_invoke_deletes_redirect_by_id(): void {
		$this->manager
			->shouldReceive( 'delete_by_id' )
			->once()
			->with( 123 )
			->andReturn( true );

		// With --yes flag to skip confirmation.
		$this->command->__invoke(
			array( '123' ),
			array(
				'by'  => 'id',
				'yes' => true,
			)
		);

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( '123', $success_call[1] );
	}

	/**
	 * Test invoke outputs error when delete by ID fails.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DeleteCommand::__invoke
	 */
	public function test_invoke_outputs_error_when_delete_by_id_fails(): void {
		$this->manager
			->shouldReceive( 'delete_by_id' )
			->once()
			->with( 999 )
			->andReturn( false );

		$this->command->__invoke(
			array( '999' ),
			array(
				'by'  => 'id',
				'yes' => true,
			)
		);

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'not found', $error_call[1] );
	}

	// =========================================================================
	// Tests for delete by source
	// =========================================================================

	/**
	 * Test invoke deletes redirect by source path successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DeleteCommand::__invoke
	 */
	public function test_invoke_deletes_redirect_by_source(): void {
		$this->manager
			->shouldReceive( 'delete_by_source' )
			->once()
			->with( Mockery::type( SourceUrl::class ) )
			->andReturn( true );

		$this->command->__invoke(
			array( '/old-page' ),
			array(
				'by'  => 'source',
				'yes' => true,
			)
		);

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( '/old-page', $success_call[1] );
	}

	/**
	 * Test invoke outputs error when delete by source fails.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DeleteCommand::__invoke
	 */
	public function test_invoke_outputs_error_when_delete_by_source_fails(): void {
		$this->manager
			->shouldReceive( 'delete_by_source' )
			->once()
			->andReturn( false );

		$this->command->__invoke(
			array( '/nonexistent' ),
			array(
				'by'  => 'source',
				'yes' => true,
			)
		);

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'not found', $error_call[1] );
	}

	/**
	 * Test invoke outputs error for invalid source path.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DeleteCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_invalid_source_path(): void {
		$this->manager->shouldNotReceive( 'delete_by_source' );

		// Empty string should fail SourceUrl validation.
		$this->command->__invoke(
			array( '' ),
			array(
				'by'  => 'source',
				'yes' => true,
			)
		);

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'Invalid source path', $error_call[1] );
	}

	// =========================================================================
	// Tests for confirmation
	// =========================================================================

	/**
	 * Test invoke prompts for confirmation.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DeleteCommand::__invoke
	 */
	public function test_invoke_prompts_for_confirmation(): void {
		$this->manager
			->shouldReceive( 'delete_by_id' )
			->once()
			->andReturn( true );

		// Without --yes flag, should call confirm.
		$this->command->__invoke( array( '123' ), array( 'by' => 'id' ) );

		$this->assertTrue( WP_CLI::was_called( 'confirm' ), 'WP_CLI::confirm should have been called' );
		$confirm_call = WP_CLI::get_call( 'confirm' );
		$this->assertStringContainsString( '123', $confirm_call[1] );
	}

	// =========================================================================
	// Tests for default behavior
	// =========================================================================

	/**
	 * Test invoke defaults to source lookup when --by not specified.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\DeleteCommand::__invoke
	 */
	public function test_invoke_defaults_to_source_lookup(): void {
		$this->manager
			->shouldReceive( 'delete_by_source' )
			->once()
			->andReturn( true );

		// No --by argument, should default to 'source'.
		$this->command->__invoke( array( '/old-page' ), array( 'yes' => true ) );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
	}
}
