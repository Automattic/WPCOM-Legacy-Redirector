<?php

class WPCOM_Legacy_Redirector_UI {

    public function admin_menu() {
        add_menu_page( 'WPCOM Legacy Redirector', 'Redirect Manager', 'manage_options', 'wpcom-legacy-redirector', array(
            $this,
            'generate_page_html',
        ), 'dashicons-external' );
    }

    public function generate_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $errors   = array();
        $messages = array();
        if ( class_exists( 'WPCOM_Legacy_Redirector' ) ) {
            if ( isset( $_POST['from_url'] ) && isset( $_POST['redirect_to'] ) ) {
                if (
                    ! isset( $_POST['redirect_nonce_field'] )
                    || ! wp_verify_nonce( $_POST['redirect_nonce_field'] )
                ) {
                    $errors[] = array(
                        'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
                        'message' => __( 'Sorry, your nonce did not verify.', 'wpcom-legacy-redirector' ),
                    );
                } else {
                    $from_url    = sanitize_text_field( $_POST['from_url'] );
                    $redirect_to = sanitize_text_field( $_POST['redirect_to'] );
                    if ( WPCOM_Legacy_Redirector::validate( $from_url, $redirect_to ) ) {
                        $output = WPCOM_Legacy_Redirector::insert_legacy_redirect( $from_url, $redirect_to, false );
                        if ( true === $output ) {
                            $link       = '<a href="' . esc_url( $from_url ) . '" target="_blank">' . esc_url( $from_url ) . '</a>';
                            $messages[] = __( 'The redirect was added successfully. Check Redirect: ', 'wpcom-legacy-redirector' ) . $link;
                        } else {
                            if ( false === $output ) {
                                $errors[] = array(
                                    'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
                                    'message' => __( 'Redirect could not be saved. Contact administrator and check permissions.', 'wpcom-legacy-redirector' ),
                                );
                            } elseif ( is_wp_error( $output ) ) {
                                foreach ( $output->get_error_messages() as $error ) {
                                    $errors[] = array(
                                        'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
                                        'message' => $error,
                                    );
                                }
                            }
                        }
                    } else {
                        $errors[] = array(
                            'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
                            'message' => __( 'Check the values you are using to save the redirect. 
                            All fields are required. "Redirect From" and "Redirect To" should not match.', 'wpcom-legacy-redirector' ),
                        );
                    }
                }
            }
        } else {
            $errors[] = array(
                'label'   => __( 'Error', 'wpcom-legacy-redirector' ),
                'message' => __( 'WPCOM Legacy Redirector plugin is required to add redirects.', 'wpcom-legacy-redirector' ),
            );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Add Redirect', 'wpcom-legacy-redirector' ); ?></h1>
            <?php if ( count( $messages ) ) : ?>
                <div class="notice notice-success">
                    <?php foreach ( $messages as $message ) : ?>
                        <p><?php echo wp_kses_post( $message ); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ( count( $errors ) ) : ?>
                <div class="notice notice-error">
                    <?php foreach ( $errors as $error ) : ?>
                        <p><strong><?php echo esc_html( $error['label'] ); ?></strong>: <?php echo esc_html( $error['message'] ); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( - 1, 'redirect_nonce_field' ); ?>

                <table class="form-table">
                    <tbody>
                    <tr>
                        <th>
                            <label for="from_url"><?php esc_html_e( 'Redirect From', 'wpcom-legacy-redirector' ); ?></label>
                        </th>
                        <td>
                            <input name="from_url" type="text" id="from_url" value="" class="regular-text">
                            <p class="description"><?php esc_html_e( 'This path should be relative to the root, e.g. "/hello".', 'wpcom-legacy-redirector' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th>
                            <label for="redirect_to"><?php esc_html_e( 'Redirect To', 'wpcom-legacy-redirector' ); ?></label>
                        </th>
                        <td>
                            <input name="redirect_to" type="text" id="redirect_to" value="" class="regular-text">
                            <p class="description"><?php esc_html_e( 'To redirect to a post you can use the post_id, e.g. "100".', '' ); ?></p>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary"
                           value="<?php esc_attr_e( 'Add Redirect', 'wpcom-legacy-redirector' ); ?>">
                </p>
            </form>

        </div>
        <?php
    }
}
