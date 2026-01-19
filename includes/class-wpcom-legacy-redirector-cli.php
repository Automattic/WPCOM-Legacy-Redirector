<?php
/**
 * WPCOM_Legacy_Redirector_CLI class
 *
 * @package Automattic\LegacyRedirector
 */

use Automattic\LegacyRedirector\Post_Type;

/**
 * Manage redirects added via the WPCOM Legacy Redirector plugin.
 */
class WPCOM_Legacy_Redirector_CLI extends WP_CLI_Command {
	/**
	 * Find domains redirected to, useful to populate the allowed_redirect_hosts filter.
	 *
	 * ## EXAMPLES
	 *
	 *     # Get a list of the domains used as redirect destinations
	 *     $ wp wpcom-legacy-redirector find-domains
	 *     Finding domains  100% [========]
	 *     Found 2 unique outbound domains.
	 *     example.com
	 *     example.org
	 *
	 * @subcommand find-domains
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function find_domains( $args, $assoc_args ) {
		global $wpdb;

		$posts_per_page = 500;
		$paged          = 0;

		$domains = array();

		$total_redirects = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( ID ) FROM $wpdb->posts WHERE post_type = %s AND post_excerpt LIKE %s",
				Post_Type::POST_TYPE,
				'http%'
			)
		);

		$progress = \WP_CLI\Utils\make_progress_bar( 'Finding domains', (int) $total_redirects );
		do {
			$redirect_urls = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_excerpt FROM $wpdb->posts WHERE post_type = %s AND post_excerpt LIKE %s ORDER BY ID ASC LIMIT %d, %d",
					Post_Type::POST_TYPE,
					'http%',
					( $paged * $posts_per_page ),
					$posts_per_page
				)
			);

			foreach ( $redirect_urls as $redirect_url ) {
				$progress->tick();
				if ( ! empty( $redirect_url ) ) {
					$redirect_host = wp_parse_url( $redirect_url, PHP_URL_HOST );
					if ( $redirect_host ) {
						$domains[] = $redirect_host;
					}
				}
			}

			// Pause.
			sleep( 1 );
			++$paged;
			$redirect_urls_count = count( $redirect_urls );
		} while ( $redirect_urls_count );

		$progress->finish();

		$domains       = array_unique( $domains );
		$domains_count = count( $domains );

		/* translators: %s = count of the domains */
		$translatable_text = _n( 'Found %s unique outbound domain.', 'Found %s unique outbound domains.', $domains_count, 'wpcom-legacy-redirector' );

		WP_CLI::line( sprintf( $translatable_text, number_format( $domains_count ) ) );

		foreach ( $domains as $domain ) {
			WP_CLI::line( $domain );
		}
	}

	/**
	 * Insert a single redirect.
	 *
	 * ## OPTIONS
	 *
	 * <from_url>
	 * : The path to redirect from.
	 *
	 * <to_url>
	 * : The redirect destination. Can be a full URL, or an integer post ID.
	 *
	 * ## EXAMPLES
	 *
	 *     # Insert redirect from /foo (must not exist) to /bar.
	 *     $ wp wpcom-legacy-redirector insert-redirect /foo /bar
	 *     Success: Inserted /foo -> /bar
	 *
	 *     # Insert redirect from /bar to post ID 5.
	 *     $ wp wpcom-legacy-redirector insert-redirect bar 5
	 *
	 * @subcommand insert-redirect
	 * @synopsis <from_url> <to_url>
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function insert_redirect( $args, $assoc_args ) {
		$from_url = esc_url_raw( $args[0] );

		if ( is_numeric( $args[1] ) ) {
			$to_url = absint( $args[1] );
		} else {
			$to_url = esc_url_raw( $args[1] );
		}

		$inserted = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url );

		if ( ! $inserted || is_wp_error( $inserted ) ) {
			$error_text = '';
			if ( is_wp_error( $inserted ) ) {
				$error_text = $inserted->get_error_message();
			}
			WP_CLI::error( sprintf( "Couldn't insert %s -> %s (%s)", $from_url, $to_url, $error_text ) );
		}

		WP_CLI::success( sprintf( 'Inserted %s -> %s', $from_url, $to_url ) );
	}

	/**
	 * Bulk import redirects from URLs stored as meta values for posts.
	 *
	 * ## OPTIONS
	 *
	 * --meta-key=<name-of-meta-key>
	 * : Name of the meta key to import from. The meta value contains the From value. The To is the post ID that the meta key is for.
	 *
	 * [--start=<start-offset>]
	 * : Starting offset. Defaults to 0.
	 *
	 * [--end=<end-offset>]
	 * : Ending offset. Defaults to 99999999.
	 *
	 * [--skip_dupes=<skip-dupes>]
	 * : If set to true, then redirects for a From URL with an existing redirect will be skipped. Defaults to false.
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
	 * [--dry_run]
	 * : If set, redirects are not imported. Defaults to false.
	 *
	 * [--verbose]
	 * : Display notices for successful imports and duplicates (if skip_dupes is used). Defaults to false.
	 *
	 * ## EXAMPLES
	 *
	 *     # Bulk import from a my-redirect meta key.
	 *     $ wp wpcom-legacy-redirector import-from-meta --meta-key=my-redirect
	 *     ---Live Run---
	 *     Importing 143 redirects
	 *     All of your redirects have been imported. Nice work!
	 *
	 * @subcommand import-from-meta
	 * @synopsis --meta_key=<name-of-meta-key> [--start=<start-offset>] [--end=<end-offset>] [--skip_dupes=<skip-dupes>] [--format=<format>] [--dry_run] [--verbose]
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function import_from_meta( $args, $assoc_args ) {
		define( 'WP_IMPORTING', true );

		global $wpdb;
		$offset     = isset( $assoc_args['start'] ) ? intval( $assoc_args['start'] ) : 0;
		$end_offset = isset( $assoc_args['end'] ) ? intval( $assoc_args['end'] ) : 99999999;

		$meta_key   = isset( $assoc_args['meta_key'] ) ? sanitize_key( $assoc_args['meta_key'] ) : '';
		$skip_dupes = isset( $assoc_args['skip_dupes'] ) ? (bool) intval( $assoc_args['skip_dupes'] ) : false;
		$format     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$dry_run    = isset( $assoc_args['dry_run'] ) ? true : false;
		$verbose    = isset( $assoc_args['verbose'] ) ? true : false;
		$notices    = array();

		if ( true === $dry_run ) {
			WP_CLI::line( '---Dry Run---' );
		} else {
			WP_CLI::line( '---Live Run---' );
		}

		$total_redirects = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( post_id ) FROM $wpdb->postmeta WHERE meta_key = %s",
				$meta_key
			)
		);

		if ( 0 === absint( $total_redirects ) ) {
			WP_CLI::error( sprintf( 'No redirects found for meta_key: %s', $meta_key ) );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( sprintf( 'Importing %s redirects', number_format( $total_redirects ) ), $total_redirects );

		do {
			$redirects = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = %s ORDER BY post_id ASC LIMIT %d, 1000", $meta_key, $offset ) );
			$i         = 0;
			$total     = count( $redirects );

			foreach ( $redirects as $redirect ) {
				++$i;
				$progress->tick();

				if ( true === $skip_dupes && 0 !== WPCOM_Legacy_Redirector::get_redirect_post_id( wp_parse_url( $redirect->meta_value, PHP_URL_PATH ) ) ) {
					if ( $verbose ) {
						$notices[] = array(
							'redirect_from' => $redirect->meta_value,
							'redirect_to'   => $redirect->post_id,
							'message'       => sprintf( 'Skipped - Redirect for this from URL already exists (%s)', $redirect->meta_value ),
						);
					}
					continue;
				}

				if ( false === $dry_run ) {
					// Set `redirect_to` flag to have the validate URL perform the correct checks.
					$is_unset = false;
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- CLI command, no nonce context.
					if ( ! isset( $_POST['redirect_to'] ) ) {
						$is_unset = true;
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Setting flag for internal validation.
						$_POST['redirect_to'] = true;
					}

					$inserted = WPCOM_Legacy_Redirector::insert_legacy_redirect( $redirect->meta_value, $redirect->post_id );

					// Clean up.
					if ( $is_unset ) {
						unset( $_POST['redirect_to'] );
					}

					if ( ! $inserted || is_wp_error( $inserted ) ) {
						$failure_message = is_wp_error( $inserted ) ? implode( PHP_EOL, $inserted->get_error_messages() ) : 'Could not insert redirect';
						$notices[]       = array(
							'redirect_from' => $redirect->meta_value,
							'redirect_to'   => $redirect->post_id,
							'message'       => $failure_message,
						);
					} elseif ( $verbose ) {
						$notices[] = array(
							'redirect_from' => $redirect->meta_value,
							'redirect_to'   => $redirect->post_id,
							'message'       => 'Successfully imported',
						);
					}
				}

				if ( 0 === $i % 100 ) {
					if ( function_exists( 'vip_inmemory_cleanup' ) ) {
						vip_inmemory_cleanup();
					}
					sleep( 1 );
				}
			}
			$offset += 1000;
		} while ( $total >= 1000 && $offset < $end_offset );

		$progress->finish();

		if ( count( $notices ) > 0 ) {
			WP_CLI\Utils\format_items( $format, $notices, array( 'redirect_from', 'redirect_to', 'message' ) );
		} else {
			WP_CLI::log( WP_CLI::colorize( '%GAll of your redirects have been imported. Nice work!%n ' ) );
		}
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
	 * @subcommand import-from-csv
	 * @synopsis --csv=<path-to-csv> [--format=<format>] [--verbose] [--skip-validation]
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function import_from_csv( $args, $assoc_args ) {
		define( 'WP_IMPORTING', true );
		$format   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$csv      = trim( \WP_CLI\Utils\get_flag_value( $assoc_args, 'csv' ) );
		$verbose  = isset( $assoc_args['verbose'] ) ? true : false;
		$validate = isset( $assoc_args['skip-validation'] ) ? false : true;
		$notices  = array();

		if ( empty( $csv ) || ! file_exists( $csv ) ) {
			WP_CLI::error( "Invalid 'csv' file" );
		}

		if ( ! $verbose ) {
			WP_CLI::line( 'Processing...' );
		}

		/*
		*  Only applicable for the current CLI request to this function call only.
		*  The configuration option will keep this value during the script's execution, and will be restored at the script's ending.
		*/
		ini_set( 'auto_detect_line_endings', true );

		global $wpdb;
		$row = 0;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CLI command, WP_Filesystem not appropriate.
		$handle = fopen( $csv, 'r' );
		if ( false !== $handle ) {
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

				$inserted = WPCOM_Legacy_Redirector::insert_legacy_redirect( $redirect_from, $redirect_to, $validate );
				if ( ! $inserted || is_wp_error( $inserted ) ) {
					$failure_message = is_wp_error( $inserted ) ? implode( PHP_EOL, $inserted->get_error_messages() ) : 'Could not insert redirect';
					$notices[]       = array(
						'redirect_from' => $redirect_from,
						'redirect_to'   => $redirect_to,
						'message'       => $failure_message,
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
				WP_CLI\Utils\format_items( $format, $notices, array( 'redirect_from', 'redirect_to', 'message' ) );
			} else {
				WP_CLI::log( WP_CLI::colorize( '%GAll of your redirects have been imported. Nice work!%n ' ) );
			}
		}
	}

	/**
	 * Export non-trashed redirects to a CSV file.
	 *
	 * Matches the following structure:
	 *    redirect_from_path,(redirect_to_post_id|redirect_to_path|redirect_to_url)
	 *
	 * ## OPTIONS
	 *
	 * <csv>
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
	 * @subcommand export-to-csv
	 * @synopsis --csv=<path-to-csv> [--overwrite]
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function export_to_csv( $args, $assoc_args ) {
		$filename  = $assoc_args['csv'] ? $assoc_args['csv'] : false;
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
		$post_count     = array_sum( (array) wp_count_posts( Post_Type::POST_TYPE ) );
		$progress       = \WP_CLI\Utils\make_progress_bar( 'Exporting ' . number_format( $post_count ) . ' redirects', $post_count );
		$output         = array();

		do {
			$posts = get_posts(
				array(
					'posts_per_page'   => $posts_per_page,
					'paged'            => $paged,
					'post_type'        => Post_Type::POST_TYPE,
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
		WP_CLI\Utils\write_csv( $file_descriptor, $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI command, WP_Filesystem not appropriate.
		fclose( $file_descriptor );
	}
}
