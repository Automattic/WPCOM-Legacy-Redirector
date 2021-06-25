<?php
/**
 * Do not allow inserts to be enabled on the frontend on wpcom.
 *
 * This file is only loaded when the plugin is run on wordpress.com.
 *
 * @package WPCOM_Legacy_Redirect
 */

add_filter( 'wpcom_legacy_redirector_allow_insert', '__return_false', 9999 );
