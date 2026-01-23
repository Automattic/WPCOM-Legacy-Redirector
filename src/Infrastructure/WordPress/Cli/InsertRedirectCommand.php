<?php
/**
 * Insert redirect CLI command.
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
 * Insert a single redirect.
 */
final class InsertRedirectCommand extends WP_CLI_Command {

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
	 * [--status=<status>]
	 * : The initial status of the redirect.
	 * ---
	 * default: enabled
	 * options:
	 *   - enabled
	 *   - disabled
	 * ---
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
	 *     # Insert a disabled redirect.
	 *     $ wp wpcom-legacy-redirector insert-redirect /old /new --status=disabled
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$from_url    = $args[0];
		$to_value    = is_numeric( $args[1] ) ? absint( $args[1] ) : $args[1];
		$status_flag = $assoc_args['status'] ?? 'enabled';
		$post_status = 'disabled' === $status_flag ? 'draft' : 'publish';

		try {
			$source      = SourceUrl::from_string( $from_url );
			$destination = Destination::from_mixed( $to_value );
		} catch ( \InvalidArgumentException $e ) {
			WP_CLI::error( sprintf( "Couldn't insert %s -> %s (%s)", $from_url, $to_value, $e->getMessage() ) );
			return;
		}

		// Basic sanity check: source and destination must be different.
		if ( $this->are_same_path( $source, $destination ) ) {
			WP_CLI::error( sprintf( "Couldn't insert %s -> %s (%s)", $from_url, $to_value, '"Redirect From" and "Redirect To" values are required and should not match.' ) );
			return;
		}

		// For external URLs, validate that the host is allowed.
		// This happens separately from full validation to match legacy behavior.
		if ( $destination->is_url() ) {
			$url    = $destination->as_url()->value();
			$parsed = wp_parse_url( $url );

			if ( ! empty( $parsed['host'] ) && ! empty( $parsed['scheme'] ) && in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
				// Check if this external host is in the allowed_redirect_hosts.
				if ( ! $this->is_host_allowed( $parsed['host'] ) ) {
					WP_CLI::error( sprintf( "Couldn't insert %s -> %s (If you are doing an external redirect, make sure you safelist the domain using the \"allowed_redirect_hosts\" filter.)", $from_url, $to_value ) );
					return;
				}
			}
		}

		// Skip full validation by default to match legacy behavior.
		$result = $this->manager->create_redirect( $source, $destination, false, $post_status );

		if ( $result->is_error() ) {
			$error_message = $this->get_friendly_error_message( $result->error_code(), $result->error_message() );
			WP_CLI::error( sprintf( "Couldn't insert %s -> %s (%s)", $from_url, $to_value, $error_message ) );
			return;
		}

		$status_msg = 'disabled' === $status_flag ? ' (disabled)' : '';
		WP_CLI::success( sprintf( 'Inserted %s -> %s%s', $from_url, $to_value, $status_msg ) );
	}

	/**
	 * Check if source and destination resolve to the same path.
	 *
	 * @param SourceUrl   $source      The source URL.
	 * @param Destination $destination The destination.
	 * @return bool True if they are effectively the same path.
	 */
	private function are_same_path( SourceUrl $source, Destination $destination ): bool {
		$source_path = $this->normalise_path( $source->path() );

		if ( $destination->is_post_id() ) {
			$post_permalink = get_permalink( $destination->as_post_id()->value() );
			if ( false !== $post_permalink ) {
				$destination_path = wp_parse_url( $post_permalink, PHP_URL_PATH );
				if ( $destination_path ) {
					return $source_path === $this->normalise_path( $destination_path );
				}
			}
			return false;
		}

		$url              = $destination->as_url()->value();
		$parsed           = wp_parse_url( $url );
		$destination_path = $parsed['path'] ?? '';

		return $source_path === $this->normalise_path( $destination_path );
	}

	/**
	 * Normalise a path for comparison.
	 *
	 * @param string $path The path to normalise.
	 * @return string Normalised path.
	 */
	private function normalise_path( string $path ): string {
		return strtolower( trim( $path, '/' ) );
	}

	/**
	 * Check if a host is in the allowed redirect hosts list.
	 *
	 * @param string $host The host to check.
	 * @return bool True if the host is allowed.
	 */
	private function is_host_allowed( string $host ): bool {
		// Get the home host which is always allowed.
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

		// Check if it's the home host.
		if ( $host === $home_host ) {
			return true;
		}

		// Get the allowed hosts from the filter.
		$allowed_hosts = (array) apply_filters( 'allowed_redirect_hosts', array( $home_host ), $home_host );

		return in_array( $host, $allowed_hosts, true );
	}

	/**
	 * Get a user-friendly error message for CLI output.
	 *
	 * @param string|null $code    The error code.
	 * @param string|null $message The error message.
	 * @return string The friendly message.
	 */
	private function get_friendly_error_message( ?string $code, ?string $message ): string {
		$messages = array(
			'duplicate-redirect-uri' => 'A redirect for this URI already exists.',
			'invalid-values'         => '"Redirect From" and "Redirect To" values are required and should not match.',
			'empty-postid'           => 'The post ID does not exist.',
			'non-public'             => 'You are trying to redirect to a post that is not published.',
			'invalid'                => 'The destination URL does not exist.',
			'invalid-url'            => 'If you are doing an external redirect, make sure you safelist the domain using the "allowed_redirect_hosts" filter.',
		);

		return $messages[ $code ] ?? ( $message ?? 'Unknown error' );
	}
}
