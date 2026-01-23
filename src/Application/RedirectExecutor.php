<?php
/**
 * Redirect executor service.
 *
 * @package Automattic\LegacyRedirector\Application
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Application;

use Automattic\LegacyRedirector\Domain\Redirect;
use Automattic\LegacyRedirector\Domain\RedirectRepositoryInterface;
use Automattic\LegacyRedirector\Domain\SourceUrl;

/**
 * Service responsible for finding and executing redirects.
 *
 * This is the main entry point for redirect lookups during request handling.
 * It coordinates with the repository to find redirects and resolves
 * destinations to full URLs ready for the HTTP redirect.
 */
final class RedirectExecutor {

	/**
	 * The redirect repository.
	 *
	 * @var RedirectRepositoryInterface
	 */
	private RedirectRepositoryInterface $repository;

	/**
	 * Plugin name for the X-Redirect-By header.
	 *
	 * @var string
	 */
	private string $plugin_name;

	/**
	 * Constructor.
	 *
	 * @param RedirectRepositoryInterface $repository  The redirect repository.
	 * @param string                      $plugin_name Plugin name for redirect headers.
	 */
	public function __construct( RedirectRepositoryInterface $repository, string $plugin_name = 'wpcom-legacy-redirector' ) {
		$this->repository  = $repository;
		$this->plugin_name = $plugin_name;
	}

	/**
	 * Try to find and execute a redirect for the current request.
	 *
	 * This method is designed to be called from the template_redirect hook.
	 * It only processes 404 pages to avoid overhead on normal requests.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		// Only process 404 pages - avoids overhead on every pageload.
		if ( ! is_404() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised in SourceUrl::from_string().
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( empty( $request_uri ) ) {
			return;
		}

		$redirect_data = $this->get_redirect_data( $request_uri );
		if ( null === $redirect_data ) {
			return;
		}

		$this->perform_redirect(
			$redirect_data['url'],
			$redirect_data['status_code']
		);
	}

	/**
	 * Get redirect data for a URL.
	 *
	 * @param string $url The URL to find a redirect for.
	 * @return array{url: string, status_code: int}|null Redirect data or null if not found.
	 */
	public function get_redirect_data( string $url ): ?array {
		/**
		 * Filter the request path before redirect lookup.
		 *
		 * @since 1.0.0
		 *
		 * @param string $path The request path.
		 */
		$path = apply_filters( 'wpcom_legacy_redirector_request_path', $this->extract_path( $url ) );

		if ( empty( $path ) ) {
			return null;
		}

		// Extract preservable query params before lookup.
		$preservable_params = $this->get_preservable_params( $path );
		$lookup_path        = $this->strip_preservable_params( $path, $preservable_params );

		// Find the redirect.
		try {
			$source   = SourceUrl::from_string( $lookup_path );
			$redirect = $this->repository->find_by_source( $source );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}

		if ( null === $redirect ) {
			return null;
		}

		// Resolve the destination URL.
		$destination_url = $this->resolve_destination( $redirect, $preservable_params );
		if ( empty( $destination_url ) ) {
			return null;
		}

		/**
		 * Filter the redirect status code.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $status_code The HTTP status code (default 301).
		 * @param string $url         The original request URL.
		 */
		$status_code = apply_filters( 'wpcom_legacy_redirector_redirect_status', 301, $url );

		return array(
			'url'         => $destination_url,
			'status_code' => $status_code,
		);
	}

	/**
	 * Find a redirect by source URL.
	 *
	 * @param string $url The source URL.
	 * @return Redirect|null The redirect if found.
	 */
	public function find_redirect( string $url ): ?Redirect {
		try {
			$source = SourceUrl::from_string( $url );
			return $this->repository->find_by_source( $source );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Extract the path and query string from a URL.
	 *
	 * In subdirectory multisite, strips the subsite path prefix to get
	 * the site-relative path that matches stored redirects.
	 *
	 * @param string $url The URL.
	 * @return string The path with optional query string.
	 */
	private function extract_path( string $url ): string {
		// Decode the URL to handle encoded characters.
		$decoded  = urldecode( $url );
		$url_info = wp_parse_url( $decoded );

		if ( ! is_array( $url_info ) || ! isset( $url_info['path'] ) ) {
			return '';
		}

		$path = $url_info['path'];

		// In subdirectory multisite, strip the subsite path prefix.
		// e.g., /site3/to-slug becomes /to-slug for site3.
		$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
		if ( ! empty( $home_path ) && '/' !== $home_path && str_starts_with( $path, $home_path ) ) {
			$path = substr( $path, strlen( rtrim( $home_path, '/' ) ) );
			// Ensure path starts with / after stripping.
			if ( empty( $path ) ) {
				$path = '/';
			}
		}

		if ( isset( $url_info['query'] ) ) {
			$path .= '?' . $url_info['query'];
		}

		return $path;
	}

	/**
	 * Get the preservable query parameters from a URL.
	 *
	 * @param string $url The URL with query string.
	 * @return array<string, string> Preserved parameter key-value pairs.
	 */
	private function get_preservable_params( string $url ): array {
		/**
		 * Filter the list of preservable querystring parameter keys.
		 *
		 * These parameters are stripped before lookup and re-appended to the destination.
		 *
		 * @since 1.3.0
		 *
		 * @param string[] $keys Indexed array of querystring keys to preserve.
		 * @param string   $url  The source URL.
		 */
		$keys = apply_filters( 'wpcom_legacy_redirector_preserve_query_params', array(), $url );

		if ( ! is_array( $keys ) || empty( $keys ) ) {
			return array();
		}

		// Extract query string.
		$query_string = wp_parse_url( $url, PHP_URL_QUERY );
		if ( empty( $query_string ) ) {
			return array();
		}

		// Parse to array.
		$params = array();
		parse_str( $query_string, $params );

		// Return only the preservable keys.
		return array_intersect_key( $params, array_flip( $keys ) );
	}

	/**
	 * Strip preservable parameters from a URL for lookup.
	 *
	 * @param string                $url    The URL.
	 * @param array<string, string> $params Parameters to strip.
	 * @return string URL without the preservable parameters.
	 */
	private function strip_preservable_params( string $url, array $params ): string {
		if ( empty( $params ) ) {
			return $url;
		}

		return remove_query_arg( array_keys( $params ), $url );
	}

	/**
	 * Resolve a redirect's destination to a full URL.
	 *
	 * @param Redirect              $redirect          The redirect.
	 * @param array<string, string> $preservable_params Query params to append.
	 * @return string The resolved destination URL.
	 */
	private function resolve_destination( Redirect $redirect, array $preservable_params ): string {
		$destination = $redirect->destination();

		if ( $destination->is_post_id() ) {
			$url = get_permalink( $destination->as_post_id()->value() );
			if ( false === $url ) {
				return '';
			}
		} else {
			$url = $destination->as_url()->resolve( home_url() );
		}

		// Append preserved query params.
		if ( ! empty( $preservable_params ) ) {
			$url = add_query_arg( $preservable_params, $url );
		}

		return $url;
	}

	/**
	 * Perform the actual HTTP redirect.
	 *
	 * @param string $url         The destination URL.
	 * @param int    $status_code The HTTP status code.
	 * @return never
	 */
	private function perform_redirect( string $url, int $status_code ): void {
		// Allow redirects to external hosts by adding destination host to allowed list.
		$this->allow_redirect_host( $url );

		// WordPress 5.1+ supports the X-Redirect-By header via third argument.
		if ( version_compare( get_bloginfo( 'version' ), '5.1.0', '>=' ) ) {
			wp_safe_redirect( $url, $status_code, $this->plugin_name );
		} else {
			header( 'X-legacy-redirect: HIT' );
			wp_safe_redirect( $url, $status_code );
		}

		exit;
	}

	/**
	 * Add the destination URL's host to the allowed redirect hosts.
	 *
	 * @param string $url The destination URL.
	 * @return void
	 */
	private function allow_redirect_host( string $url ): void {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( empty( $host ) ) {
			return;
		}

		add_filter(
			'allowed_redirect_hosts',
			static function ( array $hosts ) use ( $host ): array {
				$hosts[] = $host;
				return $hosts;
			}
		);
	}
}
