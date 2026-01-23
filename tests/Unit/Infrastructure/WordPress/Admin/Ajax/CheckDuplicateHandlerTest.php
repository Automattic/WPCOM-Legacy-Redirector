<?php
/**
 * CheckDuplicateHandler unit tests.
 *
 * @package Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Admin\Ajax
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Admin\Ajax;

use Automattic\LegacyRedirector\Domain\RedirectRepositoryInterface;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\CheckDuplicateHandler;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use Brain\Monkey\Functions;
use Mockery;

/**
 * CheckDuplicateHandlerTest class.
 *
 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\CheckDuplicateHandler
 */
final class CheckDuplicateHandlerTest extends MonkeyStubs {

	/**
	 * The mock repository.
	 *
	 * @var RedirectRepositoryInterface&Mockery\MockInterface
	 */
	private $repository;

	/**
	 * The handler under test.
	 *
	 * @var CheckDuplicateHandler
	 */
	private CheckDuplicateHandler $handler;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->repository = Mockery::mock( RedirectRepositoryInterface::class );
		$this->handler    = new CheckDuplicateHandler( $this->repository );
	}

	/**
	 * Test get_action returns the correct action name.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\CheckDuplicateHandler::get_action
	 */
	public function test_get_action_returns_correct_name(): void {
		$this->assertSame( 'check_redirect_duplicate', CheckDuplicateHandler::get_action() );
	}

	/**
	 * Test register hooks up the AJAX action.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\CheckDuplicateHandler::register
	 */
	public function test_register_adds_ajax_action(): void {
		Functions\expect( 'add_action' )
			->once()
			->with(
				'wp_ajax_check_redirect_duplicate',
				Mockery::type( 'array' )
			);

		$this->handler->register();
	}
}
