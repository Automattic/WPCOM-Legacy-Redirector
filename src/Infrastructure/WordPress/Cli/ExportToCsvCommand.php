<?php
/**
 * Export to CSV CLI command.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Infrastructure\WordPress\PostType;
use WP_CLI;
use WP_CLI_Command;

/**
 * Export redirects to a CSV file.
 */
final class ExportToCsvCommand extends WP_CLI_Command {

	/**
	 * Export redirects to a CSV file.
	 *
	 * Exports redirects with the following structure:
	 *    redirect_from_path,(redirect_to_post_id|redirect_to_path|redirect_to_url),status
	 *
	 * The status column contains 'enabled' or 'disabled'.
	 *
	 * ## OPTIONS
	 *
	 * --csv=<path-to-csv>
	 * : Path to CSV.
	 *
	 * [--status=<status>]
	 * : Filter by redirect status.
	 * ---
	 * default: any
	 * options:
	 *   - any
	 *   - enabled
	 *   - disabled
	 * ---
	 *
	 * [--overwrite]
	 * : Whether to overwrite an existing file. Defaults to false.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export all redirects to a CSV file.
	 *     $ wp wpcom-legacy-redirector export-to-csv --csv=path/to/redirects.csv
	 *
	 *     # Export only enabled redirects.
	 *     $ wp wpcom-legacy-redirector export-to-csv --csv=path/to/redirects.csv --status=enabled
	 *
	 *     # Export only disabled redirects.
	 *     $ wp wpcom-legacy-redirector export-to-csv --csv=path/to/redirects.csv --status=disabled
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$filename      = $assoc_args['csv'] ?? false;
		$overwrite     = isset( $assoc_args['overwrite'] ) ? (bool) $assoc_args['overwrite'] : false;
		$status_filter = $assoc_args['status'] ?? 'any';

		if ( ! $filename ) {
			WP_CLI::error( 'Invalid CSV file!' );
		}

		if ( file_exists( $filename ) && ! $overwrite ) {
			WP_CLI::error( 'CSV file already exists!' );
		} elseif ( file_exists( $filename ) && $overwrite ) {
			WP_CLI::warning( 'Overwriting file ' . $filename );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CLI command, WP_Filesystem not appropriate.
		$file_descriptor = fopen( $filename, 'wb' );

		if ( ! $file_descriptor ) {
			WP_CLI::error( 'Invalid CSV filename!' );
		}

		$posts_per_page = 100;
		$paged          = 1;

		// Map filter values to post statuses.
		$post_status = 'any';
		if ( 'enabled' === $status_filter ) {
			$post_status = 'publish';
		} elseif ( 'disabled' === $status_filter ) {
			$post_status = 'draft';
		}

		// Get count for progress bar (exclude trash for 'any').
		if ( 'any' === $post_status ) {
			$counts     = (array) wp_count_posts( PostType::POST_TYPE );
			$post_count = ( $counts['publish'] ?? 0 ) + ( $counts['draft'] ?? 0 );
		} else {
			$counts     = (array) wp_count_posts( PostType::POST_TYPE );
			$post_count = $counts[ $post_status ] ?? 0;
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Exporting ' . number_format( $post_count ) . ' redirects', $post_count );
		$output   = array();

		do {
			$query_status = 'any' === $post_status ? array( 'publish', 'draft' ) : $post_status;

			$posts = get_posts(
				array(
					'posts_per_page'   => $posts_per_page,
					'paged'            => $paged,
					'post_type'        => PostType::POST_TYPE,
					'post_status'      => $query_status,
					'suppress_filters' => 'false',
				)
			);

			foreach ( $posts as $post ) {
				$redirect_from = $post->post_title;
				$redirect_to   = ( $post->post_parent && 0 !== $post->post_parent ) ? $post->post_parent : $post->post_excerpt;
				$status        = 'publish' === $post->post_status ? 'enabled' : 'disabled';
				$output[]      = array( $redirect_from, $redirect_to, $status );
			}
			$progress->tick( $posts_per_page );

			if ( function_exists( 'vip_inmemory_cleanup' ) ) {
				vip_inmemory_cleanup();
			}

			++$paged;
			$posts_count = count( $posts );
		} while ( $posts_count );

		$progress->finish();

		\WP_CLI\Utils\write_csv( $file_descriptor, $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI command, WP_Filesystem not appropriate.
		fclose( $file_descriptor );
	}
}
