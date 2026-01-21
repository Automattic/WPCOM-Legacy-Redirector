<?php
/**
 * URL utility functions.
 *
 * @package Automattic\LegacyRedirector\Infrastructure\WordPress
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress;

use InvalidArgumentException;

/**
 * Utility functions for URL parsing and manipulation.
 *
 * Provides UTF-8 aware URL parsing and WordPress-specific URL utilities.
 */
final class UrlUtils {

	/**
	 * UTF-8 aware wp_parse_url() replacement.
	 *
	 * Sample Input: https://www.example1.org//فوتوغرافيا/?test=فوتوغرافيا
	 * Sample Output: Array (
	 *                  [scheme] => https
	 *                  [host] => www.example1.org
	 *                  [path] => //فوتوغرافيا/
	 *                  [query] => test=فوتوغرافيا
	 *                ) .
	 *
	 * @throws InvalidArgumentException Malformed URL.
	 *
	 * @param string $url        The URL to parse. We will try and encode all url characters except
	 *                           reserved URL chars https://developers.google.com/maps/documentation/urls/url-encoding.
	 * @param int    $component  Optional. The specific component to retrieve. Use one of the
	 *                           PHP predefined constants to specify which one. Defaults
	 *                           to -1 (= return all parts as an array).
	 * @return string|array<string, string|int>|int|null Array of URL components on success; When a specific component
	 *                                                   has been requested: null if the component doesn't exist in the
	 *                                                   given URL; a string (or in the case of PHP_URL_PORT, integer)
	 *                                                   when it does.
	 */
	public static function mb_parse_url( string $url, int $component = -1 ) {
		$encoded_url = preg_replace_callback(
			'|[^!*\'();:@&=+$,\/?%#\[\]]+|usD',
			static function ( array $matches ): string {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Required for proper percent-encoding of UTF-8 chars.
				return urlencode( $matches[0] );
			},
			$url
		);

		$parts = wp_parse_url( $encoded_url, $component );

		if ( null === $parts ) {
			return null;
		}

		if ( false === $parts ) {
			throw new InvalidArgumentException( 'Malformed URL: ' . $url ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not rendered output.
		}

		if ( is_array( $parts ) ) {
			foreach ( $parts as $name => $value ) {
				$parts[ $name ] = urldecode( (string) $value );
			}
		} else {
			$parts = urldecode( (string) $parts );
		}

		return $parts;
	}

	/**
	 * Get WP Home URL without path suffix.
	 *
	 * Returns the scheme and host (and port if present) of the home URL,
	 * without any path component.
	 *
	 * @return string The home domain URL (e.g., "https://example.com" or "https://example.com:8080").
	 */
	public static function get_home_domain_without_path(): string {
		$home_url_info = self::mb_parse_url( home_url() );

		if ( ! is_array( $home_url_info ) ) {
			return home_url();
		}

		$return_url = ( $home_url_info['scheme'] ?? 'https' ) . '://' . ( $home_url_info['host'] ?? '' );

		if ( ! empty( $home_url_info['port'] ) ) {
			$return_url .= ':' . $home_url_info['port'];
		}

		return $return_url;
	}
}
