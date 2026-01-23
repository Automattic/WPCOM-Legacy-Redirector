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
	 * Export non-trashed redirects to a CSV file.
	 *
	 * Matches the following structure:
	 *    redirect_from_path,(redirect_to_post_id|redirect_to_path|redirect_to_url)
	 *
	 * ## OPTIONS
	 *
	 * --csv=<path-to-csv>
	 * : Path to CSV.
	 *
	 * [--overwrite]
	 * : Whether to overwrite an existing file. Defaults to false.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export redirects to a redirects.csv file.
	 *     $ wp wpcom-legacy-redirector export-to-csv --csv=path/to/redirects.csv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$filename  = $assoc_args['csv'] ?? false;
		$overwrite = isset( $assoc_args['overwrite'] ) ? (bool) $assoc_args['overwrite'] : false;

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
		$post_count     = array_sum( (array) wp_count_posts( PostType::POST_TYPE ) );
		$progress       = \WP_CLI\Utils\make_progress_bar( 'Exporting ' . number_format( $post_count ) . ' redirects', $post_count );
		$output         = array();

		do {
			$posts = get_posts(
				array(
					'posts_per_page'   => $posts_per_page,
					'paged'            => $paged,
					'post_type'        => PostType::POST_TYPE,
					'post_status'      => 'any',
					'suppress_filters' => 'false',
				)
			);

			foreach ( $posts as $post ) {
				$redirect_from = $post->post_title;
				$redirect_to   = ( $post->post_parent && 0 !== $post->post_parent ) ? $post->post_parent : $post->post_excerpt;
				$output[]      = array( $redirect_from, $redirect_to );
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
