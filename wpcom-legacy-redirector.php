<?php
/**
 * Plugin Name: WPCOM Legacy Redirector
 * Plugin URI: https://vip.wordpress.com/plugins/wpcom-legacy-redirector/
 * Description: Simple plugin for handling legacy redirects in a scalable manner.
 * Version: 1.4.0-alpha
 * Requires PHP: 5.6
 * Author: Automattic / WordPress.com VIP
 * Author URI: https://vip.wordpress.com
 *
 * @package WPCOM_Legacy_Redirect
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

define( 'WPCOM_LEGACY_REDIRECTOR_VERSION', '1.4.0-alpha' );
define( 'WPCOM_LEGACY_REDIRECTOR_PLUGIN_NAME', 'WPCOM Legacy Redirector' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require __DIR__ . '/includes/class-wpcom-legacy-redirector-cli.php';
	WP_CLI::add_command( 'wpcom-legacy-redirector', 'WPCOM_Legacy_Redirector_CLI' );
}

require __DIR__ . '/includes/class-capability.php';
require __DIR__ . '/includes/class-post-type.php';
require __DIR__ . '/includes/class-list-redirects.php';
require __DIR__ . '/includes/class-lookup.php';
require __DIR__ . '/includes/class-wpcom-legacy-redirector-ui.php';
require __DIR__ . '/includes/class-wpcom-legacy-redirector.php';

WPCOM_Legacy_Redirector::start();
