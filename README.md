# WPCOM Legacy Redirector

Stable tag: 1.4.0-alpha
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: redirects, 301, legacy, migration, seo
Contributors: automattic, WordPress VIP

A WordPress plugin for handling legacy redirects in a scalable manner. Designed for high-traffic sites with large volumes of redirects.

## At a Glance

- **Scalable**: Handles thousands of redirects efficiently using MD5-indexed lookups
- **Admin UI**: Add and manage redirects through the WordPress admin
- **WP-CLI support**: Bulk import/export via command line
- **Multisite compatible**: Works on single sites and multisite networks
- **Query parameter preservation**: Optionally preserve UTM and other tracking parameters
- **VIP-ready**: Built for WordPress VIP environments

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress admin
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Tools > Redirects** to add or manage redirects

## Usage

### Adding Redirects via Admin

1. Go to **Tools > Redirects > Add Redirect**
2. Enter the "Redirect From" path (e.g., `/old-page`)
3. Enter the "Redirect To" destination (URL or post ID)
4. Click "Add Redirect"

### Adding Redirects via WP-CLI

```bash
# Add a single redirect
wp wpcom-legacy-redirector insert-redirect /old-page https://example.com/new-page

# Redirect to an internal post by ID
wp wpcom-legacy-redirector insert-redirect /old-page 123

# Import redirects from CSV
wp wpcom-legacy-redirector import-from-csv /path/to/redirects.csv

# Export redirects to CSV
wp wpcom-legacy-redirector export-to-csv /path/to/export.csv
```

### Programmatic Usage

```php
// Add a redirect to an external URL
WPCOM_Legacy_Redirector::insert_legacy_redirect( '/old-page', 'https://example.com/new-page' );

// Add a redirect to an internal post
WPCOM_Legacy_Redirector::insert_legacy_redirect( '/old-page', $post_id );

// Check if a redirect exists
$redirect_uri = Automattic\LegacyRedirector\Lookup::get_redirect_uri( '/old-page' );
```

## How It Works

Redirects are stored as a custom post type (`vip-legacy-redirect`) with:

- **MD5 hash** of the source URL in `post_name` (indexed for fast lookups)
- **Original URL** in `post_title` (human-readable)
- **Destination** as either `post_parent` (internal) or `post_excerpt` (external)

The plugin intercepts 404 requests early (priority 0 on `template_redirect`) and performs a redirect if a match is found.

## Hooks and Filters

### Preserve Query Parameters

By default, query parameters are stripped during redirect lookup. To preserve specific parameters (like UTM codes):

```php
add_filter( 'wpcom_legacy_redirector_preserve_query_params', function( $params, $url ) {
    return array( 'utm_source', 'utm_medium', 'utm_campaign' );
}, 10, 2 );
```

### Modify Redirect Status Code

Change the HTTP status code (default: 301):

```php
add_filter( 'wpcom_legacy_redirector_redirect_status', function( $status, $url ) {
    return 302; // Temporary redirect
}, 10, 2 );
```

### Modify Request Path

Alter the path before redirect lookup:

```php
add_filter( 'wpcom_legacy_redirector_request_path', function( $path ) {
    return strtolower( $path ); // Case-insensitive matching
} );
```

## WP-CLI Commands

| Command | Description |
|---------|-------------|
| `insert-redirect` | Add a single redirect |
| `import-from-csv` | Bulk import from CSV file |
| `import-from-meta` | Import from post meta |
| `export-to-csv` | Export all redirects to CSV |
| `find-domains` | List destination domains |

For detailed command options, run `wp help wpcom-legacy-redirector`.

## Documentation

See the [Wiki](https://github.com/Automattic/WPCOM-Legacy-Redirector/wiki) for detailed documentation.

## Support

- **Bug reports & features**: [GitHub Issues](https://github.com/Automattic/WPCOM-Legacy-Redirector/issues)
- **VIP customers**: Contact [WordPress VIP Support](https://wpvip.com/wordpress-vip-enterprise-support/)

Please use GitHub Issues only for bug reports and feature requests, not general support questions.

## Contributing

We welcome contributions! See [CONTRIBUTING.md](./CONTRIBUTING.md) for guidelines.

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for the full list of changes.

## License

Licensed under `GPL-2.0-or-later`. See [LICENSE](./LICENSE) for details.
