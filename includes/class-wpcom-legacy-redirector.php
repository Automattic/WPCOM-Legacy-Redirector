<?php

class WPCOM_Legacy_Redirector {
	const POST_TYPE   = 'vip-legacy-redirect';
	const CACHE_GROUP = 'vip-legacy-redirect-2';

	static function start() {
		add_action( 'init', array( __CLASS__, 'init' ) );
		add_action( 'init', array( __CLASS__, 'register_redirect_custom_capability' ) );
		add_filter( 'template_redirect', array( __CLASS__, 'maybe_do_redirect' ), 0 ); // hook in early, before the canonical redirect
		add_action( 'admin_menu', array( new WPCOM_Legacy_Redirector_UI(), 'admin_menu' ) );
		add_filter( 'admin_enqueue_scripts', array( __CLASS__, 'wpcom_legacy_add_redirect_js' ) );

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
			'labels'          => $labels,
			'public'          => true,
			'rewrite'         => array( 'slug' => 'vip-legacy-redirect' ),
			'capability_type' => 'post',
			'hierarchical'    => false,
			'menu_position'   => 100,
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
			'menu_icon'       => 'dashicons-randomize',
			'supports'        => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
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

	static function maybe_do_redirect() {
		// Avoid the overhead of running this on every single pageload.
		// We move the overhead to the 404 page but the trade-off for site performance is worth it.
		if ( ! is_404() ) {
			return;
		}

		$request_path = apply_filters( 'wpcom_legacy_redirector_request_path', self::get_current_url_path() );

		if ( $request_path ) {
			$redirect_uri = self::get_redirect_uri( $request_path );

			if ( !$redirect_uri ) {
				// trailing slash agnostic support to check if redirect exists by toggling slash based on current URL.
				$request_path = apply_filters( 'wpcom_legacy_redirector_request_path', self::get_current_url_path( TRUE ) );
				$redirect_uri = self::get_redirect_uri( $request_path );
			}

			if ( $redirect_uri ) {
				header( 'X-legacy-redirect: HIT' );
				$redirect_status = apply_filters( 'wpcom_legacy_redirector_redirect_status', 301, $url );
				wp_safe_redirect( $redirect_uri, $redirect_status );
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
		wp_localize_script( 'wpcom-legacy-redirector', 'wpcomLegacyRedirector', array( 'siteurl' => get_option( 'siteurl' ) ) );
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
	 * Validate the URLs
	 *
	 * @param string $from_url
	 * @param string $redirect_to
	 * * @param bool $validate          Validate $from_url and $redirect_to values.
	 */
	static function validate_urls( $from_url, $redirect_to ) {
		if ( is_numeric( $redirect_to ) || false !== strpos( $redirect_to, 'http' ) ) {
			if ( is_numeric( $redirect_to ) && true !== self::vip_legacy_redirect_parent_id( $redirect_to ) ) {
				$message = __( 'Redirect is pointing to a Post ID that does not exist.', 'wpcom-legacy-redirector' );
				return new WP_Error( 'empty-postid', $message );
			}
			if ( ! wp_validate_redirect( $redirect_to ) ) {
				$message = __( 'If you are doing an external redirect, make sure you whitelist the domain using the "allowed_redirect_hosts" filter.', 'wpcom-legacy-redirector' );
				return new WP_Error( 'external-url-not-allowed', $message );
			}
			return array( $from_url, $redirect_to );
		}
		if ( false !== self::get_redirect_uri( $from_url ) ) {
			return new WP_Error( 'duplicate-redirect-uri', 'A redirect for this URI already exists' );
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
	static function get_redirect_uri( $url ) {

		$url = self::normalise_url( $url );
		if ( is_wp_error( $url ) ) {
			return false;
		}

		// White list of Params that should be pass through as is.
		$protected_params       = apply_filters( 'wpcom_legacy_redirector_preserve_query_params', array(), $url );
		$protected_param_values = array();
		$param_values           = array();

		// Parse URL to get Query Params.
		$query_params = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! empty( $query_params ) ) { // Verify Query Params exist.

			// Parse Query String to Associated Array.
			parse_str( $query_params, $param_values );
			// For every white listed param save value and strip from url
			foreach ( $protected_params as $protected_param ) {
				if ( ! empty( $param_values[ $protected_param ] ) ) {
					$protected_param_values[ $protected_param ] = $param_values[ $protected_param ];
					$url                                        = remove_query_arg( $protected_param, $url );
				}
			}
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
				return add_query_arg( $protected_param_values, get_permalink( $redirect_post->post_parent ) ); // Add Whitelisted Params to the Redirect URL.
			} elseif ( ! empty( $redirect_post->post_excerpt ) ) {
				return add_query_arg( $protected_param_values, esc_url_raw( $redirect_post->post_excerpt ) ); // Add Whitelisted Params to the Redirect URL
			}
		}

		return false;
	}

	static function get_redirect_post_id( $url ) {
		global $wpdb;

		$url_hash = self::get_url_hash( $url );

		$redirect_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", self::POST_TYPE, $url_hash ) );

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
	/**
	 * Check if URL is a 404.
	 *
	 * @param string $url The URL.
	 */
	public static function check_if_404( $url ) {

		if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
			$response = vip_safe_wp_remote_get( $url );
		} else {
			$response = wp_remote_get( $url );
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
	 * We look for the excerpt, root, check if private, and check post parent IDs
	 *
	 * @param array $post The post array.
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

	/**
 	 * Returns current URL's relative path including the query string. Optionally add or remove trailig slash to get variant URL.
 	 * @param bool | $trailing_slash_toggle If TRUE, will return URL toggling the trailing slash on/off (i.e add slash if not present).
 	 * @return string | Will return the current URL path based via $_SERVER.
 	 */
	static function get_current_url_path( $trailing_slash_toggle = false ) {

		$url = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

		// Add the trailing slash if not present; or vice-versa to toggle it.
		if ( $trailing_slash_toggle ) {
			if ( substr( $url , -1 ) == '/' ) {
				$url = rtrim( $url, '/' );
			}
			else {
				$url .= "/";
			}			
		}
	
		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			$url .= '?' . $_SERVER['QUERY_STRING'];
		}

		return $url;

	}
}
