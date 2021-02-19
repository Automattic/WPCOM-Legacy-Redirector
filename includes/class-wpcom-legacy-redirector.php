<?php

/**
 * Plugin core functionality for creating, validating, and performing redirect rules.
 */
class WPCOM_Legacy_Redirector {
	const POST_TYPE   = 'vip-legacy-redirect';
	const CACHE_GROUP = 'vip-legacy-redirect-2';

	/**
	 * Actions and filters.
	 */
	static function start() {
		add_action( 'init', array( __CLASS__, 'init' ) );
		add_action( 'init', array( __CLASS__, 'register_redirect_custom_capability' ) );
		add_filter( 'template_redirect', array( __CLASS__, 'maybe_do_redirect' ), 0 ); // hook in early, before the canonical redirect.
		add_action( 'admin_menu', array( new WPCOM_Legacy_Redirector_UI(), 'admin_menu' ) );
		add_filter( 'admin_enqueue_scripts', array( __CLASS__, 'wpcom_legacy_add_redirect_js' ) );
		add_filter( 'bulk_actions-edit-' . self::POST_TYPE, array( __CLASS__, 'remove_bulk_edit' ) );
	}

	/**
	 * Initialize and register the CPT.
	 */
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
			'public'             => false,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'rewrite'            => false,
			'query_var'          => false,
			'capability_type'    => 'post',
			'hierarchical'       => false,
			'menu_position'      => 100,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => false,
			'capabilities'       => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'       => true,
			'menu_icon'          => 'dashicons-randomize',
			'supports'           => [ 'page-attributes' ],
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
			$roles = array( 'administrator', 'editor' );
			foreach ( $roles as $role ) {
				$role_obj = get_role( $role );
				$role_obj->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove Bulk Edit from the Bulk Actions drop-down on the CPT's edit screen UI.
	 *
	 * @param array $actions Current bulk actions available to drop-down.
	 * @return array Available bulk actions minus edit functionality.
	 */
	static function remove_bulk_edit( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	/**
	 * Performs redirect if current URL is a 404 and redirect rule exists.
	 */
	static function maybe_do_redirect() {
		// Avoid the overhead of running this on every single pageload.
		// We move the overhead to the 404 page but the trade-off for site performance is worth it.
		if ( ! is_404() ) {
			return;
		}

		$url = self::mb_parse_url( urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH );

		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			$url .= '?' . urldecode( $_SERVER['QUERY_STRING'] );
		}

		$request_path = apply_filters( 'wpcom_legacy_redirector_request_path', $url );

		if ( $request_path ) {
			$redirect_uri = self::get_redirect_uri( $request_path );
			if ( $redirect_uri ) {
				$redirect_status = apply_filters( 'wpcom_legacy_redirector_redirect_status', 301, $url );

				// Third argument introduced to support the x_redirect_by header to denote WP redirect source.
				if ( version_compare( get_bloginfo( 'version' ), '5.1.0', '>=' ) ) {
					wp_safe_redirect( $redirect_uri, $redirect_status, WPCOM_LEGACY_REDIRECTOR_PLUGIN_NAME );
				} else {
					header( 'X-legacy-redirect: HIT' );
					wp_safe_redirect( $redirect_uri, $redirect_status );
				}

				exit;
			}
		}
	}

	/**
	 * Enqueue the JS that builds the link previews.
	 *
	 * @since 1.4.0
	 *
	 * @param string $hook Get the current page hook.
	 */
	public static function wpcom_legacy_add_redirect_js( $hook ) {
		if ( 'vip-legacy-redirect_page_wpcom-legacy-redirector' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'wpcom-legacy-redirector', plugins_url( '/../js/admin-add-redirects.js', __FILE__ ), [], WPCOM_LEGACY_REDIRECTOR_VERSION, true );
		wp_localize_script( 'wpcom-legacy-redirector', 'wpcomLegacyRedirector', array( 'siteurl' => home_url() ) );
	}

	/**
	 * Insert redirect as CPT in the database.
	 *
	 * @param string     $from_url    URL or path that should be redirected; should have leading slash if path.
	 * @param int|string $redirect_to The post ID or URL to redirect to.
	 * @param bool       $validate    Validate $from_url and $redirect_to values.
	 * @return bool|WP_Error True if inserted; false if not permitted; otherwise error upon validation issue.
	 */
	static function insert_legacy_redirect( $from_url, $redirect_to, $validate = true ) {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! is_admin() && ! apply_filters( 'wpcom_legacy_redirector_allow_insert', false ) ) {
			// Never run on the front end.
			return false;
		}

		$from_url = self::normalise_url( $from_url );
		if ( is_wp_error( $from_url ) ) {
			return $from_url;
		}

		$from_url_hash = self::get_url_hash( $from_url );

		if ( $validate ) {
			$valid_urls = self::validate_urls( $from_url, $redirect_to );
			if ( is_object( $valid_urls ) ) {
				return $valid_urls;
			} else {
				$valid_urls[0] = $from_url;
				$valid_urls[1] = $redirect_to;
			}
		}

		$args = array(
			'post_name'  => $from_url_hash,
			'post_title' => $from_url,
			'post_type'  => self::POST_TYPE,
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

	/**
	 * Validate the URLs.
	 *
	 * @param string $from_url    URL to redirect (source).
	 * @param string $redirect_to URL to redirect to (destination).
	 * @return array|WP_Error Error if invalid redirect URL specified; returns array of params otherwise.
	 */
	static function validate_urls( $from_url, $redirect_to ) {
		if ( false !== self::get_redirect_uri( $from_url ) ) {
			return new WP_Error( 'duplicate-redirect-uri', 'A redirect for this URI already exists' );
		}
		if ( is_numeric( $redirect_to ) || false !== strpos( $redirect_to, 'http' ) ) {
			if ( is_numeric( $redirect_to ) && true !== self::vip_legacy_redirect_parent_id( $redirect_to ) ) {
				$message = __( 'Redirect is pointing to a Post ID that does not exist.', 'wpcom-legacy-redirector' );
				return new WP_Error( 'empty-postid', $message );
			}
			if ( ! wp_validate_redirect( $redirect_to ) ) {
				$message = __( 'If you are doing an external redirect, make sure you safelist the domain using the "allowed_redirect_hosts" filter.', 'wpcom-legacy-redirector' );
				return new WP_Error( 'external-url-not-allowed', $message );
			}
			return array( $from_url, $redirect_to );
		}
		if ( false === self::validate( $from_url, $redirect_to ) ) {
			$message = __( '"Redirect From" and "Redirect To" values are required and should not match.', 'wpcom-legacy-redirector' );
			return new WP_Error( 'invalid-values', $message );
		}
		if ( 404 !== absint( self::check_if_404( home_url() . $from_url ) ) ) {
			$message = __( 'Redirects need to be from URLs that have a 404 status.', 'wpcom-legacy-redirector' );
			return new WP_Error( 'non-404', $message );
		}
		if ( 'private' === self::vip_legacy_redirect_check_if_public( $from_url ) ) {
			$message = __( 'You are trying to redirect from a URL that is currently private.', 'wpcom-legacy-redirector' );
			return new WP_Error( 'private-url', $message );
		}
		if ( 'private' === self::vip_legacy_redirect_check_if_public( $redirect_to ) && '/' !== $redirect_to ) {
			$message = __( 'You are trying to redirect to a URL that is currently not public.', 'wpcom-legacy-redirector' );
			return new WP_Error( 'non-public', $message );
		}
		if ( 'null' === self::vip_legacy_redirect_check_if_public( $redirect_to ) && '/' !== $redirect_to ) {
			$message = __( 'You are trying to redirect to a URL that does not exist.', 'wpcom-legacy-redirector' );
			return new WP_Error( 'invalid', $message );
		}
		return array( $from_url, $redirect_to );
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
		$url_query_params = self::mb_parse_url( $url, PHP_URL_QUERY );
		
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
	 * Get Redirect Destination URL.
	 *
	 * @param string $url URL to redirect (source).
	 * @return string|bool Redirect URL if one was found; otherwise false.
	 */
	static function get_redirect_uri( $url ) {
		$url = self::normalise_url( $url );
		if ( is_wp_error( $url ) ) {
			return false;
		}

		$preservable_params = self::get_preservable_querystring_params_from_url( $url );

		$url = remove_query_arg( array_keys( $preservable_params ), $url );

		$url_hash = self::get_url_hash( $url );

		$redirect_post_id = wp_cache_get( $url_hash, self::CACHE_GROUP );

		if ( false === $redirect_post_id ) {
			$redirect_post_id = self::get_redirect_post_id( $url );
			wp_cache_add( $url_hash, $redirect_post_id, self::CACHE_GROUP );
		}

		if ( $redirect_post_id ) {
			$redirect_post = get_post( $redirect_post_id );
			if ( ! $redirect_post instanceof WP_Post ) {
				// If redirect post object doesn't exist, reset cache.
				wp_cache_set( $url_hash, 0, self::CACHE_GROUP );

				return false;
			} elseif ( 0 !== $redirect_post->post_parent ) {
				// Add preserved params to the destination URL.
				return add_query_arg( $preservable_params, get_permalink( $redirect_post->post_parent ) );
			} elseif ( ! empty( $redirect_post->post_excerpt ) ) {
				// Add preserved params to the destination URL.
				// We need to add here the home_url() if the target starts with.
				$redirect_url = esc_url_raw( $redirect_post->post_excerpt );

				if ( substr( $redirect_post->post_excerpt, 0, 1 ) === '/' ) {
					$redirect_url = home_url() . $redirect_url;
				}

				return add_query_arg( $preservable_params, $redirect_url );
			}
		}

		return false;
	}

	/**
	 * Get Redirect Post ID.
	 *
	 * @param string $url URL to redirect (source).
	 * @return string|int Redirect post ID (as string) if one was found; otherwise 0.
	 */
	static function get_redirect_post_id( $url ) {
		global $wpdb;

		$url_hash = self::get_url_hash( $url );

		$redirect_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", self::POST_TYPE, $url_hash ) );

		if ( ! $redirect_post_id ) {
			$redirect_post_id = 0;
		}

		return $redirect_post_id;
	}

	/**
	 * Utility to get MD5 hash of URL.
	 *
	 * @param string $url URL to hash.
	 * @return string Hash representation of string.
	 */
	private static function get_url_hash( $url ) {
		return md5( $url );
	}

	/**
	 * UTF-8 aware parse_url() replacement.
	 *
	 * @throws \InvalidArgumentException Malformed URL.
	 * 
	 * @param string $url       The URL to parse.
	 * @param int    $component The specific component to retrieve. Use one of the
	 *                          PHP predefined constants to specify which one. Defaults
	 *                          to -1 (= return all parts as an array).
	 * @return array Exception on parse failure.
	 *               Array of URL components on success; When a specific component has been
	 *               requested: null if the component doesn't exist in the given URL; a
	 *               string (or in the case of PHP_URL_PORT, integer) when it does. 
	 */
	public static function mb_parse_url( $url, $component = -1 ) {
		$encoded_url = preg_replace_callback(
			'%[^:/@?&=#]+%usD',
			function ( $matches ) {
				return urlencode( $matches[0] );
			},
			$url
		);
	
		$parts = wp_parse_url( $encoded_url, $component );
	
		if ( false === $parts ) {
			throw new \InvalidArgumentException( 'Malformed URL: ' . $url );
		}
		
		if ( is_array( $parts ) ) {
			foreach ( $parts as $name => $value ) {
				$parts[ $name ] = urldecode( $value );
			}
		} else {
			$parts = urldecode( $parts );
		}

		return $parts;
	}

	/**
	 * Takes a request URL and "normalises" it, stripping common elements.
	 * Removes scheme and host from the URL, as redirects should be independent of these.
	 *
	 * @param string $url URL to transform.
	 * @return string|WP_Error Transformed URL; error if validation failed.
	 */
	private static function normalise_url( $url ) {
		// Sanitise the URL first rather than trying to normalise a non-URL.
		$url = esc_url_raw( $url );
		if ( empty( $url ) ) {
			return new WP_Error( 'invalid-redirect-url', 'The URL does not validate' );
		}

		// Break up the URL into it's constituent parts.
		$components = self::mb_parse_url( $url );

		// Avoid playing with unexpected data.
		if ( ! is_array( $components ) ) {
			return new WP_Error( 'url-parse-failed', 'The URL could not be parsed' );
		}

		// We should have at least a path or query.
		if ( ! isset( $components['path'] ) && ! isset( $components['query'] ) ) {
			return new WP_Error( 'url-parse-failed', 'The URL contains neither a path nor query string' );
		}

		// Make sure $components['query'] is set, to avoid errors.
		$components['query'] = ( isset( $components['query'] ) ) ? $components['query'] : '';

		// All we want is path and query strings
		// Note this strips hashes (#) too
		// @todo should we destory the query strings and rebuild with `add_query_arg()`?
		$normalised_url = $components['path'];

		// Only append '?' and the query if there is one.
		if ( ! empty( $components['query'] ) ) {
			$normalised_url = $components['path'] . '?' . $components['query'];
		}

		return $normalised_url;
	}

	/**
	 * Utility function to lowercase string.
	 *
	 * @param string $string To apply lowercase.
	 * @return string Lowercase representation of string.
	 */
	public static function lowercase( $string ) {
		return ! empty( $string ) ? strtolower( $string ) : $string;
	}

	/**
	 * Utility function to lowercase, trim, and remove trailing slashes from URL.
	 * Trailing slashes would not be removed if query string was present.
	 *
	 * @param string $url URL to be transformed.
	 * @return string Transformed URL.
	 */
	public static function transform( $url ) {
		return trim( self::lowercase( $url ), '/' );
	}

	/**
	 * Check redirect source and destination URL's are different.
	 *
	 * @param string $from_url    URL to redirect (source).
	 * @param string $redirect_to URL to redirect to (destination).
	 * @return bool True if URL's are different; false if they match or either param is empty.
	 */
	public static function validate( $from_url, $redirect_to ) {
		return ( ! empty( $from_url ) && ! empty( $redirect_to ) && self::transform( $from_url ) !== self::transform( $redirect_to ) );
	}

	/**
	 * Get response code to later check if URL is a 404.
	 *
	 * @param string $url The URL.
	 * @return int|string HTTP response code; empty string if no response code.
	 */
	public static function check_if_404( $url ) {
		if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
			$response = vip_safe_wp_remote_get( $url );
		} else {
			$response = wp_remote_get( $url );
			// If it was an error, try again with no SSL verification, in case it was a self-signed certificate: https://github.com/Automattic/WPCOM-Legacy-Redirector/issues/64.
			if ( is_wp_error( $response ) ) {
				$args     = [
					'sslverify' => false,
				];
				$response = wp_remote_get( $url, $args );
			}
		}
		$response_code = '';
		if ( is_array( $response ) ) {
			$response_code = wp_remote_retrieve_response_code( $response );
		}
		return $response_code;
	}

	/**
	 * Check if $redirect is a public Post.
	 *
	 * @param string $excerpt The Excerpt.
	 * @return string If post status not published returns 'private'; otherwise 'null'.
	 */
	public static function vip_legacy_redirect_check_if_public( $excerpt ) {
		$post_types = get_post_types();

		if ( function_exists( 'wpcom_vip_get_page_by_path' ) ) {
			$post_obj = wpcom_vip_get_page_by_path( $excerpt, OBJECT, $post_types );
		} else {
			$post_obj = get_page_by_path( $excerpt, OBJECT, $post_types );
		}
		if ( ! is_null( $post_obj ) ) {
			if ( 'publish' !== get_post_status( $post_obj->ID ) ) {
				return 'private';
			}
		} else {
			return 'null';
		}
	}

	/**
	 * Get the redirect URL to pass on to validate.
	 * We look for the excerpt, root, check if private, and check post parent IDs.
	 *
	 * @param object $post The Post.
	 * @return string The redirect URL.
	 */
	public static function get_redirect( $post ) {
		if ( has_excerpt( $post->ID ) ) {
			$excerpt = get_the_excerpt( $post->ID );

			// Check if redirect is a full URL or not.
			if ( 0 === strpos( $excerpt, 'http' ) ) {
				$redirect = $excerpt;
			} elseif ( '/' === $excerpt ) {
				$redirect = 'valid';
			} elseif ( 'private' === self::vip_legacy_redirect_check_if_public( $excerpt ) ) {
				$redirect = 'private';
			} else {
				$redirect = home_url() . $excerpt;
			}
		} else {
			// If it's not stored as an Excerpt, it will be stored as a post_parent ID.
			// Post Parent IDs are always internal redirects.
			$redirect = self::vip_legacy_redirect_parent_id( $post );
		}
		return $redirect;
	}

	/**
	 * Check if the excerpt is the home URL.
	 *
	 * @param string $excerpt The Excerpt of a post.
	 * @return bool True if is home URL matches param.
	 */
	public static function check_if_excerpt_is_home( $excerpt ) {
		if ( '/' === $excerpt || home_url() === $excerpt ) {
			return true;
		}
	}

	/**
	 * Run checks for the Post Parent ID of the redirect.
	 *
	 * @param object $post The Post.
	 * @return bool|string True on success, false if parent not found, 'private' if not published.
	 */
	public static function vip_legacy_redirect_parent_id( $post ) {
		if ( isset( $_POST['redirect_to'] ) && true !== self::check_if_excerpt_is_home( $post ) ) {
			if ( null !== get_post( $post ) && 'publish' === get_post_status( $post ) ) {
				return true;
			}
		} else {
			$parent = get_post( $post->post_parent );
			if ( null === get_post( $post->post_parent ) ) {
				return false;
			} elseif ( 'publish' !== get_post_status( $parent ) ) {
				return 'private';
			} else {
				$parent_slug = $parent->post_name;
				return $parent_slug;
			}
		}
	}
}
