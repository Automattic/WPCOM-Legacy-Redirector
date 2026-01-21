<?php
/**
 * Plugin bootstrapper.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress;

use Automattic\LegacyRedirector\Infrastructure\DI\Container;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\AdminBootstrapper;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\BulkActionsHandler;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\StatusActionsHandler;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Notices\StatusChangeNotices;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\TrashRedirectEnhancer;

/**
 * Bootstraps the plugin by registering all hooks and initializing components.
 *
 * This class replaces the legacy WPCOM_Legacy_Redirector::start() method,
 * providing a cleaner separation of concerns with dedicated handler classes.
 */
final class PluginBootstrapper {

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Initialize the plugin.
	 *
	 * Registers all hooks and initializes components.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register post type on init.
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Register capability on admin_init.
		add_action( 'admin_init', array( $this, 'register_capability' ) );

		// Register redirect handler on template_redirect (early, before canonical).
		add_filter( 'template_redirect', array( $this, 'maybe_do_redirect' ), 0 );

		// Initialize admin components.
		$this->init_admin();
	}

	/**
	 * Register the custom post type for redirects.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$post_type = new PostType();
		$post_type->register();
	}

	/**
	 * Register capabilities for redirect management.
	 *
	 * @return void
	 */
	public function register_capability(): void {
		$capability = new Capability();
		$capability->register();
	}

	/**
	 * Check for redirects and perform if needed.
	 *
	 * @return void
	 */
	public function maybe_do_redirect(): void {
		$this->container->executor()->maybe_redirect();
	}

	/**
	 * Initialize admin components.
	 *
	 * @return void
	 */
	private function init_admin(): void {
		// Initialize the main admin bootstrapper (AJAX handlers, list table, form pages).
		$admin = new AdminBootstrapper(
			$this->container->inner_repository(),
			$this->container->manager(),
			$this->container->validator()
		);
		$admin->init();

		// Register bulk actions handler.
		$bulk_actions = new BulkActionsHandler( $this->container->manager() );
		$bulk_actions->register();

		// Register status actions handler (single enable/disable).
		$status_actions = new StatusActionsHandler( $this->container->manager() );
		$status_actions->register();

		// Register status change notices.
		$status_notices = new StatusChangeNotices();
		$status_notices->register();

		// Register trash redirect enhancer.
		$trash_enhancer = new TrashRedirectEnhancer();
		$trash_enhancer->register();
	}
}
