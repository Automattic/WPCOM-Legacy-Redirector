<?php
/**
 * Mangage the redirects list.
 *
 * @package WPCOM_Legacy_Redirect
 */

namespace Automattic\LegacyRedirector;

/**
 * Control how redirects are listed in UI.
 */
final class List_Redirects {

	/**
	 * Get hooked in!
	 */
	public function init() {
		add_filter( 'manage_vip-legacy-redirect_posts_columns', array( $this, 'set_columns' ) );
		add_action( 'manage_vip-legacy-redirect_posts_custom_column', array( $this, 'posts_custom_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'modify_list_row_actions' ), 10, 2 );
	}

	/**
	 * Set Columns for Redirect Table.
	 *
	 * @param array $columns Columns to show for the post type.
	 */
	public function set_columns( $columns ) {
		return array(
			'cb'   => '<input type="checkbox" />',
			'from' => __( 'Redirect From', 'wpcom-legacy-redirector' ),
			'to'   => __( 'Redirect To', 'wpcom-legacy-redirector' ),
			'date' => __( 'Date', 'wpcom-legacy-redirector' ),
		);
	}

	/**
	 * Add the data to the custom columns for the vip-legacy-redirects post type.
	 * Provide warnings for possibly bad redirects.
	 *
	 * @param string $column  The Column for the post_type table.
	 * @param int    $post_id The Post ID.
	 */
	public function posts_custom_column( $column, $post_id ) {
		switch ( $column ) {
			case 'from':
				echo esc_html( get_the_title( $post_id ) );
				break;
			case 'to':
				$post    = get_post( $post_id );
				$excerpt = get_the_excerpt( $post_id );
				$parent  = get_post( $post->post_parent );

				// Check if the Post is Published.
				if ( ! empty( $excerpt ) ) {
					// Check if it's the Home URL.
					if ( true === \WPCOM_Legacy_Redirector::check_if_excerpt_is_home( $excerpt ) ) {
						echo esc_html( $excerpt );
					} elseif ( 0 === strpos( $excerpt, 'http' ) ) {
						echo esc_url_raw( $excerpt );
					} else {
						if ( 'private' === \WPCOM_Legacy_Redirector::vip_legacy_redirect_check_if_public( $excerpt ) ) {
							echo esc_html( $excerpt ) . '<br /><em>' . esc_html__( 'Warning: Redirect is not a public URL.', 'wpcom-legacy-redirector' ) . '</em>';
						} else {
							echo esc_html( $excerpt );
						}
					}
				} else {
					switch ( \WPCOM_Legacy_Redirector::vip_legacy_redirect_parent_id( $post ) ) {
						case false:
							echo '<em>' . esc_html__( 'Redirect is pointing to a Post ID that does not exist.', 'wpcom-legacy-redirector' ) . '</em>';
							break;
						case 'private':
							echo ( esc_html( get_permalink( $parent ) ) . '<br /><em>' . esc_html__( 'Warning: Redirect is not a public URL.', 'wpcom-legacy-redirector' ) . '</em>' );
							break;
						default:
							echo esc_html( str_replace( home_url(), '', get_permalink( $parent ) ) );
					}
				}
				break;
		}
	}

	/**
	 * Modify the Row Actions for the vip-legacy-redirect post type.
	 *
	 * @param array  $actions Default Actions.
	 * @param object $post the current Post.
	 * @return array Modified Actions.
	 */
	public function modify_list_row_actions( $actions, $post ) {
		// Check for your post type.
		if ( Post_Type::POST_TYPE === $post->post_type ) {
			$url = admin_url( 'post.php?post=vip-legacy-redirect&post=' . $post->ID );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status'] ) {
				return $actions;
			}
			$trash   = $actions['trash'];
			$actions = array();

			if ( current_user_can( Capability::MANAGE_REDIRECTS_CAPABILITY ) ) {
				// Add a nonce to Validate Link.
				$validate_link = wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'validate',
						),
						$url
					),
					'validate_vip_legacy_redirect',
					'_validate_redirect'
				);

				// Add the Validate Link.
				$actions = array_merge(
					$actions,
					array(
						'validate' => sprintf(
							'<a href="%1$s">%2$s</a>',
							esc_url( $validate_link ),
							'Validate'
						),
					)
				);
				// Re-insert thrash link preserved from the default $actions.
				$actions['trash'] = $trash;
			}
		}
		return $actions;
	}
}
