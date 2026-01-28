Feature: Inserting a redirect
  As a user
  I want to insert a redirect
  So that specific requests are redirected

  Background:
    Given a WP installation with the WPCOM Legacy Redirector plugin

  # Smoke test: basic redirect insertion works via CLI.
  Scenario: Insert a redirect to a path
    Given there is a published post with a slug of "bar"

    When I run `wp wpcom-legacy-redirector insert-redirect /foo /bar`
    Then STDOUT should contain:
      """
      Success: Inserted /foo -> /bar
      """

  # Contract test: verifies allowed_redirect_hosts filter is respected.
  # This filter behaviour can't be easily tested in PHPUnit integration tests.
  Scenario: Redirect to disallowed host is not allowed
    When I try `wp wpcom-legacy-redirector insert-redirect /foo https://google.com`
    Then STDERR should contain:
      """
      Error: Couldn't insert /foo -> https://google.com (If you are doing an external redirect, make sure you safelist the domain using the "allowed_redirect_hosts" filter.)
      """
