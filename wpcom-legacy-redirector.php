<?php
/**
 * Plugin Name: WPCOM Legacy Redirector
 * Plugin URI: https://vip.wordpress.com/plugins/wpcom-legacy-redirector/
 * Description: Simple plugin for handling legacy redirects in a scalable manner.
 * Version: 1.2.0
 * Author: Automattic / WordPress.com VIP
 * Author URI: https://vip.wordpress.com
 *
 * This is a no-frills plugin (no UI, for example). Data entry needs to be bulk-loaded via the wp-cli commands provided or custom scripts.
 *
 * Redirects are stored as a custom post type and use the following fields:
 *
 * - post_name for the md5 hash of the "from" path or URL.
 *  - we use this column, since it's indexed and queries are super fast.
 *  - we also use an md5 just to simplify the storage.
 * - post_title to store the non-md5 version of the "from" path.
 * - one of either:
 *  - post_parent if we're redirect to a post; or
 *  - post_excerpt if we're redirecting to an alternate URL.
 *
 * Please contact us before using this plugin.
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require( __DIR__ . '/includes/wp-cli.php' );
}

require( __DIR__ . '/includes/class-wpcom-legacy-redirector-ui.php' );

class WPCOM_Legacy_Redirector {
	const POST_TYPE = 'vip-legacy-redirect';
	const CACHE_GROUP = 'vip-legacy-redirect-2';

	static function start() {
		add_action( 'init', array( __CLASS__, 'init' ) );
		add_action( 'init', array( __CLASS__, 'register_redirect_custom_capability') );
		add_filter( 'template_redirect', array( __CLASS__, 'maybe_do_redirect' ), 0 ); // hook in early, before the canonical redirect
		add_action( 'admin_menu', array( new WPCOM_Legacy_Redirector_UI, 'admin_menu' ) );
		load_plugin_textdomain( 'vip-legacy-redirect', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	static function init() {
		$labels = array(
			'name'                  => _x( 'Redirect Manager', 'Post type general name', 'wpcom-legacy-redirector' ),
			'singular_name'         => _x( 'Redirect Manager', 'Post type singular name', 'wpcom-legacy-redirector' ),
			'menu_name'             => _x( 'Redirect Manager', 'Admin Menu text', 'wpcom-legacy-redirector' ),
			'name_admin_bar'        => _x( 'Redirect Manager', 'Add New on Toolbar', 'wpcom-legacy-redirector' ),
			'add_new'               => __( 'Add New', 'wpcom-legacy-redirector' ),
			'add_new_item'          => __( 'Add New Redirect', 'wpcom-legacy-redirector' ),
			'new_item'              => __( 'New Redirect', 'wpcom-legacy-redirector' ),
			'all_items'             => __( 'All Redirects', 'wpcom-legacy-redirector' ),
			'search_items'          => __( 'Search Redirects', 'wpcom-legacy-redirector' ),
			'not_found'             => __( 'No redirects found.', 'wpcom-legacy-redirector' ),
			'not_found_in_trash'    => __( 'No redirects found in Trash.', 'wpcom-legacy-redirector' ),
			'filter_items_list'     => _x( 'Filter redirects list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'wpcom-legacy-redirector' ),
			'items_list_navigation' => _x( 'Redirect list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'wpcom-legacy-redirector' ),
			'items_list'            => _x( 'Redirects list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'wpcom-legacy-redirector' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'rewrite'            => array( 'slug' => 'book' ),
			'capability_type'    => 'post',
			'hierarchical'       => false,
			'menu_position'      => 100,
			'capabilities'       => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'       => true,
			'menu_icon'          => 'dashicons-external',
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
		);
		register_post_type( self::POST_TYPE, $args );
	}
	/**
	 * Register custom role using VIP Helpers with fallbacks.
	 */
	static function register_redirect_custom_capability() {
		$cap = apply_filters( 'manage_redirect_capability', 'manage_redirects' );
		if ( function_exists( 'wpcom_vip_add_role_caps' ) ) {
			wpcom_vip_add_role_caps( 'administrator', $cap );
			wpcom_vip_add_role_caps( 'editor', $cap );
		} else {
			$role = get_role( 'editor' );
			$role->add_cap( $cap );
		}
	}

	static function maybe_do_redirect() {
		// Avoid the overhead of running this on every single pageload.
		// We move the overhead to the 404 page but the trade-off for site performance is worth it.
		if ( ! is_404() ) {
			return;
		}

		$url = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			$url .= '?' . $_SERVER['QUERY_STRING'];
		}

		$request_path = apply_filters( 'wpcom_legacy_redirector_request_path', $url );

		if ( $request_path ) {
			$redirect_uri = self::get_redirect_uri( $request_path );

			if ( $redirect_uri ) {
				header( 'X-legacy-redirect: HIT' );
				$redirect_status = apply_filters( 'wpcom_legacy_redirector_redirect_status', 301, $url );
				wp_safe_redirect( $redirect_uri, $redirect_status );
				exit;
			}
		}
	}

	/**
	 * @param string $from_url        URL or path that should be redirected; should have leading slash if path.
	 * @param int|string $redirect_to The post ID or URL to redirect to.
	 * @param bool $validate          Validate $from_url and $redirect_to values.
	 *
	 * @return bool|string|\WP_Error Error if invalid redirect URL specified or if the URI already has a rule; false if not is_admin, true otherwise.
	 */
	static function insert_legacy_redirect( $from_url, $redirect_to, $validate = true ) {

		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! is_admin() && ! apply_filters( 'wpcom_legacy_redirector_allow_insert', false ) ) {
			// never run on the front end
			return false;
		}

		$from_url = self::normalise_url( $from_url );
		if ( is_wp_error( $from_url ) ) {
			return $from_url;
		}

		if ( $validate && false === self::validate( $from_url, $redirect_to ) ) {
			$message = __( '"Redirect From" and "Redirect To" values are required and should not match.', 'wpcom-legacy-redirector' );
			return new WP_Error( 'invalid-values', $message );
		}

		$from_url_hash = self::get_url_hash( $from_url );

		if ( false !== self::get_redirect_uri( $from_url ) ) {
			return new WP_Error( 'duplicate-redirect-uri', 'A redirect for this URI already exists' );
		}

		$args = array(
			'post_name' => $from_url_hash,
			'post_title' => $from_url,
			'post_type' => self::POST_TYPE,
		);

		if ( is_numeric( $redirect_to ) ) {
			$args['post_parent'] = $redirect_to;
		} elseif ( false !== wp_parse_url( $redirect_to ) ) {
			$args['post_excerpt'] = esc_url_raw( $redirect_to );
		} else {
			return new WP_Error( 'invalid-redirect-url', 'Invalid redirect_to param; should be a post_id or a URL' );
		}

		wp_insert_post( $args );

		wp_cache_delete( $from_url_hash, self::CACHE_GROUP );

		return true;
	}

	static function get_redirect_uri( $url ) {

		$url = self::normalise_url( $url );
		if ( is_wp_error( $url ) ) {
			return false;
		}

		$url_hash = self::get_url_hash( $url );

		$redirect_post_id = wp_cache_get( $url_hash, self::CACHE_GROUP );

		if ( false === $redirect_post_id ) {
			$redirect_post_id = self::get_redirect_post_id( $url );
			wp_cache_add( $url_hash, $redirect_post_id, self::CACHE_GROUP );
		}

		if ( $redirect_post_id ) {
			$redirect_post = get_post( $redirect_post_id );
			if ( ! $redirect_post instanceof WP_Post ) {
				// If redirect post object doesn't exist, reset cache
				wp_cache_set( $url_hash, 0, self::CACHE_GROUP );

				return false;
			} elseif ( 0 !== $redirect_post->post_parent ) {
				return get_permalink( $redirect_post->post_parent );
			} elseif ( ! empty( $redirect_post->post_excerpt ) ) {
				return esc_url_raw( $redirect_post->post_excerpt );
			}
		}

		return false;
	}

	static function get_redirect_post_id( $url ) {
		global $wpdb;

		$url_hash = self::get_url_hash( $url );

		// Allow plugins to disable lowercase. Check in case we transform to lowercase.
		if ( apply_filters( 'wpcom_legacy_redirector_check_lowercase', true ) ) {
			$lowercase_url_hash = self::get_url_hash( self::lowercase( $url ) );
			$select_query       = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND (post_name = %s OR post_name = %s) LIMIT 1", self::POST_TYPE, $url_hash, $lowercase_url_hash );
		} else {
			$select_query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", self::POST_TYPE, $url_hash );
		}

		$redirect_post_id = $wpdb->get_var( $select_query );

		if ( ! $redirect_post_id ) {
			$redirect_post_id = 0;
		}

		return $redirect_post_id;
	}

	private static function get_url_hash( $url ) {
		return md5( $url );
	}

	/**
	 * Takes a request URL and "normalises" it, stripping common elements
	 *
	 * Removes scheme and host from the URL, as redirects should be independent of these.
	 *
	 * @param string $url URL to transform
	 *
	 * @return string $url Transformed URL
	 */
	private static function normalise_url( $url ) {

		// Sanitise the URL first rather than trying to normalise a non-URL
		$url = esc_url_raw( $url );
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid-redirect-url', 'The URL does not validate' );
		}

		// Break up the URL into it's constituent parts
		$components = wp_parse_url( $url );

		// Avoid playing with unexpected data
		if ( ! is_array( $components ) ) {
			return new WP_Error( 'url-parse-failed', 'The URL could not be parsed' );
		}

		// We should have at least a path or query
		if ( ! isset( $components['path'] ) && ! isset( $components['query'] ) ) {
			return new WP_Error( 'url-parse-failed', 'The URL contains neither a path nor query string' );
		}

		// Make sure $components['query'] is set, to avoid errors
		$components['query'] = ( isset( $components['query'] ) ) ? $components['query'] : '';

		// All we want is path and query strings
		// Note this strips hashes (#) too
		// @todo should we destory the query strings and rebuild with `add_query_arg()`?
		$normalised_url = $components['path'];

		// Only append '?' and the query if there is one
		if ( ! empty( $components['query'] ) ) {
			$normalised_url = $components['path'] . '?' . $components['query'];
		}

		// Allow plugins to disable lowercase.
		if ( apply_filters( 'wpcom_legacy_redirector_check_lowercase', true ) ) {
			// Transform to lowercase.
			$normalised_url = self::lowercase( $normalised_url );
		}

		return $normalised_url;

	}

	/**
	 * @param $string
	 *
	 * @return string
	 */
	public static function lowercase( $string ) {
		return ! empty( $string ) ? strtolower( $string ) : $string;
	}

	/**
	 * @param $url
	 *
	 * @return string
	 */
	public static function transform( $url ) {
		return trim( self::lowercase( $url ), '/' );
	}

	/**
	 * @param $from_url
	 * @param $redirect_to
	 *
	 * @return bool
	 */
	public static function validate( $from_url, $redirect_to ) {
		return ( ! empty( $from_url ) && ! empty( $redirect_to ) && self::transform( $from_url ) !== self::transform( $redirect_to ) );
	}
}

WPCOM_Legacy_Redirector::start();
