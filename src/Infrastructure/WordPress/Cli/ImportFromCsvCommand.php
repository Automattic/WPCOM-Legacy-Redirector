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
 * Bulk import redirects from a CSV file.
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
	 * Bulk import redirects from a CSV file.
	 *
	 * CSV should match the following structure:
	 *   redirect_from_path,(redirect_to_post_id|redirect_to_path|redirect_to_url)
	 *
	 * ## OPTIONS
	 *
	 * --csv=<path-to-csv>
	 * : Path to CSV file.
	 *
	 * [--skip-validation]
	 * :  If set, validation of from and to values will be skipped. Defaults to false.
	 *
	 * [--verbose]
	 * : If set, more verbose logging will be output. Defaults to false.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: csv
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Import redirects from a redirects.csv file.
	 *     $ wp wpcom-legacy-redirector import-from-csv --csv=path/to/redirects.csv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		$format   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$csv      = trim( \WP_CLI\Utils\get_flag_value( $assoc_args, 'csv' ) );
		$verbose  = isset( $assoc_args['verbose'] );
		$validate = ! isset( $assoc_args['skip-validation'] );
		$notices  = array();

		if ( empty( $csv ) || ! file_exists( $csv ) ) {
			WP_CLI::error( "Invalid 'csv' file" );
		}

		if ( ! $verbose ) {
			WP_CLI::line( 'Processing...' );
		}

		// phpcs:ignore WordPress.PHP.IniSet.Risky -- Required for CSV parsing with different line endings.
		ini_set( 'auto_detect_line_endings', true );

		$row = 0;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CLI command, WP_Filesystem not appropriate.
		$handle = fopen( $csv, 'r' );

		if ( false === $handle ) {
			WP_CLI::error( 'Could not open CSV file.' );
		}

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard CSV reading pattern.
		while ( ( $data = fgetcsv( $handle, 2000, ',' ) ) !== false ) {
			++$row;
			$redirect_from = $data[0];
			$redirect_to   = $data[1];

			if ( $verbose ) {
				WP_CLI::line( "Adding (CSV) redirect for {$redirect_from} to {$redirect_to}" );
				WP_CLI::line( "-- at $row" );
			} elseif ( 0 === $row % 100 ) {
				WP_CLI::line( "Processing row $row" );
			}

			try {
				$source      = SourceUrl::from_string( $redirect_from );
				$to_value    = is_numeric( $redirect_to ) ? (int) $redirect_to : $redirect_to;
				$destination = Destination::from_mixed( $to_value );
			} catch ( \InvalidArgumentException $e ) {
				$notices[] = array(
					'redirect_from' => $redirect_from,
					'redirect_to'   => $redirect_to,
					'message'       => $e->getMessage(),
				);
				continue;
			}

			$result = $this->manager->create_redirect( $source, $destination, $validate );

			if ( $result->is_error() ) {
				$notices[] = array(
					'redirect_from' => $redirect_from,
					'redirect_to'   => $redirect_to,
					'message'       => $result->error_message() ?? 'Could not insert redirect',
				);
			} elseif ( $verbose ) {
				$notices[] = array(
					'redirect_from' => $redirect_from,
					'redirect_to'   => $redirect_to,
					'message'       => 'Successfully imported',
				);
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

		if ( count( $notices ) > 0 ) {
			\WP_CLI\Utils\format_items( $format, $notices, array( 'redirect_from', 'redirect_to', 'message' ) );
		} else {
			WP_CLI::log( WP_CLI::colorize( '%GAll of your redirects have been imported. Nice work!%n ' ) );
		}
	}
}
