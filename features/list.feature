Feature: List redirects
  As a site administrator
  I want to list all redirects
  So that I can see what redirects exist

  Background:
    Given a WP installation with the WPCOM Legacy Redirector plugin

  # Smoke test: verifies basic list output format via CLI.
  Scenario: List all redirects
    Given there is a published post with a slug of "list-destination"
    And there is a redirect from "/list-test-1" to "/list-destination"
    And there is a redirect from "/list-test-2" to "/list-destination"

    When I run `wp wpcom-legacy-redirector list`
    Then STDOUT should contain:
      """
      /list-test-1
      """
    And STDOUT should contain:
      """
      /list-test-2
      """
