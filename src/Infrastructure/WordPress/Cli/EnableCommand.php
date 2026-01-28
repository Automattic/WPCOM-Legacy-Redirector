<?php
/**
 * Enable redirect CLI command.
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
 * Enable a redirect.
 */
final class EnableCommand extends WP_CLI_Command {

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
	 * Enable a redirect by source path or ID.
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
	 *     # Enable redirect by source path.
	 *     $ wp wpcom-legacy-redirector enable /old-page
	 *
	 *     # Enable redirect by ID.
	 *     $ wp wpcom-legacy-redirector enable 123 --by=id
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$lookup = $args[0];
		$by     = $assoc_args['by'] ?? 'source';

		// Find and enable the redirect (including disabled ones).
		if ( 'id' === $by ) {
			$redirect_id = (int) $lookup;
		} else {
			try {
				$source      = SourceUrl::from_string( $lookup );
				$redirect_id = $this->repository->get_id_by_source( $source );
				if ( 0 === $redirect_id ) {
					WP_CLI::error( sprintf( 'Redirect not found: %s', $lookup ) );
					return;
				}
			} catch ( \InvalidArgumentException $e ) {
				WP_CLI::error( sprintf( 'Invalid source path: %s', $e->getMessage() ) );
				return;
			}
		}

		$enabled = $this->manager->enable( $redirect_id );

		if ( $enabled ) {
			WP_CLI::success( sprintf( 'Enabled redirect: %s', $lookup ) );
		} else {
			WP_CLI::error( sprintf( 'Could not enable redirect: %s', $lookup ) );
		}
	}
}
