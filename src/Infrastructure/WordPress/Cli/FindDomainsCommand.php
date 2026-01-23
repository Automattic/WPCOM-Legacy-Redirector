<?php
/**
 * Find domains CLI command.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Infrastructure\WordPress\PostType;
use WP_CLI;
use WP_CLI_Command;

/**
 * Find unique outbound domains in redirects.
 */
final class FindDomainsCommand extends WP_CLI_Command {

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
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		global $wpdb;

		$posts_per_page = 500;
		$paged          = 0;
		$domains        = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command for bulk operation.
		$total_redirects = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( ID ) FROM $wpdb->posts WHERE post_type = %s AND post_excerpt LIKE %s",
				PostType::POST_TYPE,
				'http%'
			)
		);

		$progress = \WP_CLI\Utils\make_progress_bar( 'Finding domains', (int) $total_redirects );

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI command for bulk operation.
			$redirect_urls = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_excerpt FROM $wpdb->posts WHERE post_type = %s AND post_excerpt LIKE %s ORDER BY ID ASC LIMIT %d, %d",
					PostType::POST_TYPE,
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

			sleep( 1 );
			++$paged;
			$redirect_urls_count = count( $redirect_urls );
		} while ( $redirect_urls_count );

		$progress->finish();

		$domains       = array_unique( $domains );
		$domains_count = count( $domains );

		/* translators: %s = count of the domains */
		$translatable_text = _n(
			'Found %s unique outbound domain.',
			'Found %s unique outbound domains.',
			$domains_count,
			'wpcom-legacy-redirector'
		);

		WP_CLI::line( sprintf( $translatable_text, number_format( $domains_count ) ) );

		foreach ( $domains as $domain ) {
			WP_CLI::line( $domain );
		}
	}
}
