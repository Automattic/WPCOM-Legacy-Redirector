<?php
/**
 * InsertRedirectCommand unit tests.
 *
 * @package Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Application\RedirectCreationResult;
use Automattic\LegacyRedirector\Application\RedirectManager;
use Automattic\LegacyRedirector\Domain\Destination;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use Brain\Monkey\Functions;
use Mockery;
use WP_CLI;

/**
 * InsertRedirectCommandTest class.
 *
 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand
 */
final class InsertRedirectCommandTest extends MonkeyStubs {

	/**
	 * The mock manager.
	 *
	 * @var RedirectManager&Mockery\MockInterface
	 */
	private $manager;

	/**
	 * The command under test.
	 *
	 * @var InsertRedirectCommand
	 */
	private InsertRedirectCommand $command;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->manager = Mockery::mock( RedirectManager::class );
		$this->command = new InsertRedirectCommand( $this->manager );

		// Reset WP_CLI call tracker.
		WP_CLI::reset();

		// Stub home_url for host checking.
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );

		// Stub apply_filters for allowed_redirect_hosts.
		Functions\when( 'apply_filters' )->alias(
			function ( $filter, $default ) {
				if ( 'allowed_redirect_hosts' === $filter ) {
					return array( 'example.com' );
				}
				return $default;
			}
		);

		// Stub get_permalink for same-path check.
		Functions\when( 'get_permalink' )->justReturn( false );
	}

	// =========================================================================
	// Tests for successful insertion
	// =========================================================================

	/**
	 * Test invoke inserts redirect to URL successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_inserts_redirect_to_url(): void {
		$this->manager
			->shouldReceive( 'create_redirect' )
			->once()
			->with(
				Mockery::type( SourceUrl::class ),
				Mockery::type( Destination::class ),
				false,
				'publish'
			)
			->andReturn( RedirectCreationResult::success( 123 ) );

		$this->command->__invoke( array( '/old-page', '/new-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( '/old-page', $success_call[1] );
		$this->assertStringContainsString( '/new-page', $success_call[1] );
	}

	/**
	 * Test invoke inserts redirect to post ID successfully.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_inserts_redirect_to_post_id(): void {
		$this->manager
			->shouldReceive( 'create_redirect' )
			->once()
			->with(
				Mockery::type( SourceUrl::class ),
				Mockery::type( Destination::class ),
				false,
				'publish'
			)
			->andReturn( RedirectCreationResult::success( 123 ) );

		$this->command->__invoke( array( '/old-page', '456' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( '456', $success_call[1] );
	}

	// =========================================================================
	// Tests for --status flag
	// =========================================================================

	/**
	 * Test invoke inserts redirect with enabled status by default.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_inserts_with_enabled_status_by_default(): void {
		$this->manager
			->shouldReceive( 'create_redirect' )
			->once()
			->with(
				Mockery::any(),
				Mockery::any(),
				false,
				'publish' // Default is enabled (publish).
			)
			->andReturn( RedirectCreationResult::success( 123 ) );

		$this->command->__invoke( array( '/old-page', '/new-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
	}

	/**
	 * Test invoke inserts redirect with disabled status when specified.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_inserts_with_disabled_status(): void {
		$this->manager
			->shouldReceive( 'create_redirect' )
			->once()
			->with(
				Mockery::any(),
				Mockery::any(),
				false,
				'draft' // Disabled maps to draft.
			)
			->andReturn( RedirectCreationResult::success( 123 ) );

		$this->command->__invoke( array( '/old-page', '/new-page' ), array( 'status' => 'disabled' ) );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
		$success_call = WP_CLI::get_call( 'success' );
		$this->assertStringContainsString( 'disabled', $success_call[1] );
	}

	/**
	 * Test invoke inserts redirect with explicitly enabled status.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_inserts_with_explicitly_enabled_status(): void {
		$this->manager
			->shouldReceive( 'create_redirect' )
			->once()
			->with(
				Mockery::any(),
				Mockery::any(),
				false,
				'publish'
			)
			->andReturn( RedirectCreationResult::success( 123 ) );

		$this->command->__invoke( array( '/old-page', '/new-page' ), array( 'status' => 'enabled' ) );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
	}

	// =========================================================================
	// Tests for error handling
	// =========================================================================

	/**
	 * Test invoke outputs error when creation fails with duplicate.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_outputs_error_on_duplicate(): void {
		$this->manager
			->shouldReceive( 'create_redirect' )
			->once()
			->andReturn( RedirectCreationResult::error( 'duplicate-redirect-uri', 'A redirect for this URI already exists.' ) );

		$this->command->__invoke( array( '/old-page', '/new-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'already exists', $error_call[1] );
	}

	/**
	 * Test invoke outputs error for invalid source path.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_invalid_source(): void {
		$this->manager->shouldNotReceive( 'create_redirect' );

		// Empty source should fail validation.
		$this->command->__invoke( array( '', '/new-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
	}

	/**
	 * Test invoke outputs error for invalid destination.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_invalid_destination(): void {
		$this->manager->shouldNotReceive( 'create_redirect' );

		// Empty destination should fail validation.
		$this->command->__invoke( array( '/old-page', '' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
	}

	/**
	 * Test invoke outputs error when source and destination are the same.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_same_source_and_destination(): void {
		$this->manager->shouldNotReceive( 'create_redirect' );

		// Same source and destination should fail.
		$this->command->__invoke( array( '/same-page', '/same-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'should not match', $error_call[1] );
	}

	// =========================================================================
	// Tests for external URL handling
	// =========================================================================

	/**
	 * Test invoke inserts redirect to same-domain external URL.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_inserts_redirect_to_same_domain_url(): void {
		$this->manager
			->shouldReceive( 'create_redirect' )
			->once()
			->andReturn( RedirectCreationResult::success( 123 ) );

		// example.com is allowed by our stub.
		$this->command->__invoke( array( '/old-page', 'https://example.com/new-page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'success' ), 'WP_CLI::success should have been called' );
	}

	/**
	 * Test invoke outputs error for external URL to disallowed host.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_outputs_error_for_disallowed_external_host(): void {
		$this->manager->shouldNotReceive( 'create_redirect' );

		// external.com is not in our allowed hosts.
		$this->command->__invoke( array( '/old-page', 'https://external.com/page' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'safelist the domain', $error_call[1] );
	}

	// =========================================================================
	// Tests for user-friendly error messages
	// =========================================================================

	/**
	 * Test invoke maps error codes to friendly messages.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_maps_empty_postid_error_to_friendly_message(): void {
		$this->manager
			->shouldReceive( 'create_redirect' )
			->once()
			->andReturn( RedirectCreationResult::error( 'empty-postid', '' ) );

		$this->command->__invoke( array( '/old-page', '999' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'post ID does not exist', $error_call[1] );
	}

	/**
	 * Test invoke maps non-public error to friendly message.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand::__invoke
	 */
	public function test_invoke_maps_non_public_error_to_friendly_message(): void {
		$this->manager
			->shouldReceive( 'create_redirect' )
			->once()
			->andReturn( RedirectCreationResult::error( 'non-public', '' ) );

		$this->command->__invoke( array( '/old-page', '456' ), array() );

		$this->assertTrue( WP_CLI::was_called( 'error' ), 'WP_CLI::error should have been called' );
		$error_call = WP_CLI::get_call( 'error' );
		$this->assertStringContainsString( 'not published', $error_call[1] );
	}
}
