<?php
/**
 * Set up the post type for the plugin.
 *
 * @package WPCOM_Legacy_Redirect
 */

namespace Automattic\LegacyRedirector;

/**
 * Registers the post type
 */
final class Post_Type {
	const POST_TYPE = 'vip-legacy-redirect';

	/**
	 * Register the post type
	 */
	public function register() {
		register_post_type( self::POST_TYPE, $this->get_args() );
	}

	/**
	 * Post type labels
	 *
	 * @return array Labels used by the post type.
	 */
	protected function get_labels() {
		return array(
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
	}

	/**
	 * Post type arguments
	 *
	 * @return array Arguments used by the post type.
	 */
	protected function get_args() {
		return array(
			'labels'             => $this->get_labels(),
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
			'supports'           => array( 'page-attributes' ),
		);
	}
}
