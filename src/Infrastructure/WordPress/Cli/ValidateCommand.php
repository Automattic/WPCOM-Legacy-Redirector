<?php
/**
 * Validate redirects CLI command.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Domain\RedirectRepositoryInterface;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use Automattic\LegacyRedirector\Infrastructure\WordPress\PostType;
use WP_CLI;
use WP_CLI_Command;

/**
 * Validate redirects for broken destinations.
 */
final class ValidateCommand extends WP_CLI_Command {

	/**
	 * The redirect repository.
	 *
	 * @var RedirectRepositoryInterface|null
	 */
	private ?RedirectRepositoryInterface $repository;

	/**
	 * Constructor.
	 *
	 * @param RedirectRepositoryInterface|null $repository The redirect repository (optional for batch mode).
	 */
	public function __construct( ?RedirectRepositoryInterface $repository = null ) {
		$this->repository = $repository;
	}

	/**
	 * Validate redirects and find broken destinations.
	 *
	 * Checks for:
	 * - Destinations pointing to deleted or trashed posts
	 * - Destinations pointing to unpublished posts
	 * - Optionally checks if destination URLs return 404
	 *
	 * ## OPTIONS
	 *
	 * [<identifier>]
	 * : Optional source path or redirect ID to validate a single redirect.
	 *
	 * [--by=<field>]
	 * : How to look up the redirect (only used with identifier).
	 * ---
	 * default: source
	 * options:
	 *   - source
	 *   - id
	 * ---
	 *
	 * [--check-urls]
	 * : Also check if URL destinations return 404 (slow, makes HTTP requests).
	 * : Note: Single redirect validation checks URLs by default; use --no-check-urls to skip.
	 *
	 * [--no-check-urls]
	 * : Skip URL checking for single redirect validation (URLs are checked by default in single mode).
	 *
	 * [--status=<status>]
	 * : Only check redirects with this status (batch mode only).
	 * ---
	 * default: enabled
	 * options:
	 *   - any
	 *   - enabled
	 *   - disabled
	 * ---
	 *
	 * [--limit=<number>]
	 * : Maximum number of redirects to check (batch mode only).
	 * ---
	 * default: 1000
	 * ---
	 *
	 * [--fix]
	 * : Automatically disable broken redirects.
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
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Validate a single redirect by source path.
	 *     $ wp wpcom-legacy-redirector validate /old-page
	 *
	 *     # Validate a single redirect by ID.
	 *     $ wp wpcom-legacy-redirector validate 123 --by=id
	 *
	 *     # Find broken redirects (batch mode).
	 *     $ wp wpcom-legacy-redirector validate
	 *
	 *     # Find broken redirects including URL checks.
	 *     $ wp wpcom-legacy-redirector validate --check-urls
	 *
	 *     # Find and disable broken redirects.
	 *     $ wp wpcom-legacy-redirector validate --fix
	 *
	 *     # Check all redirects (enabled and disabled).
	 *     $ wp wpcom-legacy-redirector validate --status=any
	 *
	 *     # Get count of broken redirects.
	 *     $ wp wpcom-legacy-redirector validate --format=count
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		// Single redirect validation mode.
		if ( isset( $args[0] ) ) {
			$this->validate_single( $args[0], $assoc_args );
			return;
		}
		$check_urls = isset( $assoc_args['check-urls'] );
		$status     = $assoc_args['status'] ?? 'enabled';
		$limit      = (int) ( $assoc_args['limit'] ?? 1000 );
		$fix        = isset( $assoc_args['fix'] );
		$format     = $assoc_args['format'] ?? 'table';

		// Map status filter to post status.
		$post_status = 'any';
		if ( 'enabled' === $status ) {
			$post_status = 'publish';
		} elseif ( 'disabled' === $status ) {
			$post_status = 'draft';
		} else {
			$post_status = array( 'publish', 'draft' );
		}

		WP_CLI::line( sprintf( 'Checking up to %d redirects...', $limit ) );
		if ( $check_urls ) {
			WP_CLI::warning( 'URL checking enabled - this may be slow.' );
		}

		// Query redirects.
		$query = new \WP_Query(
			array(
				'post_type'      => PostType::POST_TYPE,
				'post_status'    => $post_status,
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$broken  = array();
		$checked = 0;
		$total   = count( $query->posts );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Validating redirects', $total );

		foreach ( $query->posts as $post ) {
			++$checked;
			$progress->tick();

			$from         = $post->post_title;
			$is_post_dest = $post->post_parent > 0;
			$to           = $is_post_dest ? $post->post_parent : $post->post_excerpt;

			$issue = $this->check_redirect( $post, $check_urls );

			if ( null !== $issue ) {
				$broken[] = array(
					'ID'     => $post->ID,
					'from'   => $from,
					'to'     => $to,
					'type'   => $is_post_dest ? 'post' : 'url',
					'issue'  => $issue,
					'status' => 'publish' === $post->post_status ? 'enabled' : 'disabled',
				);
			}

			// Memory cleanup every 100 items.
			if ( 0 === $checked % 100 ) {
				if ( function_exists( 'stop_the_insanity' ) ) {
					stop_the_insanity();
				}
			}
		}

		$progress->finish();

		// Handle count format.
		if ( 'count' === $format ) {
			WP_CLI::line( (string) count( $broken ) );
			return;
		}

		if ( empty( $broken ) ) {
			WP_CLI::success( sprintf( 'All %d redirects validated - no issues found.', $checked ) );
			return;
		}

		// Display results.
		WP_CLI::warning( sprintf( 'Found %d broken redirect(s) out of %d checked.', count( $broken ), $checked ) );
		WP_CLI::line( '' );

		\WP_CLI\Utils\format_items( $format, $broken, array( 'ID', 'from', 'to', 'type', 'issue', 'status' ) );

		// Fix if requested.
		if ( $fix ) {
			WP_CLI::line( '' );
			$fixed = 0;
			foreach ( $broken as $item ) {
				if ( 'enabled' === $item['status'] ) {
					$result = wp_update_post(
						array(
							'ID'          => $item['ID'],
							'post_status' => 'draft',
						)
					);
					if ( $result && ! is_wp_error( $result ) ) {
						++$fixed;
					}
				}
			}
			WP_CLI::success( sprintf( 'Disabled %d broken redirect(s).', $fixed ) );
		}
	}

	/**
	 * Validate a single redirect by identifier.
	 *
	 * @param string $identifier The source path or redirect ID.
	 * @param array  $assoc_args Key-value associative arguments.
	 */
	private function validate_single( string $identifier, array $assoc_args ): void {
		if ( null === $this->repository ) {
			WP_CLI::error( 'Repository not available for single redirect validation.' );
			return;
		}

		$by  = $assoc_args['by'] ?? 'source';
		$fix = isset( $assoc_args['fix'] );

		// For single redirect, always check URLs (it's just one request).
		// User can explicitly disable with --no-check-urls if needed.
		$check_urls = ! isset( $assoc_args['no-check-urls'] );

		// Find the redirect.
		if ( 'id' === $by ) {
			$redirect = $this->repository->find_by_id( (int) $identifier );
		} else {
			try {
				$source   = SourceUrl::from_string( $identifier );
				$redirect = $this->repository->find_by_source( $source );
			} catch ( \InvalidArgumentException $e ) {
				WP_CLI::error( sprintf( 'Invalid source path: %s', $e->getMessage() ) );
				return;
			}
		}

		if ( null === $redirect ) {
			WP_CLI::error( sprintf( 'Redirect not found: %s', $identifier ) );
			return;
		}

		// Get the underlying post for validation.
		$post = get_post( $redirect->id() );
		if ( null === $post ) {
			WP_CLI::error( sprintf( 'Redirect post not found: %d', $redirect->id() ) );
			return;
		}

		$issue = $this->check_redirect( $post, $check_urls );

		$dest        = $redirect->destination();
		$is_post_id  = $dest->is_post_id();
		$destination = $is_post_id ? $dest->as_post_id()->value() : $dest->as_url()->value();

		if ( null === $issue ) {
			WP_CLI::success(
				sprintf(
					'Redirect %d is valid: %s → %s',
					$redirect->id(),
					$redirect->source()->path(),
					$destination
				)
			);
			return;
		}

		// Has an issue.
		WP_CLI::warning(
			sprintf(
				'Redirect %d has issue: %s',
				$redirect->id(),
				$issue
			)
		);

		WP_CLI::line( sprintf( '  From: %s', $redirect->source()->path() ) );
		WP_CLI::line( sprintf( '  To: %s', $destination ) );
		WP_CLI::line( sprintf( '  Type: %s', $is_post_id ? 'post' : 'url' ) );
		WP_CLI::line( sprintf( '  Status: %s', $redirect->is_active() ? 'enabled' : 'disabled' ) );
		WP_CLI::line( sprintf( '  Issue: %s', $issue ) );

		// Fix if requested.
		if ( $fix && $redirect->is_active() ) {
			$result = wp_update_post(
				array(
					'ID'          => $redirect->id(),
					'post_status' => 'draft',
				)
			);
			if ( $result && ! is_wp_error( $result ) ) {
				WP_CLI::success( 'Redirect disabled.' );
			} else {
				WP_CLI::error( 'Failed to disable redirect.' );
			}
		}
	}

	/**
	 * Check a single redirect for issues.
	 *
	 * @param \WP_Post $post       The redirect post.
	 * @param bool     $check_urls Whether to check URL destinations.
	 * @return string|null The issue description, or null if no issue.
	 */
	private function check_redirect( \WP_Post $post, bool $check_urls ): ?string {
		$is_post_dest = $post->post_parent > 0;

		if ( $is_post_dest ) {
			return $this->check_post_destination( $post->post_parent );
		}

		// URL destination.
		$url = $post->post_excerpt;

		if ( empty( $url ) ) {
			return 'Empty destination';
		}

		// Check if it's a relative path pointing to a post.
		if ( $this->is_relative_path( $url ) ) {
			return $this->check_relative_path( $url, $check_urls );
		}

		// External URL - only check if requested.
		if ( $check_urls ) {
			return $this->check_url( $url );
		}

		return null;
	}

	/**
	 * Check if a post destination is valid.
	 *
	 * @param int $post_id The post ID.
	 * @return string|null The issue, or null if valid.
	 */
	private function check_post_destination( int $post_id ): ?string {
		$post = get_post( $post_id );

		if ( null === $post ) {
			return 'Post deleted';
		}

		if ( 'trash' === $post->post_status ) {
			return 'Post trashed';
		}

		if ( 'publish' !== $post->post_status ) {
			return sprintf( 'Post not published (status: %s)', $post->post_status );
		}

		return null;
	}

	/**
	 * Check if a string is a relative path.
	 *
	 * @param string $url The URL to check.
	 * @return bool True if relative path.
	 */
	private function is_relative_path( string $url ): bool {
		return ! preg_match( '#^https?://#i', $url );
	}

	/**
	 * Check if a relative path destination is valid.
	 *
	 * @param string $path       The relative path.
	 * @param bool   $check_urls Whether to check via HTTP.
	 * @return string|null The issue, or null if valid.
	 */
	private function check_relative_path( string $path, bool $check_urls ): ?string {
		// Try to find a post by path.
		$post = get_page_by_path( ltrim( $path, '/' ), OBJECT, array( 'post', 'page' ) );

		if ( null !== $post ) {
			if ( 'trash' === $post->post_status ) {
				return 'Destination page trashed';
			}
			if ( 'publish' !== $post->post_status ) {
				return sprintf( 'Destination page not published (status: %s)', $post->post_status );
			}
			return null;
		}

		// If URL checking is enabled, verify via HTTP.
		if ( $check_urls ) {
			$full_url = home_url( $path );
			return $this->check_url( $full_url );
		}

		// Can't determine without HTTP check.
		return null;
	}

	/**
	 * Check if a URL returns a successful response.
	 *
	 * @param string $url The URL to check.
	 * @return string|null The issue, or null if valid.
	 */
	private function check_url( string $url ): ?string {
		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 0, // Don't follow redirects.
				'sslverify'   => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return sprintf( 'Request failed: %s', $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 404 === $status_code ) {
			return 'Destination returns 404';
		}

		if ( $status_code >= 500 ) {
			return sprintf( 'Destination returns %d', $status_code );
		}

		return null;
	}
}
