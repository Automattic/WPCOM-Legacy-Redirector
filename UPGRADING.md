# Upgrading to WPCOM Legacy Redirector 2.0

This guide covers breaking changes and migration steps when upgrading from version 1.x to 2.0.

## Breaking Changes

### Removal of Lookup Class

The `Automattic\LegacyRedirector\Lookup` class has been removed. All redirect lookup functionality is now handled through the Domain-Driven Design (DDD) services via the `Container` class.

#### Migration Examples

**Before (1.x):**
```php
use Automattic\LegacyRedirector\Lookup;

// Get redirect URI
$redirect_uri = Lookup::get_redirect_uri( '/old-page' );

// Get redirect data
$redirect_data = Lookup::get_redirect_data( '/old-page' );
if ( $redirect_data ) {
    $uri    = $redirect_data['redirect_uri'];
    $status = $redirect_data['redirect_status'];
}

// Get redirect post ID
$post_id = Lookup::get_redirect_post_id( '/old-page' );

// Cache group constant
$cache_group = Lookup::CACHE_GROUP;
```

**After (2.0):**
```php
use Automattic\LegacyRedirector\Infrastructure\DI\Container;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use Automattic\LegacyRedirector\Infrastructure\WordPress\CachingRedirectRepository;

// Get redirect data (replaces get_redirect_uri)
$redirect_data = Container::instance()->executor()->get_redirect_data( '/old-page' );
if ( $redirect_data ) {
    $uri    = $redirect_data['url'];        // Note: key changed from 'redirect_uri'
    $status = $redirect_data['status_code']; // Note: key changed from 'redirect_status'
}

// Get redirect post ID
$source  = SourceUrl::from_string( '/old-page' );
$post_id = Container::instance()->inner_repository()->get_id_by_source( $source );

// Cache group constant
$cache_group = CachingRedirectRepository::CACHE_GROUP;
```

### Response Format Changes

The `get_redirect_data()` method now returns different array keys:

| 1.x Key | 2.0 Key |
|---------|---------|
| `redirect_uri` | `url` |
| `redirect_status` | `status_code` |

Additionally:
- 1.x returned `false` when no redirect was found
- 2.0 returns `null` when no redirect is found

### Removed Methods

The following public methods from the `Lookup` class are no longer available:

| Method | Replacement |
|--------|-------------|
| `Lookup::get_redirect_uri($url)` | `Container::instance()->executor()->get_redirect_data($url)['url']` |
| `Lookup::get_redirect_data($url)` | `Container::instance()->executor()->get_redirect_data($url)` |
| `Lookup::get_redirect_post_id($url)` | `Container::instance()->inner_repository()->get_id_by_source(SourceUrl::from_string($url))` |
| `Lookup::get_preservable_querystring_params_from_url($url)` | Handled automatically by the executor |

### Relocated Classes

Some utility classes have been moved to the Infrastructure layer:

| 1.x Location | 2.0 Location |
|--------------|--------------|
| `includes/class-capability.php` | `src/Infrastructure/WordPress/Capability.php` |
| `includes/class-post-type.php` | `src/Infrastructure/WordPress/PostType.php` |
| `includes/class-utils.php` | `src/Infrastructure/WordPress/UrlUtils.php` |

#### Class Renames

- `Automattic\LegacyRedirector\Post_Type` → `Automattic\LegacyRedirector\Infrastructure\WordPress\PostType`
- `Automattic\LegacyRedirector\Utils` → `Automattic\LegacyRedirector\Infrastructure\WordPress\UrlUtils`

### Query Parameter Preservation

The `get_preservable_querystring_params_from_url()` method is no longer public. The executor handles query parameter preservation automatically based on the `wpcom_legacy_redirector_preserve_query_params` filter.

If you need to extract preservable parameters manually, use this pattern:

```php
/**
 * Get preservable query parameters from a URL.
 *
 * @param string $url The URL to parse.
 * @return array Associative array of preserved key-value pairs.
 */
function get_preservable_params( string $url ): array {
    $keys = apply_filters( 'wpcom_legacy_redirector_preserve_query_params', array(), $url );

    if ( ! is_array( $keys ) || empty( $keys ) ) {
        return array();
    }

    $query_string = wp_parse_url( $url, PHP_URL_QUERY );
    if ( empty( $query_string ) ) {
        return array();
    }

    $params = array();
    parse_str( $query_string, $params );

    return array_intersect_key( $params, array_flip( $keys ) );
}
```

### Removal of WPCOM_Legacy_Redirector Class

The `WPCOM_Legacy_Redirector` class (`includes/class-wpcom-legacy-redirector.php`) has been entirely removed. All redirect creation functionality is now handled through the `RedirectManager` service.

#### Migration Examples

**Before (1.x):**
```php
// Insert a redirect (returns bool or WP_Error)
$result = WPCOM_Legacy_Redirector::insert_legacy_redirect( '/old-page', 'https://example.com/new-page', false );
if ( is_wp_error( $result ) ) {
    // Handle error
}

// Insert and get the redirect ID
$post_id = WPCOM_Legacy_Redirector::insert_legacy_redirect( '/old-page', 'https://example.com/new-page', false, true );
```

**After (2.0):**
```php
use Automattic\LegacyRedirector\Infrastructure\DI\Container;
use Automattic\LegacyRedirector\Domain\Destination;
use Automattic\LegacyRedirector\Domain\SourceUrl;

// Insert a redirect using the new API
$manager     = Container::instance()->manager();
$source      = SourceUrl::from_string( '/old-page' );
$destination = Destination::from_mixed( 'https://example.com/new-page' );
$result      = $manager->create_redirect( $source, $destination, $validate = false );

if ( $result->is_error() ) {
    // Handle error
    $error_code    = $result->error_code();
    $error_message = $result->error_message();
}

// Get the redirect ID
$redirect_id = $result->redirect_id();

// Redirect to a post ID
$source      = SourceUrl::from_string( '/another-page' );
$destination = Destination::from_mixed( 123 ); // post ID
$result      = $manager->create_redirect( $source, $destination );
```

### Removed WPCOM_Legacy_Redirector Methods

The following methods are no longer available:

| Method | Replacement |
|--------|-------------|
| `WPCOM_Legacy_Redirector::insert_legacy_redirect()` | `Container::instance()->manager()->create_redirect()` |
| `WPCOM_Legacy_Redirector::get_url_hash()` | `SourceUrl::from_string($url)->hash()` |
| `WPCOM_Legacy_Redirector::normalise_url()` | `SourceUrl::from_string($url)->path()` |
| `WPCOM_Legacy_Redirector::validate_urls()` | `Container::instance()->validator()->validate_for_creation()` |
| `WPCOM_Legacy_Redirector::validate()` | Removed (use validator service) |
| `WPCOM_Legacy_Redirector::transform()` | Removed (handled internally) |
| `WPCOM_Legacy_Redirector::lowercase()` | Removed (use `strtolower()`) |
| `WPCOM_Legacy_Redirector::get_redirect()` | Removed (use `Container::instance()->executor()->get_redirect_data()`) |
| `WPCOM_Legacy_Redirector::validate_destination_post_id()` | Handled by validator |
| `WPCOM_Legacy_Redirector::vip_legacy_redirect_parent_id()` | Handled internally by list table |
| `WPCOM_Legacy_Redirector::vip_legacy_redirect_check_if_public()` | Handled internally by list table |
| `WPCOM_Legacy_Redirector::check_if_excerpt_is_home()` | Handled internally |
| `WPCOM_Legacy_Redirector::check_if_404()` | Removed (used for validation, not redirects) |
| `WPCOM_Legacy_Redirector::throw_error()` | Removed (use RedirectCreationResult) |
| `WPCOM_Legacy_Redirector::modify_bulk_actions()` | Now in `BulkActionsHandler` class |
| `WPCOM_Legacy_Redirector::status_change_notices()` | Now in `StatusChangeNotices` class |
| `WPCOM_Legacy_Redirector::add_source_to_trash_redirect()` | Now in `TrashRedirectEnhancer` class |

### Using RedirectCreationResult

The new `create_redirect()` method returns a `RedirectCreationResult` object instead of mixed types:

```php
$result = $manager->create_redirect( $source, $destination, $validate );

// Check for errors
if ( $result->is_error() ) {
    echo $result->error_code();    // e.g., 'duplicate-redirect-uri'
    echo $result->error_message(); // Human-readable message
}

// Get the redirect ID on success
$id = $result->redirect_id();
```

## No Changes Required

The following APIs remain unchanged:

- All WordPress hooks and filters - Work the same
- WP-CLI commands - Work the same (internal implementation changed)

## Using the Container

The `Container` class provides access to all DDD services:

```php
use Automattic\LegacyRedirector\Infrastructure\DI\Container;

// Get the redirect executor (for lookups)
$executor = Container::instance()->executor();

// Get the repository (for direct database access)
$repository = Container::instance()->repository(); // With caching
$repository = Container::instance()->inner_repository(); // Without caching

// Get the validator
$validator = Container::instance()->validator();

// Get the manager (for admin operations)
$manager = Container::instance()->manager();
```

## Testing

If you have tests that depend on the `Lookup` class, update them to use the DDD services:

```php
// Before
$this->assertSame( $expected_uri, Lookup::get_redirect_uri( $url ) );

// After
$redirect_data = Container::instance()->executor()->get_redirect_data( $url );
$this->assertSame( $expected_uri, $redirect_data['url'] );
```

## Questions?

If you encounter issues upgrading, please open an issue at:
https://github.com/Automattic/WPCOM-Legacy-Redirector/issues
