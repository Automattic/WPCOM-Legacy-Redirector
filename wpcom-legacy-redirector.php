<?php
/**
 * Plugin Name: WPCOM Legacy Redirector
 * Plugin URI: https://github.com/Automattic/WPCOM-Legacy-Redirector
 * Description: Simple plugin for handling legacy redirects in a scalable manner.
 * Version: 1.4.0-alpha
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * Author: Automattic / WordPress VIP
 * Author URI: https://wpvip.com
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
 * @package Automattic\LegacyRedirector
 */

define( 'WPCOM_LEGACY_REDIRECTOR_FILE', __FILE__ );
define( 'WPCOM_LEGACY_REDIRECTOR_VERSION', '1.4.0-alpha' );

// Load Composer autoloader for PSR-4 classes (src/).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize the plugin via the bootstrapper.
$container    = \Automattic\LegacyRedirector\Infrastructure\DI\Container::instance();
$bootstrapper = new \Automattic\LegacyRedirector\Infrastructure\WordPress\PluginBootstrapper( $container );
$bootstrapper->init();

// Register WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// Register parent command for help text.
	WP_CLI::add_command(
		'wpcom-legacy-redirector',
		\Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\RedirectorCommand::class
	);
	WP_CLI::add_command(
		'wpcom-legacy-redirector find-domains',
		new \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\FindDomainsCommand()
	);
	WP_CLI::add_command(
		'wpcom-legacy-redirector insert-redirect',
		new \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\InsertRedirectCommand( $container->manager() )
	);
	WP_CLI::add_command(
		'wpcom-legacy-redirector import-from-meta',
		new \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ImportFromMetaCommand(
			$container->manager(),
			$container->inner_repository()
		)
	);
	WP_CLI::add_command(
		'wpcom-legacy-redirector import-from-csv',
		new \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ImportFromCsvCommand( $container->manager() )
	);
	WP_CLI::add_command(
		'wpcom-legacy-redirector export-to-csv',
		new \Automattic\LegacyRedirector\Infrastructure\WordPress\Cli\ExportToCsvCommand()
	);
}

/**
 * Get the plugin's DI container instance.
 *
 * This function is provided for third-party developers who need to access
 * plugin services. Internal plugin code should use Container::instance() directly.
 *
 * @return \Automattic\LegacyRedirector\Infrastructure\DI\Container The container.
 */
function wpcom_legacy_redirector_container(): \Automattic\LegacyRedirector\Infrastructure\DI\Container {
	return \Automattic\LegacyRedirector\Infrastructure\DI\Container::instance();
}
