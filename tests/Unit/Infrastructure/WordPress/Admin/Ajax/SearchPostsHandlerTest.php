<?php
/**
 * SearchPostsHandler unit tests.
 *
 * @package Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Admin\Ajax
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit\Infrastructure\WordPress\Admin\Ajax;

use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\SearchPostsHandler;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use Brain\Monkey\Functions;
use Mockery;

/**
 * SearchPostsHandlerTest class.
 *
 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\SearchPostsHandler
 */
final class SearchPostsHandlerTest extends MonkeyStubs {

	/**
	 * The handler under test.
	 *
	 * @var SearchPostsHandler
	 */
	private SearchPostsHandler $handler;

	/**
	 * Sets up test fixtures.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->handler = new SearchPostsHandler();
	}

	/**
	 * Test get_action returns the correct action name.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\SearchPostsHandler::get_action
	 */
	public function test_get_action_returns_correct_name(): void {
		$this->assertSame( 'search_posts_for_redirect', SearchPostsHandler::get_action() );
	}

	/**
	 * Test register hooks up the AJAX action.
	 *
	 * @covers \Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\SearchPostsHandler::register
	 */
	public function test_register_adds_ajax_action(): void {
		Functions\expect( 'add_action' )
			->once()
			->with(
				'wp_ajax_search_posts_for_redirect',
				Mockery::type( 'array' )
			);

		$this->handler->register();
	}
}
