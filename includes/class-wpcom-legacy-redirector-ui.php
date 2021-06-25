<?php
/**
 * User Interface
 *
 * @package WPCOM_Legacy_Redirect
 */

use \Automattic\LegacyRedirector\Capability;
use \Automattic\LegacyRedirector\Post_Type;

/**
 * User Interface.
 */
class WPCOM_Legacy_Redirector_UI {
	/**
	 * Constructor Class.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'validate_redirects_notices' ), 10, 2 );
		add_action( 'after_setup_theme', array( $this, 'validate_vip_legacy_redirect' ), 10, 2 );
		add_filter( 'removable_query_args', array( $this, 'add_removable_arg' ) );
		add_filter( 'views_edit-vip-legacy-redirect', array( $this, 'vip_redirects_custom_post_status_filters' ) );
	}
	/**
	 * Add Submenu Page.
	 */
	public function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Post_Type::POST_TYPE,
			__( 'Add Redirect', 'wpcom-legacy-redirector' ),
			__( 'Add Redirect', 'wpcom-legacy-redirector' ),
			Capability::MANAGE_REDIRECTS_CAPABILITY,
			'wpcom-legacy-redirector',
			array( $this, 'generate_page_html' )
		);
	}
	/**
	 * Set the $args that can be removed for validation purposes.
	 *
	 * @param array $args The Args: coming to a theatre near you.
	 */
	public function add_removable_arg( $args ) {
		array_push( $args, 'validate', 'ids' );
		return $args;
	}
	/**
	 * Notices for the redirect validation.
	 */
	public function validate_redirects_notices() {
		$redirect_not_valid_text = __( 'Redirect is not valid', 'wpcom-legacy-redirector' );

		$validate = filter_input( INPUT_GET, 'validate', FILTER_SANITIZE_STRING );
		if ( $validate ) {
			switch ( $validate ) {
				case 'invalid':
					echo '<div id="message" class="error notice is-dismissible"><p>' . esc_html( $redirect_not_valid_text ) . '<br />' . esc_html__( 'If you are doing an external redirect, make sure you safelist the domain using the "allowed_redirect_hosts" filter.', 'wpcom-legacy-redirector' ) . '</p></div>';
					break;
				case '404':
					echo '<div id="message" class="error notice is-dismissible"><p>' . esc_html( $redirect_not_valid_text ) . '<br />' . esc_html__( 'Redirect is pointing to a page with the HTTP status of 404.', 'wpcom-legacy-redirector' ) . '</p></div>';
					break;
				case 'valid':
					echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html__( 'Redirect Valid.', 'wpcom-legacy-redirector' ) . '</p></div>';
					break;
				case 'private':
					echo '<div id="message" class="error notice is-dismissible"><p>' . esc_html( $redirect_not_valid_text ) . '<br />' . esc_html__( 'The redirect is pointing to content that is not publiclly accessible.', 'wpcom-legacy-redirector' ) . '</p></div>';
					break;
				case 'null':
					echo '<div id="message" class="error notice is-dismissible"><p>' . esc_html( $redirect_not_valid_text ) . '<br />' . esc_html__( 'The redirect is pointing to a Post ID that does not exist.', 'wpcom-legacy-redirector' ) . '</p></div>';
			}
		}
	}

	/**
	 * Remove "draft" from the status filters for vip-legacy-redirect post type.
	 *
	 * @param array $views Status filters.
	 * @return array Modified status filters.
	 */
	public function vip_redirects_custom_post_status_filters( $views ) {
		unset( $views['draft'] );
		return $views;
	}


	/**
	 * Return error data when validate check fails.
	 *
	 * @param string $validate String that passes back the validate result in order to output the right notice.
	 * @param int    $post_id The Post ID.
	 */
	public function vip_legacy_redirect_sendback( $validate, $post_id ) {
		$sendback = remove_query_arg( array( 'validate', 'ids' ), wp_get_referer() );
			wp_safe_redirect(
				add_query_arg(
					array(
						'validate' => $validate,
						'ids'      => $post_id,
					),
					$sendback
				)
			);
			exit();
	}
	/**
	 * Validate the Redirect To URL.
	 */
	public function validate_vip_legacy_redirect() {
		if ( isset( $_GET['action'] ) && 'validate' === $_GET['action'] ) {
			$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
			$post    = get_post( $post_id );
			if ( ! isset( $_REQUEST['_validate_redirect'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_validate_redirect'] ), 'validate_vip_legacy_redirect' ) ) {
				return;
			} else {
				$redirect = WPCOM_Legacy_Redirector::get_redirect( $post );
				$status   = WPCOM_Legacy_Redirector::check_if_404( $redirect );

				// Check if $redirect is invalid.
				if ( ! wp_validate_redirect( $redirect, false ) ) {
					$this->vip_legacy_redirect_sendback( 'invalid', $post->ID );
				}
				// Check if $redirect is a 404.
				if ( 404 === $status ) {
					$this->vip_legacy_redirect_sendback( '404', $post->ID );
				}
				// Check if $redirect is not publicly visible.
				if ( 'private' === $redirect ) {
					$this->vip_legacy_redirect_sendback( 'private', $post->ID );
				}
				// Check if $redirect is pointing to a null Post ID.
				if ( 'null' === $redirect ) {
					$this->vip_legacy_redirect_sendback( 'null', $post->ID );
				}
				// Check if $redirect is valid.
				if ( wp_validate_redirect( $redirect, false ) && 404 !== $status || 'valid' === $redirect ) {
					$this->vip_legacy_redirect_sendback( 'valid', $post->ID );
				}
			}
		}
	}
	/**
	 * Validate the redirect that is being added.
	 */
	public function add_redirect_validation() {
		if ( ! current_user_can( Capability::MANAGE_REDIRECTS_CAPABILITY ) ) {
			return;
		}
		$errors   = array();
		$messages = array();
		if ( isset( $_POST['redirect_from'] ) && isset( $_POST['redirect_to'] ) ) {
			if (
				! isset( $_POST['redirect_nonce_field'] )
				|| ! wp_verify_nonce( sanitize_text_field( $_POST['redirect_nonce_field'] ), 'add_redirect_nonce' )
			) {
				$errors[] = array(
					'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
					'message' => __( 'Sorry, your nonce did not verify.', 'wpcom-legacy-redirector' ),
				);
			} else {
				$redirect_from = sanitize_text_field( $_POST['redirect_from'] );
				$redirect_to   = sanitize_text_field( $_POST['redirect_to'] );
				if ( WPCOM_Legacy_Redirector::validate( $redirect_from, $redirect_to ) ) {
					$output = WPCOM_Legacy_Redirector::insert_legacy_redirect( $redirect_from, $redirect_to, true );
					if ( true === $output ) {
						$link       = '<a href="' . esc_url( $redirect_from ) . '" target="_blank">' . esc_url( $redirect_from ) . '</a>';
						$messages[] = __( 'The redirect was added successfully. Check Redirect: ', 'wpcom-legacy-redirector' ) . $link;
					} elseif ( is_wp_error( $output ) ) {
						foreach ( $output->get_error_messages() as $error ) {
							$errors[] = array(
								'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
								'message' => $error,
							);
						}
					}
				} else {
					$errors[] = array(
						'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
						'message' => __( 'Check the values you are using to save the redirect. All fields are required. "Redirect From" and "Redirect To" should not match.', 'wpcom-legacy-redirector' ),
					);
				}
			}
		}
		return array( $errors, $messages );
	}
	/**
	 * Generate the Add Redirect page.
	 */
	public function generate_page_html() {
		$array = $this->add_redirect_validation();

		$errors   = $array[0];
		$messages = $array[1];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Not being saved directly, only used to pre-populate field if there was an error on the last submission.
		$redirect_from_value = isset( $_POST['redirect_from'], $errors[0] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_from'] ) ) : '/';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Not being saved directly, only used to pre-populate field if there was an error on the last submission.
		$redirect_to_value = isset( $_POST['redirect_to'], $errors[0] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) ) : '/';
		?>
		<style>
		#redirect_from_preview:not(:empty),
		#redirect_to_preview:not(:empty) {
			color: #666;
			float: left;
			width: 100%;
			margin-top: -17px;
		}
		@media (max-width: 782px) {
			#redirect_from_preview:not(:empty),
			#redirect_to_preview:not(:empty) {
				margin-top: 0;
			}
		}
		</style>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php if ( ! empty( $messages ) ) : ?>
				<div class="notice notice-success">
					<?php foreach ( $messages as $message ) : ?>
						<p><?php echo wp_kses_post( $message ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $errors ) ) : ?>
				<div class="notice notice-error">
					<?php foreach ( $errors as $error ) : ?>
						<p><strong><?php echo esc_html( $error['label'] ); ?></strong>: <?php echo esc_html( $error['message'] ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'add_redirect_nonce', 'redirect_nonce_field' ); ?>

				<table class="form-table">
					<tbody>
					<tr>
						<th>
							<label for="from_url"><?php esc_html_e( 'Redirect From', 'wpcom-legacy-redirector' ); ?></label>
						</th>
						<td>
							<p id="redirect_from_preview"></p>
							<input name="redirect_from" type="text" id="redirect_from" value="<?php echo esc_attr( $redirect_from_value ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'This path should be relative to the root, e.g. "/hello".', 'wpcom-legacy-redirector' ); ?></p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="redirect_to"><?php esc_html_e( 'Redirect To', 'wpcom-legacy-redirector' ); ?></label>
						</th>
						<td>
							<p id="redirect_to_preview"></p>
							<input name="redirect_to" type="text" id="redirect_to" value="<?php echo esc_attr( $redirect_to_value ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'To redirect to a post you can use the post_id, e.g. "100".', 'wpcom-legacy-redirector' ); ?></p>
						</td>
					</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Add Redirect', 'wpcom-legacy-redirector' ) ); ?>

			</form>

		</div>
		<?php
	}
}
