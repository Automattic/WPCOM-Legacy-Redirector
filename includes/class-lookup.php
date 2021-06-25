<?php
/**
 * Handles the redirect lookup.
 *
 * @package WPCOM_Legacy_Redirect
 */

namespace Automattic\LegacyRedirector;

/**
 * Look up the redirect
 */
final class Lookup {
	const CACHE_GROUP = 'vip-legacy-redirect-2';

	/**
	 * Get Redirect Destination URL.
	 *
	 * @param string $url URL to redirect (source).
	 * @return string|bool Redirect URL if one was found; otherwise false.
	 */
	public static function get_redirect_uri( $url ) {
		$url = \WPCOM_Legacy_Redirector::normalise_url( $url );
		if ( is_wp_error( $url ) ) {
			return false;
		}

		$preservable_params = self::get_preservable_querystring_params_from_url( $url );

		$url = remove_query_arg( array_keys( $preservable_params ), $url );

		$url_hash = \WPCOM_Legacy_Redirector::get_url_hash( $url );

		$redirect_post_id = wp_cache_get( $url_hash, self::CACHE_GROUP );

		if ( false === $redirect_post_id ) {
			$redirect_post_id = self::get_redirect_post_id( $url );
			wp_cache_add( $url_hash, $redirect_post_id, self::CACHE_GROUP );
		}

		if ( $redirect_post_id ) {
			$redirect_post = get_post( $redirect_post_id );
			if ( ! $redirect_post instanceof \WP_Post ) {
				// If redirect post object doesn't exist, reset cache.
				wp_cache_set( $url_hash, 0, self::CACHE_GROUP );

				return false;
			} elseif ( 0 !== $redirect_post->post_parent ) {
				// Add preserved params to the destination URL.
				return add_query_arg( $preservable_params, get_permalink( $redirect_post->post_parent ) );
			} elseif ( ! empty( $redirect_post->post_excerpt ) ) {
				// Add preserved params to the destination URL.
				return add_query_arg( $preservable_params, esc_url_raw( $redirect_post->post_excerpt ) );
			}
		}
		return false;
	}

	/**
	 * Get the preservable query string parameters from a given URL.
	 *
	 * Does not edit the URL.
	 *
	 * @throws \UnexpectedValueException Invalid value from filter.
	 *
	 * @param string $url Normalized source URL with or without querystring.
	 * @return array Associative array of preserved keys and values that were stripped.
	 */
	public static function get_preservable_querystring_params_from_url( $url ) {
		/**
		 * Filter the list of preservable querystring parameter keys.
		 *
		 * The plugin supports providing a list of querystring keys that should be ignored
		 * when calculating the URL hash. These keys and their values are stripped, the
		 * redirect lookup is done on the remaining URL, and then the keys and values are appended
		 * to the destination URL.
		 *
		 * Note that if you amend this list after URLs that include the preserved keys have been
		 * saved to the database, then the redirect lookup will fail for those URLs.
		 *
		 * @since 1.3.0
		 *
		 * @param string[] $preservable_param_keys Indexed array of strings containing the querystring keys
		 *                                         that should be preserved on the destination URL.
		 * @param string   $url                    Normalized source URL.
		 */
		$preservable_param_keys = apply_filters( 'wpcom_legacy_redirector_preserve_query_params', array(), $url );

		if ( ! is_array( $preservable_param_keys ) ) {
			throw new \UnexpectedValueException( 'wpcom_legacy_redirector_preserve_query_params must return an array.' );
		}
		if ( ! empty( $preservable_param_keys ) && array_keys( $preservable_param_keys ) !== range( 0, count( $preservable_param_keys ) - 1 ) ) {
			throw new \UnexpectedValueException( 'wpcom_legacy_redirector_preserve_query_params must return an indexed array.' );
		}

		$preserved_param_values = array();
		$preserved_params       = array();

		// Parse URL to get querystring parameters.
		$url_query_params = wp_parse_url( $url, PHP_URL_QUERY );

		// No parameters in URL, so return early.
		if ( empty( $url_query_params ) ) {
			return array();
		}

		// Parse querystring parameters to associative array.
		parse_str( $url_query_params, $url_params );

		// Extract and return the list of preservable keys (and their values).
		return array_intersect_key( $url_params, array_flip( $preservable_param_keys ) );
	}

	/**
	 * Get Redirect Post ID.
	 *
	 * @param string $url URL to redirect (source).
	 * @return string|int Redirect post ID (as string) if one was found; otherwise 0.
	 */
	public static function get_redirect_post_id( $url ) {
		global $wpdb;

		$url_hash = \WPCOM_Legacy_Redirector::get_url_hash( $url );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$redirect_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", Post_Type::POST_TYPE, $url_hash ) );

		if ( ! $redirect_post_id ) {
			$redirect_post_id = 0;
		}

		return $redirect_post_id;
	}

}
