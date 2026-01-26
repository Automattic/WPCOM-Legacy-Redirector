<?php
/**
 * List redirects CLI command.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Infrastructure\WordPress\PostType;
use WP_CLI;
use WP_CLI_Command;

/**
 * List redirects with filtering options.
 */
final class ListCommand extends WP_CLI_Command {

	/**
	 * List redirects.
	 *
	 * ## OPTIONS
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
	 * [--destination-type=<type>]
	 * : Filter by destination type.
	 * ---
	 * default: any
	 * options:
	 *   - any
	 *   - post
	 *   - url
	 * ---
	 *
	 * [--search=<search>]
	 * : Search in source paths.
	 *
	 * [--limit=<number>]
	 * : Maximum number of redirects to show.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--offset=<number>]
	 * : Number of redirects to skip.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--orderby=<field>]
	 * : Field to order by.
	 * ---
	 * default: date
	 * options:
	 *   - date
	 *   - title
	 *   - modified
	 * ---
	 *
	 * [--order=<order>]
	 * : Sort order.
	 * ---
	 * default: DESC
	 * options:
	 *   - ASC
	 *   - DESC
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - ids
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all redirects.
	 *     $ wp wpcom-legacy-redirector list
	 *
	 *     # List disabled redirects.
	 *     $ wp wpcom-legacy-redirector list --status=disabled
	 *
	 *     # List redirects pointing to posts.
	 *     $ wp wpcom-legacy-redirector list --destination-type=post
	 *
	 *     # Search for redirects containing "blog".
	 *     $ wp wpcom-legacy-redirector list --search=blog
	 *
	 *     # Get count of all redirects.
	 *     $ wp wpcom-legacy-redirector list --format=count
	 *
	 *     # Export first 500 redirect IDs.
	 *     $ wp wpcom-legacy-redirector list --limit=500 --format=ids
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$status           = $assoc_args['status'] ?? 'any';
		$destination_type = $assoc_args['destination-type'] ?? 'any';
		$search           = $assoc_args['search'] ?? '';
		$limit            = (int) ( $assoc_args['limit'] ?? 100 );
		$offset           = (int) ( $assoc_args['offset'] ?? 0 );
		$orderby          = $assoc_args['orderby'] ?? 'date';
		$order            = strtoupper( $assoc_args['order'] ?? 'DESC' );
		$format           = $assoc_args['format'] ?? 'table';

		// Map status filter to post status.
		$post_status = 'any';
		if ( 'enabled' === $status ) {
			$post_status = 'publish';
		} elseif ( 'disabled' === $status ) {
			$post_status = 'draft';
		} else {
			$post_status = array( 'publish', 'draft' );
		}

		// Build query args.
		$query_args = array(
			'post_type'      => PostType::POST_TYPE,
			'post_status'    => $post_status,
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		// Add search if provided.
		if ( ! empty( $search ) ) {
			$query_args['s'] = $search;
		}

		// Add destination type filter via meta query.
		if ( 'post' === $destination_type ) {
			$query_args['post_parent__not_in'] = array( 0 );
		} elseif ( 'url' === $destination_type ) {
			$query_args['post_parent'] = 0;
		}

		$query = new \WP_Query( $query_args );
		$posts = $query->posts;

		// Handle count format.
		if ( 'count' === $format ) {
			WP_CLI::line( (string) $query->found_posts );
			return;
		}

		// Handle ids format.
		if ( 'ids' === $format ) {
			$ids = wp_list_pluck( $posts, 'ID' );
			WP_CLI::line( implode( ' ', $ids ) );
			return;
		}

		if ( empty( $posts ) ) {
			WP_CLI::warning( 'No redirects found.' );
			return;
		}

		// Build output data.
		$items = array();
		foreach ( $posts as $post ) {
			$to        = $post->post_parent > 0 ? $post->post_parent : $post->post_excerpt;
			$dest_type = $post->post_parent > 0 ? 'post' : 'url';

			$items[] = array(
				'ID'     => $post->ID,
				'from'   => $post->post_title,
				'to'     => $to,
				'type'   => $dest_type,
				'status' => 'publish' === $post->post_status ? 'enabled' : 'disabled',
			);
		}

		\WP_CLI\Utils\format_items( $format, $items, array( 'ID', 'from', 'to', 'type', 'status' ) );

		// Show pagination info for table format.
		if ( 'table' === $format && $query->found_posts > count( $posts ) ) {
			WP_CLI::line( '' );
			WP_CLI::line(
				sprintf(
					'Showing %d-%d of %d redirects. Use --offset and --limit for pagination.',
					$offset + 1,
					$offset + count( $posts ),
					$query->found_posts
				)
			);
		}
	}
}
