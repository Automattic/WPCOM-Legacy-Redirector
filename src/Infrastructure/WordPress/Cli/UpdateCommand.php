<?php
/**
 * Update redirect CLI command.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Application\RedirectManager;
use Automattic\LegacyRedirector\Domain\Destination;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use WP_CLI;
use WP_CLI_Command;

/**
 * Update a single redirect.
 */
final class UpdateCommand extends WP_CLI_Command {

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
	 * Update a redirect's destination.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : The source path to find (e.g., /old-page).
	 *
	 * <destination>
	 * : The new destination. Can be a path, full URL, or post ID.
	 *
	 * [--status=<status>]
	 * : Optionally change the status.
	 * ---
	 * options:
	 *   - enabled
	 *   - disabled
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Update redirect destination.
	 *     $ wp wpcom-legacy-redirector update /old-page /new-page
	 *
	 *     # Update redirect to point to a post.
	 *     $ wp wpcom-legacy-redirector update /old-page 123
	 *
	 *     # Update redirect and disable it.
	 *     $ wp wpcom-legacy-redirector update /old-page /new-page --status=disabled
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$source_path = $args[0];
		$dest_value  = is_numeric( $args[1] ) ? (int) $args[1] : $args[1];
		$status_flag = $assoc_args['status'] ?? null;

		// Parse source.
		try {
			$source = SourceUrl::from_string( $source_path );
		} catch ( \InvalidArgumentException $e ) {
			WP_CLI::error( sprintf( 'Invalid source path: %s', $e->getMessage() ) );
			return;
		}

		// Parse destination.
		try {
			$destination = Destination::from_mixed( $dest_value );
		} catch ( \InvalidArgumentException $e ) {
			WP_CLI::error( sprintf( 'Invalid destination: %s', $e->getMessage() ) );
			return;
		}

		// Convert status flag to post status.
		$post_status = null;
		if ( null !== $status_flag ) {
			$post_status = 'disabled' === $status_flag ? 'draft' : 'publish';
		}

		// Attempt update.
		$updated = $this->manager->update_by_source( $source, $destination, $post_status );

		if ( $updated ) {
			$status_msg = null !== $status_flag ? " (status: $status_flag)" : '';
			WP_CLI::success( sprintf( 'Updated %s -> %s%s', $source_path, $dest_value, $status_msg ) );
		} else {
			WP_CLI::error( sprintf( 'Redirect not found: %s', $source_path ) );
		}
	}
}
