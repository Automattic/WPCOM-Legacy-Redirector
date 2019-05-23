<?php

class WPCOM_Legacy_Redirector_CLI extends WP_CLI_Command {

	/**
	 * Find domains redirected to, useful to populate the allowed_redirect_hosts filter.
	 *
	 * @subcommand find-domains
	 */
	function find_domains( $args, $assoc_args ) {
		global $wpdb;

		$posts_per_page = 500;
		$paged = 0;

		$domains = array();

		$total_redirects = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( ID ) FROM $wpdb->posts WHERE post_type = %s AND post_excerpt LIKE %s",
				WPCOM_Legacy_Redirector::POST_TYPE,
				'http%'
			)
		);

		$progress = \WP_CLI\Utils\make_progress_bar( 'Finding domains', (int) $total_redirects );
		do {
			$redirect_urls = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_excerpt FROM $wpdb->posts WHERE post_type = %s AND post_excerpt LIKE %s ORDER BY ID ASC LIMIT %d, %d",
					WPCOM_Legacy_Redirector::POST_TYPE,
					'http%',
					( $paged * $posts_per_page ),
					$posts_per_page
				)
			);

			foreach ( $redirect_urls as $redirect_url ) {
				$progress->tick();
				if ( ! empty( $redirect_url ) ) {
					$redirect_host = parse_url( $redirect_url, PHP_URL_HOST );
					if ( $redirect_host ) {
						$domains[] = $redirect_host;
					}
				}
			}

			// Pause.
			sleep( 1 );
			$paged++;
		} while ( count( $redirect_urls ) );

		$progress->finish();

		$domains = array_unique( $domains );

		WP_CLI::line( sprintf( 'Found %s unique outbound domains', number_format( count( $domains ) ) ) );

		foreach ( $domains as $domain ) {
			WP_CLI::line( $domain );
		}
	}

	/**
	 * Insert a single redirect
	 *
	 * @subcommand insert-redirect
	 * @synopsis <from_url> <to_url>
	 */
	function insert_redirect( $args, $assoc_args ) {
		$from_url = esc_url_raw( $args[0] );

		if ( is_numeric( $args[1] ) ) {
			$to_url = absint( $args[1] );
		} else {
			$to_url = esc_url_raw( $args[1] );
		}

		$inserted = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $to_url );

		if ( ! $inserted || is_wp_error( $inserted ) ) {
			WP_CLI::error( sprintf( "Couldn't insert %s -> %s", $from_url, $to_url ) );
		}

		WP_CLI::success( sprintf( "Inserted %s -> %s", $from_url, $to_url ) );
	}

	/**
	 * Bulk import redirects from URLs stored as meta values for posts.
	 *
	 * ## OPTIONS
	 *
	 * [--start=<start-offset>]
	 *
	 * [--end=<end-offset>]
	 *
	 * [--skip_dupes=<skip-dupes>]
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
	 *
	 * [--verbose]
	 * : Display notices for sucessful imports and duplicates (if skip_dupes is used)
	 *
	 * @subcommand import-from-meta
	 * @synopsis --meta_key=<name-of-meta-key> [--start=<start-offset>] [--end=<end-offset>] [--skip_dupes=<skip-dupes>] [--format=<format>] [--dry_run] [--verbose]
	 */
	function import_from_meta( $args, $assoc_args ) {
		define( 'WP_IMPORTING', true );

		global $wpdb;
		$offset = isset( $assoc_args['start'] ) ? intval( $assoc_args['start'] ) : 0;
		$end_offset = isset( $assoc_args['end'] ) ? intval( $assoc_args['end'] ) : 99999999;;
		$meta_key = isset( $assoc_args['meta_key'] ) ? sanitize_key( $assoc_args['meta_key'] ) : '';
		$skip_dupes = isset( $assoc_args['skip_dupes'] ) ? (bool) intval( $assoc_args['skip_dupes'] ) : false;
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$dry_run = isset( $assoc_args['dry_run'] ) ? true : false;
		$verbose = isset( $assoc_args['verbose'] ) ? true : false;
		$notices = array();

		if ( true === $dry_run ) {
			WP_CLI::line( "---Dry Run---" );
		} else {
			WP_CLI::line( "---Live Run--" );
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
			$i = 0;
			$total = count( $redirects );

			foreach ( $redirects as $redirect ) {
				$i++;
				$progress->tick();

				if ( true === $skip_dupes && 0 !== WPCOM_Legacy_Redirector::get_redirect_post_id( parse_url( $redirect->meta_value, PHP_URL_PATH ) ) ) {
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
					if ( ! isset( $_POST['redirect_to'] ) ) {
						$is_unset = true;
						$_POST['redirect_to'] = true;
					}

					$inserted = WPCOM_Legacy_Redirector::insert_legacy_redirect( $redirect->meta_value, $redirect->post_id );

					// Clean up.
					if ( $is_unset ) {
						unset( $_POST['redirect_to'] );
					}
					
					if ( ! $inserted || is_wp_error( $inserted ) ) {
						$failure_message = is_wp_error( $inserted ) ? implode( PHP_EOL, $inserted->get_error_messages() ) : 'Could not insert redirect';
						$notices[] = array(
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

				if ( 0 == $i % 100 ) {
					if ( function_exists( 'stop_the_insanity' ) ) {
						stop_the_insanity();
					}
					sleep( 1 );
				}
			}
			$offset += 1000;
		} while( $total > 1000 && $offset < $end_offset );

		$progress->finish();

		if ( count( $notices ) > 0 ) {
			WP_CLI\Utils\format_items( $format, $notices, array( 'redirect_from', 'redirect_to', 'message' ) );
		} else {
			echo WP_CLI::colorize( "%GAll of your redirects have been imported. Nice work!%n " );
		}
	}

	/**
	 * Bulk import redirects from a CSV file matching the following structure:
	 *
	 * redirect_from_path,(redirect_to_post_id|redirect_to_path|redirect_to_url)
	 *
	 * ## OPTIONS
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
	 * [--verbose]
	 *
	 * @subcommand import-from-csv
	 * @synopsis --csv=<path-to-csv> [--format=<format>] [--verbose] [--skip-validation]
	 */
	function import_from_csv( $args, $assoc_args ) {
		define( 'WP_IMPORTING', true );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$csv = trim( \WP_CLI\Utils\get_flag_value( $assoc_args, 'csv' ) );
		$verbose = isset( $assoc_args['verbose'] ) ? true : false;
		$validate = isset( $assoc_args['skip-validation'] ) ? false : true;
		$notices = array();

		if ( empty( $csv ) || ! file_exists( $csv ) ) {
			WP_CLI::error( "Invalid 'csv' file" );
		}

		if ( ! $verbose ) {
			WP_CLI::line( 'Processing...' );
		}

		global $wpdb;
		$row = 0;
		if ( ( $handle = fopen( $csv, "r" ) ) !== FALSE ) {
			while ( ( $data = fgetcsv( $handle, 2000, "," ) ) !== FALSE ) {
				$row++;
				$redirect_from = $data[ 0 ];
				$redirect_to = $data[ 1 ];
				if ( $verbose ) {
					WP_CLI::line( "Adding (CSV) redirect for {$redirect_from} to {$redirect_to}" );
					WP_CLI::line( "-- at $row" );
				} elseif ( 0 == $row % 100 ) {
					WP_CLI::line( "Processing row $row" );
				}

				$inserted = WPCOM_Legacy_Redirector::insert_legacy_redirect( $redirect_from, $redirect_to, $validate );
				if ( ! $inserted || is_wp_error( $inserted ) ) {
					$failure_message = is_wp_error( $inserted ) ? implode( PHP_EOL, $inserted->get_error_messages() ) : 'Could not insert redirect';
					$notices[] = array(
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

				if ( 0 == $row % 100 ) {
					if ( function_exists( 'stop_the_insanity' ) ) {
						stop_the_insanity();
					}
					sleep( 1 );
				}
			}
			fclose( $handle );

			if ( count( $notices ) > 0 ) {
				WP_CLI\Utils\format_items( $format, $notices, array( 'redirect_from', 'redirect_to', 'message' ) );
			} else {
				echo WP_CLI::colorize( "%GAll of your redirects have been imported. Nice work!%n " );
			}
		}
	}

	/**
	 * Verify our redirects.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]:
	 * : Filter by verification status.
	 * ---
	 * default: unverified
	 * options:
	 *   - unverified
	 *   - verified
	 *   - all
	 * ---
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
	 *   - file
	 * ---
	 *
	 * [--verbose]
	 * : Print notifications to the console for passing URLs.
	 *
	 * [--force_ssl]
	 * : Forces verification over SSL to utilize HTTP/2 multiplexing.
	 *
	 * ## EXAMPLES
	 *
	 * wp wpcom-legacy-redirector verify-redirects
	 *
	 * @subcommand verify-redirects
	 * @synopsis [--status=<status>] [--format=<format>] [--verbose] [--force_ssl]
	 */
	function verify_redirects( $args, $assoc_args ) {

		global $wpdb;

		$redirects = array(
			'redirects'     => array(),
			'notices'       => array(),
			'update_status' => array(),
			'query_count'   => array(),
			'query_count'   => 0,
			'offset'        => 0,
		);
		$posts_per_page = 500;
		$paged = 0;

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$status = \WP_CLI\Utils\get_flag_value( $assoc_args, 'status' );
		switch ( $status ) {
			case 'unverified':
				$status = 'draft';
				break;
			case 'verified':
				$status = 'publish';
				break;
		}

		// Get total # of redirects to process.
		if ( 'all' === $status ) {
			$total_redirects_where = "post_type = '" . WPCOM_Legacy_Redirector::POST_TYPE . "'";
		} else {
			$total_redirects_where = "post_type = '" . WPCOM_Legacy_Redirector::POST_TYPE . "' AND post_status = '" . $status . "'";
		}
		$total_redirects = $wpdb->get_var( "SELECT COUNT( ID ) FROM $wpdb->posts WHERE " . $total_redirects_where );
		$redirects['query_count'] = WPCOM_Legacy_Redirector::update_query_count( $redirects['query_count'] );

		// Start progress bar.
		$progress = \WP_CLI\Utils\make_progress_bar( 'Verifying ' . number_format( $total_redirects ) . ' redirects', (int) $total_redirects );
		$progress->tick();

		do {

			$redirects_query = array(
				'where' => "a.post_type = '" . WPCOM_Legacy_Redirector::POST_TYPE . "'",
				'order' => "a.post_parent ASC",
				'limit' => ( $paged * $posts_per_page ) - $offset . ', ' . $posts_per_page,
			);

			if ( 'all' !== $status ) {
				$redirects_query['where'] .= " AND a.post_status = '" . $status . "'";
			}

			$redirects_to_verify = $wpdb->get_results(
				"SELECT a.ID, a.post_title, a.post_excerpt, a.post_parent, a.post_status,
						b.ID AS 'parent_id', b.post_status as 'parent_status', b.post_type as 'parent_post_type'
					FROM $wpdb->posts a
					LEFT JOIN $wpdb->posts b
						ON a.post_parent = b.ID
					WHERE " . $redirects_query['where'] . "
					ORDER BY " . $redirects_query['order'] . "
					LIMIT " . $redirects_query['limit']
			);

			$redirects['redirects'] = $redirects_to_verify;
			$redirects['query_count'] = WPCOM_Legacy_Redirector::update_query_count( $query_count );

			if ( empty( $redirects['redirects'] ) ) {
				continue;
			}

			// Validate redirects.
			$redirects = WPCOM_Legacy_Redirector::validate_redirects( $redirects, $progress, $assoc_args['force_ssl'] );
			if ( is_wp_error( $redirects ) ) {
				WP_CLI::error( $redirects->get_error_message() );
			}

			// Verify redirects.
			$redirects = WPCOM_Legacy_Redirector::verify_redirects( $redirects, $progress, $assoc_args['verbose'] );
			if ( is_wp_error( $redirects ) ) {
				WP_CLI::error( $redirects->get_error_message() );
			}

			// Update redirect status
			$redirects = WPCOM_Legacy_Redirector::update_redirects_status( $redirects );
			if ( is_wp_error( $update_redirect_status ) ) {
				WP_CLI::error( $update_redirect_status->get_error_message() );
			}

			$paged++;
		} while ( count( $redirects_to_verify ) );

		$progress->finish();

		if ( count( $redirects['notices'] ) > 0 ) {
			if ( 'file' === $format ) {
				$fp = fopen( 'wpcom-legacy-redirector_verify-redirects_' . time() . '.csv', 'w' );
				WP_CLI\Utils\write_csv( $fp, $redirects['notices'], array( 'id', 'from_url', 'to_url', 'message' ) );
				fclose( $fp );
			} else {
				WP_CLI\Utils\format_items( $format, $redirects['notices'], array( 'id', 'from_url', 'to_url', 'message' ) );
			}
		} else {
			echo WP_CLI::colorize( "%GAll of your redirects have been verified. Nice work!%n " );
		}
	}
}

WP_CLI::add_command( 'wpcom-legacy-redirector', 'WPCOM_Legacy_Redirector_CLI' );
