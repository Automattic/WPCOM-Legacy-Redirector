# WPCOM Legacy Redirector

Handles redirects for legacy WordPress.com URLs.

## Plugin Details

| Property | Value |
|----------|-------|
| **Main file** | `wpcom-legacy-redirector.php` |
| **Text domain** | `wpcom-legacy-redirector` |
| **Namespace** | `Automattic\LegacyRedirector` |
| **Source directory** | `src/` |
| **Version** | 2.0.0-alpha |

## Architecture

Uses Domain-Driven Design (DDD) with three layers:

- **Domain** (`src/Domain/`) - Value objects, entities, repository interfaces
- **Application** (`src/Application/`) - Use cases and services
- **Infrastructure** (`src/Infrastructure/`) - WordPress integration, persistence

Key classes:
- `RedirectManager` - Creates, updates, deletes redirects
- `RedirectExecutor` - Handles redirect resolution at runtime
- `RedirectValidator` - Validates redirect rules
- WP-CLI commands for bulk operations

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
