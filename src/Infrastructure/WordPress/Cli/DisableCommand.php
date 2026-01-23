<?php
/**
 * Disable redirect CLI command.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Application\RedirectManager;
use Automattic\LegacyRedirector\Domain\RedirectRepositoryInterface;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use WP_CLI;
use WP_CLI_Command;

/**
 * Disable a redirect.
 */
final class DisableCommand extends WP_CLI_Command {

	/**
	 * The redirect manager.
	 *
	 * @var RedirectManager
	 */
	private RedirectManager $manager;

	/**
	 * The redirect repository.
	 *
	 * @var RedirectRepositoryInterface
	 */
	private RedirectRepositoryInterface $repository;

	/**
	 * Constructor.
	 *
	 * @param RedirectManager             $manager    The redirect manager.
	 * @param RedirectRepositoryInterface $repository The redirect repository.
	 */
	public function __construct( RedirectManager $manager, RedirectRepositoryInterface $repository ) {
		$this->manager    = $manager;
		$this->repository = $repository;
	}

	/**
	 * Disable a redirect by source path or ID.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : The source path (e.g., /old-page) or redirect ID.
	 *
	 * [--by=<field>]
	 * : How to look up the redirect.
	 * ---
	 * default: source
	 * options:
	 *   - source
	 *   - id
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Disable redirect by source path.
	 *     $ wp wpcom-legacy-redirector disable /old-page
	 *
	 *     # Disable redirect by ID.
	 *     $ wp wpcom-legacy-redirector disable 123 --by=id
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$lookup = $args[0];
		$by     = $assoc_args['by'] ?? 'source';

		// Find and disable the redirect.
		if ( 'id' === $by ) {
			$redirect_id = (int) $lookup;
		} else {
			try {
				$source   = SourceUrl::from_string( $lookup );
				$redirect = $this->repository->find_by_source( $source );
				if ( null === $redirect ) {
					WP_CLI::error( sprintf( 'Redirect not found: %s', $lookup ) );
					return;
				}
				$redirect_id = $redirect->id();
			} catch ( \InvalidArgumentException $e ) {
				WP_CLI::error( sprintf( 'Invalid source path: %s', $e->getMessage() ) );
				return;
			}
		}

		$disabled = $this->manager->disable( $redirect_id );

		if ( $disabled ) {
			WP_CLI::success( sprintf( 'Disabled redirect: %s', $lookup ) );
		} else {
			WP_CLI::error( sprintf( 'Could not disable redirect: %s', $lookup ) );
		}
	}
}
