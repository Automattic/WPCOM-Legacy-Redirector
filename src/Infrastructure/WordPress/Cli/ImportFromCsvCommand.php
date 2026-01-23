<?php
/**
 * Import from CSV CLI command.
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
 * Bulk import, update, or delete redirects from a CSV file.
 */
final class ImportFromCsvCommand extends WP_CLI_Command {

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
	 * Bulk import, update, or delete redirects from a CSV file.
	 *
	 * By default, imports new redirects. Use --update to update existing redirects,
	 * or --delete to delete redirects matching the sources in the CSV.
	 *
	 * CSV should match the following structure:
	 *   redirect_from_path,(redirect_to_post_id|redirect_to_path|redirect_to_url)
	 *
	 * For --delete mode, only the first column (redirect_from_path) is required.
	 *
	 * ## OPTIONS
	 *
	 * --csv=<path-to-csv>
	 * : Path to CSV file.
	 *
	 * [--update]
	 * : Update existing redirects instead of creating new ones.
	 *   If a redirect doesn't exist, it will be created.
	 *
	 * [--delete]
	 * : Delete redirects matching the source paths in the CSV.
	 *   Only the first column is used in this mode.
	 *
	 * [--skip-validation]
	 * : If set, validation of from and to values will be skipped. Defaults to false.
	 *
	 * [--dry-run]
	 * : Preview what would happen without making changes.
	 *
	 * [--verbose]
	 * : If set, more verbose logging will be output. Defaults to false.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Import new redirects from a CSV file.
	 *     $ wp wpcom-legacy-redirector import-from-csv --csv=redirects.csv
	 *
	 *     # Update existing redirects (or create if not found).
	 *     $ wp wpcom-legacy-redirector import-from-csv --csv=redirects.csv --update
	 *
	 *     # Delete redirects matching sources in CSV.
	 *     $ wp wpcom-legacy-redirector import-from-csv --csv=redirects.csv --delete
	 *
	 *     # Preview deletions without making changes.
	 *     $ wp wpcom-legacy-redirector import-from-csv --csv=redirects.csv --delete --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		$format   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$csv      = trim( \WP_CLI\Utils\get_flag_value( $assoc_args, 'csv', '' ) );
		$verbose  = isset( $assoc_args['verbose'] );
		$validate = ! isset( $assoc_args['skip-validation'] );
		$dry_run  = isset( $assoc_args['dry-run'] );
		$update   = isset( $assoc_args['update'] );
		$delete   = isset( $assoc_args['delete'] );

		if ( $update && $delete ) {
			WP_CLI::error( 'Cannot use --update and --delete together.' );
		}

		if ( empty( $csv ) || ! file_exists( $csv ) ) {
			WP_CLI::error( "Invalid 'csv' file" );
		}

		$mode = 'import';
		if ( $update ) {
			$mode = 'update';
		} elseif ( $delete ) {
			$mode = 'delete';
		}

		if ( $dry_run ) {
			WP_CLI::warning( 'Dry run mode - no changes will be made.' );
		}

		WP_CLI::line( sprintf( 'Processing CSV in %s mode...', $mode ) );

		// phpcs:ignore WordPress.PHP.IniSet.Risky -- Required for CSV parsing with different line endings.
		ini_set( 'auto_detect_line_endings', true );

		$row     = 0;
		$results = array();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CLI command, WP_Filesystem not appropriate.
		$handle = fopen( $csv, 'r' );

		if ( false === $handle ) {
			WP_CLI::error( 'Could not open CSV file.' );
		}

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard CSV reading pattern.
		while ( ( $data = fgetcsv( $handle, 2000, ',' ) ) !== false ) {
			++$row;
			$redirect_from = $data[0] ?? '';
			$redirect_to   = $data[1] ?? '';

			if ( empty( $redirect_from ) ) {
				continue;
			}

			if ( $verbose ) {
				WP_CLI::line( "Processing row $row: $redirect_from" );
			} elseif ( 0 === $row % 100 ) {
				WP_CLI::line( "Processing row $row" );
			}

			$result = $this->process_row( $redirect_from, $redirect_to, $mode, $validate, $dry_run );
			if ( null !== $result ) {
				$results[] = $result;
			}

			if ( 0 === $row % 100 ) {
				if ( function_exists( 'stop_the_insanity' ) ) {
					stop_the_insanity();
				}
				sleep( 1 );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI command, WP_Filesystem not appropriate.
		fclose( $handle );

		$this->display_results( $results, $mode, $format, $dry_run );
	}

	/**
	 * Process a single CSV row.
	 *
	 * @param string $redirect_from The source path.
	 * @param string $redirect_to   The destination.
	 * @param string $mode          The operation mode (import, update, delete).
	 * @param bool   $validate      Whether to validate.
	 * @param bool   $dry_run       Whether this is a dry run.
	 * @return array|null Result array or null if nothing to report.
	 */
	private function process_row( string $redirect_from, string $redirect_to, string $mode, bool $validate, bool $dry_run ): ?array {
		try {
			$source = SourceUrl::from_string( $redirect_from );
		} catch ( \InvalidArgumentException $e ) {
			return array(
				'source'  => $redirect_from,
				'dest'    => $redirect_to,
				'action'  => 'error',
				'message' => 'Invalid source: ' . $e->getMessage(),
			);
		}

		if ( 'delete' === $mode ) {
			return $this->handle_delete( $source, $redirect_from, $dry_run );
		}

		// For import/update, we need a valid destination.
		if ( empty( $redirect_to ) ) {
			return array(
				'source'  => $redirect_from,
				'dest'    => '',
				'action'  => 'error',
				'message' => 'Missing destination',
			);
		}

		try {
			$to_value    = is_numeric( $redirect_to ) ? (int) $redirect_to : $redirect_to;
			$destination = Destination::from_mixed( $to_value );
		} catch ( \InvalidArgumentException $e ) {
			return array(
				'source'  => $redirect_from,
				'dest'    => $redirect_to,
				'action'  => 'error',
				'message' => 'Invalid destination: ' . $e->getMessage(),
			);
		}

		if ( 'update' === $mode ) {
			return $this->handle_update( $source, $destination, $redirect_from, $redirect_to, $validate, $dry_run );
		}

		return $this->handle_import( $source, $destination, $redirect_from, $redirect_to, $validate, $dry_run );
	}

	/**
	 * Handle delete mode.
	 *
	 * @param SourceUrl $source        The source URL.
	 * @param string    $redirect_from The original source string.
	 * @param bool      $dry_run       Whether this is a dry run.
	 * @return array Result array.
	 */
	private function handle_delete( SourceUrl $source, string $redirect_from, bool $dry_run ): array {
		if ( $dry_run ) {
			return array(
				'source'  => $redirect_from,
				'dest'    => '-',
				'action'  => 'would delete',
				'message' => 'Would be deleted',
			);
		}

		$deleted = $this->manager->delete_by_source( $source );

		return array(
			'source'  => $redirect_from,
			'dest'    => '-',
			'action'  => $deleted ? 'deleted' : 'not found',
			'message' => $deleted ? 'Deleted' : 'Redirect not found',
		);
	}

	/**
	 * Handle update mode.
	 *
	 * @param SourceUrl   $source        The source URL.
	 * @param Destination $destination   The destination.
	 * @param string      $redirect_from The original source string.
	 * @param string      $redirect_to   The original destination string.
	 * @param bool        $validate      Whether to validate.
	 * @param bool        $dry_run       Whether this is a dry run.
	 * @return array Result array.
	 */
	private function handle_update( SourceUrl $source, Destination $destination, string $redirect_from, string $redirect_to, bool $validate, bool $dry_run ): array {
		if ( $dry_run ) {
			return array(
				'source'  => $redirect_from,
				'dest'    => $redirect_to,
				'action'  => 'would update/create',
				'message' => 'Would be updated or created',
			);
		}

		// Try to update first.
		$updated = $this->manager->update_by_source( $source, $destination );

		if ( $updated ) {
			return array(
				'source'  => $redirect_from,
				'dest'    => $redirect_to,
				'action'  => 'updated',
				'message' => 'Updated existing redirect',
			);
		}

		// If not found, create new.
		$result = $this->manager->create_redirect( $source, $destination, $validate );

		if ( $result->is_success() ) {
			return array(
				'source'  => $redirect_from,
				'dest'    => $redirect_to,
				'action'  => 'created',
				'message' => 'Created new redirect',
			);
		}

		return array(
			'source'  => $redirect_from,
			'dest'    => $redirect_to,
			'action'  => 'error',
			'message' => $result->error_message() ?? 'Could not create redirect',
		);
	}

	/**
	 * Handle import mode.
	 *
	 * @param SourceUrl   $source        The source URL.
	 * @param Destination $destination   The destination.
	 * @param string      $redirect_from The original source string.
	 * @param string      $redirect_to   The original destination string.
	 * @param bool        $validate      Whether to validate.
	 * @param bool        $dry_run       Whether this is a dry run.
	 * @return array|null Result array or null for success in non-verbose mode.
	 */
	private function handle_import( SourceUrl $source, Destination $destination, string $redirect_from, string $redirect_to, bool $validate, bool $dry_run ): ?array {
		if ( $dry_run ) {
			return array(
				'source'  => $redirect_from,
				'dest'    => $redirect_to,
				'action'  => 'would create',
				'message' => 'Would be created',
			);
		}

		$result = $this->manager->create_redirect( $source, $destination, $validate );

		if ( $result->is_error() ) {
			return array(
				'source'  => $redirect_from,
				'dest'    => $redirect_to,
				'action'  => 'error',
				'message' => $result->error_message() ?? 'Could not insert redirect',
			);
		}

		return array(
			'source'  => $redirect_from,
			'dest'    => $redirect_to,
			'action'  => 'created',
			'message' => 'Successfully imported',
		);
	}

	/**
	 * Display the results summary.
	 *
	 * @param array  $results The results array.
	 * @param string $mode    The operation mode.
	 * @param string $format  The output format.
	 * @param bool   $dry_run Whether this was a dry run.
	 */
	private function display_results( array $results, string $mode, string $format, bool $dry_run ): void {
		if ( empty( $results ) ) {
			WP_CLI::warning( 'No rows processed from CSV.' );
			return;
		}

		// Count results by action.
		$counts = array();
		foreach ( $results as $result ) {
			$action = $result['action'];
			if ( ! isset( $counts[ $action ] ) ) {
				$counts[ $action ] = 0;
			}
			++$counts[ $action ];
		}

		// Display summary.
		WP_CLI::line( '' );
		WP_CLI::line( 'Summary:' );
		foreach ( $counts as $action => $count ) {
			WP_CLI::line( sprintf( '  %s: %d', ucfirst( $action ), $count ) );
		}

		// Show errors or full results if requested.
		$errors = array_filter( $results, fn( $r ) => 'error' === $r['action'] );
		if ( ! empty( $errors ) ) {
			WP_CLI::line( '' );
			WP_CLI::warning( 'Errors:' );
			\WP_CLI\Utils\format_items( $format, $errors, array( 'source', 'dest', 'message' ) );
		} elseif ( $dry_run ) {
			WP_CLI::line( '' );
			\WP_CLI\Utils\format_items( $format, $results, array( 'source', 'dest', 'action', 'message' ) );
		} else {
			WP_CLI::success( sprintf( 'Processed %d redirects.', count( $results ) ) );
		}
	}
}
