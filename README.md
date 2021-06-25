# WPCOM Legacy Redirector

WordPress plugin for handling legacy redirects in a scalable manner.

Redirects are stored as a custom post type and use the following fields:

- `post_name` for the md5 hash of the "from" path or URL.
  - we use this column, since it's indexed and queries are super fast.
  - we also use an md5 just to simplify the storage.
- `post_title` to store the non-md5 version of the "from" path.
- one of either:
  - `post_parent` if we're redirect to a post; or
  - `post_excerpt` if we're redirecting to an alternate URL.

For detailed documentation, please see https://docs.wpvip.com/technical-references/redirects/wpcom-legacy-redirector-plugin/

Please contact us before using this plugin.

## Requirements

- PHP 5.6+
- WordPress 4.5+

## Change Log

See [CHANGELOG.md](CHANGELOG.md) for the full list of changes.

## License

Licensed under `GPL-2.0-or-later`.
