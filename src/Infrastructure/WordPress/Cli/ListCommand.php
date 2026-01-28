<?php
/**
 * List redirects CLI command.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Domain\RedirectCriteria;
use Automattic\LegacyRedirector\Domain\RedirectQueryRepositoryInterface;
use WP_CLI;
use WP_CLI_Command;

/**
 * List redirects with filtering options.
 */
final class ListCommand extends WP_CLI_Command {

	/**
	 * The query repository.
	 *
	 * @var RedirectQueryRepositoryInterface
	 */
	private RedirectQueryRepositoryInterface $query_repository;

	/**
	 * Constructor.
	 *
	 * @param RedirectQueryRepositoryInterface $query_repository The query repository.
	 */
	public function __construct( RedirectQueryRepositoryInterface $query_repository ) {
		$this->query_repository = $query_repository;
	}

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
		$format   = $assoc_args['format'] ?? 'table';
		$criteria = RedirectCriteria::from_args( $assoc_args );

		// Handle count format - only needs count, not full results.
		if ( 'count' === $format ) {
			$count = $this->query_repository->count_matching( $criteria );
			WP_CLI::line( (string) $count );
			return;
		}

		// Fetch redirects matching criteria.
		$redirects   = $this->query_repository->find_matching( $criteria );
		$total_count = $this->query_repository->count_matching( $criteria );

		// Handle ids format.
		if ( 'ids' === $format ) {
			$ids = array_map(
				fn( $redirect ) => $redirect->id(),
				$redirects
			);
			WP_CLI::line( implode( ' ', $ids ) );
			return;
		}

		if ( empty( $redirects ) ) {
			WP_CLI::warning( 'No redirects found.' );
			return;
		}

		// Build output data.
		$items = array_map(
			fn( $redirect ) => $this->format_redirect_for_output( $redirect ),
			$redirects
		);

		\WP_CLI\Utils\format_items( $format, $items, array( 'ID', 'from', 'to', 'type', 'status' ) );

		// Show pagination info for table format.
		if ( 'table' === $format && $total_count > count( $redirects ) ) {
			WP_CLI::line( '' );
			WP_CLI::line(
				sprintf(
					'Showing %d-%d of %d redirects. Use --offset and --limit for pagination.',
					$criteria->offset() + 1,
					$criteria->offset() + count( $redirects ),
					$total_count
				)
			);
		}
	}

	/**
	 * Format a redirect for CLI output.
	 *
	 * @param \Automattic\LegacyRedirector\Domain\Redirect $redirect The redirect.
	 * @return array{ID: int|null, from: string, to: string|int, type: string, status: string}
	 */
	private function format_redirect_for_output( $redirect ): array {
		$dest        = $redirect->destination();
		$is_post_id  = $dest->is_post_id();
		$destination = $is_post_id ? $dest->as_post_id()->value() : $dest->as_url()->value();

		return array(
			'ID'     => $redirect->id(),
			'from'   => $redirect->source()->path(),
			'to'     => $destination,
			'type'   => $is_post_id ? 'post' : 'url',
			'status' => $redirect->is_active() ? 'enabled' : 'disabled',
		);
	}
}
