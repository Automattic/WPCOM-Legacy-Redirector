# WPCOM Legacy Redirector

Handles redirects for legacy WordPress.com URLs.

## Plugin Details

| Property | Value |
|----------|-------|
| **Main file** | `wpcom-legacy-redirector.php` |
| **Text domain** | `wpcom-legacy-redirector` |
| **Function prefix** | `wpcom_legacy_redirector_` |
| **Namespace** | Global |
| **Source directory** | `includes/` |
| **Version** | 1.4.0-alpha |

## Architecture

- Uses custom post type for redirect storage
- WP-CLI commands for bulk operations
- `includes/` contains main classes

## Testing

```bash
composer test:unit          # Unit tests
composer test:integration   # Integration tests
composer behat              # Behat E2E tests
```

## Notes

- Tier 1 plugin (well-maintained)
- Has Behat tests
- Has WP-CLI integration

## Standards

Follow the standards documented in `~/code/plugin-standards/`.
