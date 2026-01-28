<?php
/**
 * Validate redirects CLI command.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Application\RedirectValidator;
use Automattic\LegacyRedirector\Domain\RedirectCriteria;
use Automattic\LegacyRedirector\Domain\RedirectQueryRepositoryInterface;
use Automattic\LegacyRedirector\Domain\RedirectRepositoryInterface;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use Automattic\LegacyRedirector\Domain\ValidationIssue;
use WP_CLI;
use WP_CLI_Command;

/**
 * Validate redirects for broken destinations.
 */
final class ValidateCommand extends WP_CLI_Command {

	/**
	 * The redirect repository (for single redirect lookups).
	 *
	 * @var RedirectRepositoryInterface|null
	 */
	private ?RedirectRepositoryInterface $repository;

	/**
	 * The query repository (for batch listing).
	 *
	 * @var RedirectQueryRepositoryInterface|null
	 */
	private ?RedirectQueryRepositoryInterface $query_repository;

	/**
	 * The redirect validator.
	 *
	 * @var RedirectValidator|null
	 */
	private ?RedirectValidator $validator;

	/**
	 * Constructor.
	 *
	 * @param RedirectRepositoryInterface|null      $repository       The redirect repository (for single lookups).
	 * @param RedirectQueryRepositoryInterface|null $query_repository The query repository (for batch operations).
	 * @param RedirectValidator|null                $validator        The redirect validator.
	 */
	public function __construct(
		?RedirectRepositoryInterface $repository = null,
		?RedirectQueryRepositoryInterface $query_repository = null,
		?RedirectValidator $validator = null
	) {
		$this->repository       = $repository;
		$this->query_repository = $query_repository;
		$this->validator        = $validator;
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
	 * : Also check if URL destinations return 404 (slow, makes HTTP requests). Single redirect mode checks URLs by default.
	 *
	 * [--no-check-urls]
	 * : Skip URL checking for single redirect validation.
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

		$this->validate_batch_mode( $assoc_args );
	}

	/**
	 * Validate a single redirect by identifier.
	 *
	 * @param string $identifier The source path or redirect ID.
	 * @param array  $assoc_args Key-value associative arguments.
	 */
	private function validate_single( string $identifier, array $assoc_args ): void {
		if ( null === $this->repository || null === $this->validator ) {
			WP_CLI::error( 'Repository or validator not available for single redirect validation.' );
			return;
		}

		$by  = $assoc_args['by'] ?? 'source';
		$fix = isset( $assoc_args['fix'] );

		// For single redirect, always check URLs (it's just one request).
		// User can explicitly disable with --no-check-urls if needed.
		$check_urls = ! isset( $assoc_args['no-check-urls'] );

		// Find the redirect (including disabled ones).
		if ( 'id' === $by ) {
			$redirect = $this->repository->find_by_id( (int) $identifier );
		} else {
			try {
				$source      = SourceUrl::from_string( $identifier );
				$redirect_id = $this->repository->get_id_by_source( $source );
				$redirect    = $redirect_id > 0 ? $this->repository->find_by_id( $redirect_id ) : null;
			} catch ( \InvalidArgumentException $e ) {
				WP_CLI::error( sprintf( 'Invalid source path: %s', $e->getMessage() ) );
				return;
			}
		}

		if ( null === $redirect ) {
			WP_CLI::error( sprintf( 'Redirect not found: %s', $identifier ) );
			return;
		}

		// Validate the redirect.
		$issue = $this->validator->validate_redirect_destination( $redirect, $check_urls );

		$dest        = $redirect->destination();
		$is_post_id  = $dest->is_post_id();
		$destination = $is_post_id ? $dest->as_post_id()->value() : $dest->as_url()->value();

		if ( null === $issue ) {
			WP_CLI::success(
				sprintf(
					'Redirect %d is valid: %s -> %s',
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
				$issue->label()
			)
		);

		WP_CLI::line( sprintf( '  From: %s', $redirect->source()->path() ) );
		WP_CLI::line( sprintf( '  To: %s', $destination ) );
		WP_CLI::line( sprintf( '  Type: %s', $is_post_id ? 'post' : 'url' ) );
		WP_CLI::line( sprintf( '  Status: %s', $redirect->is_active() ? 'enabled' : 'disabled' ) );
		WP_CLI::line( sprintf( '  Issue: %s', $issue->description() ) );

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
	 * Validate redirects in batch mode.
	 *
	 * @param array $assoc_args Key-value associative arguments.
	 */
	private function validate_batch_mode( array $assoc_args ): void {
		if ( null === $this->query_repository || null === $this->validator ) {
			WP_CLI::error( 'Query repository or validator not available for batch validation.' );
			return;
		}

		$check_urls = isset( $assoc_args['check-urls'] );
		$status     = $assoc_args['status'] ?? 'enabled';
		$limit      = (int) ( $assoc_args['limit'] ?? 1000 );
		$fix        = isset( $assoc_args['fix'] );
		$format     = $assoc_args['format'] ?? 'table';

		// Build criteria.
		$criteria = new RedirectCriteria(
			'any' === $status ? null : $status,
			null, // destination_type.
			null, // search.
			'date',
			'DESC',
			$limit,
			0
		);

		WP_CLI::line( sprintf( 'Checking up to %d redirects...', $limit ) );
		if ( $check_urls ) {
			WP_CLI::warning( 'URL checking enabled - this may be slow.' );
		}

		// Fetch redirects.
		$redirects = $this->query_repository->find_matching( $criteria );
		$total     = count( $redirects );

		$start_time = microtime( true );
		$progress   = \WP_CLI\Utils\make_progress_bar( 'Validating redirects', $total );

		// Validate with progress tracking.
		$issues = $this->validator->validate_batch(
			$redirects,
			$check_urls,
			function () use ( $progress ): void {
				$progress->tick();
			}
		);

		$progress->finish();

		// Calculate elapsed time.
		$elapsed     = microtime( true ) - $start_time;
		$elapsed_str = $this->format_elapsed_time( $elapsed );
		$rate        = $total > 0 && $elapsed > 0 ? round( $total / $elapsed, 1 ) : 0;

		// Handle count format.
		if ( 'count' === $format ) {
			WP_CLI::line( (string) count( $issues ) );
			return;
		}

		// Show progress summary.
		WP_CLI::line( sprintf( 'Checked %d redirects in %s (%.1f/sec)', $total, $elapsed_str, $rate ) );

		if ( empty( $issues ) ) {
			WP_CLI::success( 'No issues found.' );
			return;
		}

		// Display results.
		WP_CLI::warning( sprintf( 'Found %d broken redirect(s).', count( $issues ) ) );
		WP_CLI::line( '' );

		// Convert issues to array format for display.
		$items = array_map(
			fn( ValidationIssue $issue ) => $issue->to_array(),
			$issues
		);

		\WP_CLI\Utils\format_items( $format, $items, array( 'ID', 'from', 'to', 'type', 'issue', 'status' ) );

		// Fix if requested.
		if ( $fix ) {
			WP_CLI::line( '' );
			$fixed = 0;
			foreach ( $issues as $issue ) {
				if ( $issue->redirect()->is_active() ) {
					$result = wp_update_post(
						array(
							'ID'          => $issue->redirect_id(),
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
	 * Format elapsed time into a human-readable string.
	 *
	 * @param float $seconds The elapsed time in seconds.
	 * @return string Formatted time string.
	 */
	private function format_elapsed_time( float $seconds ): string {
		if ( $seconds < 60 ) {
			return sprintf( '%.1fs', $seconds );
		}

		$minutes = floor( $seconds / 60 );
		$secs    = $seconds - ( $minutes * 60 );

		if ( $minutes < 60 ) {
			return sprintf( '%dm %.1fs', $minutes, $secs );
		}

		$hours = floor( $minutes / 60 );
		$mins  = $minutes - ( $hours * 60 );

		return sprintf( '%dh %dm %.1fs', $hours, $mins, $secs );
	}
}
