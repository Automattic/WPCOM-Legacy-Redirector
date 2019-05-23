# Change Log for WPCOM Legacy Redirector

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Requires PHP 5.6.

### Added
- Admin pages to view, add, delete, and validate redirects. Uses new `manage_redirects` capability. 
- `wpcom-legacy-redirector find-domains` CLI command. For sites that need to update their allowed_redirect_hosts filter, this command will list all unique domains that are redirected to.
- CI tests for PHP 7.0 and 7.1.
- Output errors for failed imports of redirects.
- Progress bar to `import-from-meta` command.
- `--verbose` flag to `import-from-meta` and `import-from-csv` commands.
- `--skip-validation` flag, but set validation to true by default.

## Changed
- Improved adherence to WPCS and VIPCS coding standards.
- Drop PHP 5.3 support.
- Use WP_CLI:error to halt operation on failed insert using `insert-redirect`.
- Return an error if no redirects were found for a meta key.
- Added performance improvement for `import-from-meta` command.
- Improved CLI commands documentation.
- Project / code cleanup.

## Fixed
- Trim whitespace around CSV file path, to support dragging a file into the terminal window to add the path.
- Ensure `POST` var is set during CLI command.


## [1.3.0] - 2016-03-29

### Added
- `wpcom_legacy_redirector_preserve_query_params` filter to allow for the whitelisting of params that should be passed through to the redirected URL.

## Changed
- Updated logic to check `wp_parse_url()` query component as the Request value will not be set for test purposes.
- Updated unit tests.

### Fixed
- Fix "Undefined variable $row at line 98" PHP notice.

## [1.2.0] - 2016-07-07

### Added

- Composer support
- `wpcom_legacy_redirector_redirect_status` filter for redirect status code (props spacedmonkey)
- `wpcom_legacy_redirector_redirect_allow_insert` filter to enable inserts outside of WP-CLI.

### Fixed
- Reset cache when a redirect post does not exist.
- Fix for WP-CLI check.

## [1.1.0] - 2016-03-29

### Added
- Unit tests

### Fixed
- Fix bug with query string URLs

## 1.0.0 - 2016-02-27

Initial release.

[Unreleased]: https://github.com/Automattic/WPCOM-Legacy-Redirector/compare/1.3.0...HEAD
[1.3.0]: https://github.com/Automattic/WPCOM-Legacy-Redirector/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/Automattic/WPCOM-Legacy-Redirector/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/Automattic/WPCOM-Legacy-Redirector/compare/1.0.0...1.1.0
