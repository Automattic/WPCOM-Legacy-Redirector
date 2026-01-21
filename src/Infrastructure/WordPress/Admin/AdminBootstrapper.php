<?php
/**
 * Bootstraps all admin functionality.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Admin;

use Automattic\LegacyRedirector\Application\RedirectManager;
use Automattic\LegacyRedirector\Application\RedirectValidator;
use Automattic\LegacyRedirector\Domain\RedirectRepositoryInterface;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\CheckDuplicateHandler;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\SearchPostsHandler;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Ajax\ValidateRedirectHandler;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\ListTable\ColumnsManager;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\ListTable\RowActionsManager;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\ListTable\ViewFilters;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Notices\ValidationNotices;
use Automattic\LegacyRedirector\Infrastructure\WordPress\Admin\Pages\RedirectFormPage;

/**
 * Initializes all admin components for the redirect management interface.
 */
final class AdminBootstrapper {

	/**
	 * Redirect repository.
	 *
	 * @var RedirectRepositoryInterface
	 */
	private RedirectRepositoryInterface $repository;

	/**
	 * Redirect manager.
	 *
	 * @var RedirectManager
	 */
	private RedirectManager $manager;

	/**
	 * Redirect validator.
	 *
	 * @var RedirectValidator
	 */
	private RedirectValidator $validator;

	/**
	 * Constructor.
	 *
	 * @param RedirectRepositoryInterface $repository Redirect repository.
	 * @param RedirectManager             $manager    Redirect manager.
	 * @param RedirectValidator           $validator  Redirect validator.
	 */
	public function __construct(
		RedirectRepositoryInterface $repository,
		RedirectManager $manager,
		RedirectValidator $validator
	) {
		$this->repository = $repository;
		$this->manager    = $manager;
		$this->validator  = $validator;
	}

	/**
	 * Initialize all admin components.
	 *
	 * @return void
	 */
	public function init(): void {
		// AJAX handlers.
		$this->register_ajax_handlers();

		// List table customization.
		$this->register_list_table_components();

		// Form pages.
		$this->register_form_pages();

		// Validation notices.
		$this->register_validation_notices();
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		$check_duplicate = new CheckDuplicateHandler( $this->repository );
		$check_duplicate->register();

		$search_posts = new SearchPostsHandler();
		$search_posts->register();

		$validate = new ValidateRedirectHandler( $this->repository, $this->validator );
		$validate->register();
	}

	/**
	 * Register list table components.
	 *
	 * @return void
	 */
	private function register_list_table_components(): void {
		$columns = new ColumnsManager();
		$columns->register();

		$row_actions = new RowActionsManager();
		$row_actions->register();

		$view_filters = new ViewFilters();
		$view_filters->register();
	}

	/**
	 * Register form pages.
	 *
	 * @return void
	 */
	private function register_form_pages(): void {
		$form_page = new RedirectFormPage( $this->repository, $this->manager, $this->validator );
		$form_page->register();
	}

	/**
	 * Register validation notices.
	 *
	 * @return void
	 */
	private function register_validation_notices(): void {
		$notices = new ValidationNotices( $this->repository, $this->validator );
		$notices->register();
	}
}
