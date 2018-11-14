=== 404 Redirect Manager ===
Contributors: automattic, wpcomvip, batmoo, betzster, davidbinda, olope, emrikol, philipjohn, mikeyarce
Tags: redirects, redirector, redirect
Requires at least: 4.5
Tested up to: 4.5.3
Stable tag: 1.4.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple plugin for handling legacy redirects in a scalable manner.

== Description ==

This is a no-frills plugin (no UI, for example). Data entry needs to be bulk-loaded via the wp-cli commands provided or custom scripts.

Redirects are stored as a custom post type and use the following fields:
 - post_name for the md5 hash of the "from" path or URL.
  - we use this column, since it's indexed and queries are super fast.
  - we also use an md5 just to simplify the storage.
 - post_title to store the non-md5 version of the "from" path.
 - one of either:
  - post_parent if we're redirect to a post; or
  - post_excerpt if we're redirecting to an alternate URL.

Please contact us before using this plugin.

== Changelog ==

= 1.4.0 =
* Rename of plugin from WPCOM Legacy Redirector to 404 Redirect Manager
* Addition of UI for adding redirects
* Added validation for adding redirects
* Added `skip-validation` flag for CLI.

= 1.3.0 =
* Cleanup of PHP Notices
* Addition of `wpcom_404_redirect_manager_preserve_query_params` filter to allow for the whitelisting of params that should be passed through unmodified to the redirected url.

= 1.2.0 =
* Composer support
* Introduced `wpcom_404_redirect_manager_redirect_status` filter for redirect status code  (props spacedmonkey)
* Reset cache when a redirect post does not exist
* Introduce the `wpcom_404_redirect_manager_allow_insert` filter to enable inserts outside of WP CLI
* Fix for WP-CLI check

props spacedmonkey, bswatson 

= 1.1.0 =
* Introduce unit tests
* Fix bug with query string URLs

= 1.0.0 =
* Initial plugin

