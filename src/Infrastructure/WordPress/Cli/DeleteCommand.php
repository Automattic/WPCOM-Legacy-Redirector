<?php
/**
 * Delete redirect CLI command.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Application\RedirectManager;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use WP_CLI;
use WP_CLI_Command;

/**
 * Delete a single redirect.
 */
final class DeleteCommand extends WP_CLI_Command {

	/**
	 * The redirect manager.
	 *
	 * @var RedirectManager
	 */
	private RedirectManager $manager;

	/**
	 * Constructor.
	 *
	 * @param RedirectManager $manager The redirect manager.
	 */
	public function __construct( RedirectManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Delete a redirect by source path or ID.
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
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete redirect by source path.
	 *     $ wp wpcom-legacy-redirector delete /old-page
	 *
	 *     # Delete redirect by ID.
	 *     $ wp wpcom-legacy-redirector delete 123 --by=id
	 *
	 *     # Delete without confirmation.
	 *     $ wp wpcom-legacy-redirector delete /old-page --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$lookup = $args[0];
		$by     = $assoc_args['by'] ?? 'source';

		// Confirm deletion.
		WP_CLI::confirm( sprintf( 'Are you sure you want to delete the redirect "%s"?', $lookup ), $assoc_args );

		// Delete the redirect.
		if ( 'id' === $by ) {
			$deleted = $this->manager->delete_by_id( (int) $lookup );
		} else {
			try {
				$source  = SourceUrl::from_string( $lookup );
				$deleted = $this->manager->delete_by_source( $source );
			} catch ( \InvalidArgumentException $e ) {
				WP_CLI::error( sprintf( 'Invalid source path: %s', $e->getMessage() ) );
				return;
			}
		}

		if ( $deleted ) {
			WP_CLI::success( sprintf( 'Deleted redirect: %s', $lookup ) );
		} else {
			WP_CLI::error( sprintf( 'Redirect not found: %s', $lookup ) );
		}
	}
}
