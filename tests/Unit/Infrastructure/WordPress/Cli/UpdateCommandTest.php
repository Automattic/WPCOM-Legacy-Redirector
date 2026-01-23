<?php
/**
 * UpdateCommand unit tests.
 *
 * @package Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Application\RedirectManager;
use Automattic\LegacyRedirector\Domain\Destination;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\UpdateCommand;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use Mockery;
use WP_CLI;

/**
 * UpdateCommandTest class.
 *
 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\UpdateCommand
 */
final class UpdateCommandTest extends MonkeyStubs {

	/**
	 * The mock manager.
	 *
	 * @var RedirectManager&Mockery\MockInterface
	 */
	private $manager;

	/**
	 * The command under test.
	 *
	 * @var UpdateCommand
	 */
	private UpdateCommand $command;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->manager = Mockery::mock( RedirectManager::class );
		$this->command = new UpdateCommand( $this->manager );

		// Reset WP_CLI call tracker.
		WP_CLI::reset();
	}

	// =========================================================================
	// Tests for successful updates
	// =========================================================================

	/**
	 * Test invoke updates redirect destination to URL successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\UpdateCommand::__invoke
	 */
	public function test_invoke_updates_redirect_to_url(): void {
		$this->manager
			->shouldReceive( 'update_by_source' )
			->once()
			->with(
				Mockery::type( SourceUrl::class ),
				Mockery::type( Destination::class ),
				null
			)
			->andReturn( true );

		$this->command->__invoke( array( '/old-page', '/new-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( '/old-page', $success_call[1] );
		$this->assertStringContainsString( '/new-page', $success_call[1] );
	}

	/**
	 * Test invoke updates redirect destination to post ID successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\UpdateCommand::__invoke
	 */
	public function test_invoke_updates_redirect_to_post_id(): void {
		$this->manager
			->shouldReceive( 'update_by_source' )
			->once()
			->with(
				Mockery::type( SourceUrl::class ),
				Mockery::type( Destination::class ),
				null
			)
			->andReturn( true );

		$this->command->__invoke( array( '/old-page', '456' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( '/old-page', $success_call[1] );
		$this->assertStringContainsString( '456', $success_call[1] );
	}

	// =========================================================================
	// Tests for status changes
	// =========================================================================

	/**
	 * Test invoke updates redirect with status change to disabled.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\UpdateCommand::__invoke
	 */
	public function test_invoke_updates_redirect_with_disabled_status(): void {
		$this->manager
			->shouldReceive( 'update_by_source' )
			->once()
			->with(
				Mockery::type( SourceUrl::class ),
				Mockery::type( Destination::class ),
				'draft'
			)
			->andReturn( true );

		$this->command->__invoke( array( '/old-page', '/new-page' ), array( 'status' => 'disabled' ) );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( 'disabled', $success_call[1] );
	}

	/**
	 * Test invoke updates redirect with status change to enabled.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\UpdateCommand::__invoke
	 */
	public function test_invoke_updates_redirect_with_enabled_status(): void {
		$this->manager
			->shouldReceive( 'update_by_source' )
			->once()
			->with(
				Mockery::type( SourceUrl::class ),
				Mockery::type( Destination::class ),
				'publish'
			)
			->andReturn( true );

		$this->command->__invoke( array( '/old-page', '/new-page' ), array( 'status' => 'enabled' ) );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
	}

	// =========================================================================
	// Tests for error handling
	// =========================================================================

	/**
	 * Test invoke outputs error when redirect not found.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\UpdateCommand::__invoke
	 */
	public function test_invoke_outputs_error_when_redirect_not_found(): void {
		$this->manager
			->shouldReceive( 'update_by_source' )
			->once()
			->andReturn( false );

		$this->command->__invoke( array( '/nonexistent', '/new-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'not found', $error_call[1] );
	}

	/**
	 * Test invoke outputs error for invalid source path.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\UpdateCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_invalid_source_path(): void {
		$this->manager->shouldNotReceive( 'update_by_source' );

		// Empty source string should fail validation.
		$this->command->__invoke( array( '', '/new-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'Invalid source path', $error_call[1] );
	}

	/**
	 * Test invoke outputs error for invalid destination.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\UpdateCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_invalid_destination(): void {
		$this->manager->shouldNotReceive( 'update_by_source' );

		// Empty destination should fail validation.
		$this->command->__invoke( array( '/old-page', '' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'Invalid destination', $error_call[1] );
	}

	// =========================================================================
	// Tests for external URL destinations
	// =========================================================================

	/**
	 * Test invoke updates redirect to external URL.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\UpdateCommand::__invoke
	 */
	public function test_invoke_updates_redirect_to_external_url(): void {
		$this->manager
			->shouldReceive( 'update_by_source' )
			->once()
			->andReturn( true );

		$this->command->__invoke( array( '/old-page', 'https://example.com/page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( 'https://example.com/page', $success_call[1] );
	}
}
