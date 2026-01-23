Feature: Import redirects from post meta
  As a site administrator
  I want to import redirects from post meta values
  So that I can bulk create redirects from existing URL metadata

  Background:
    Given a WP installation with the WPCOM Legacy Redirector plugin

  Scenario: Error when no meta values found
    When I try `wp wpcom-legacy-redirector import-from-meta --meta_key=nonexistent_meta_key`
    Then STDERR should contain:
      """
      Error:
      """
