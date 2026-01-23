Feature: Export redirects to CSV
  As a site administrator
  I want to export redirects to a CSV file
  So that I can backup or migrate redirects

  Background:
    Given a WP installation with the WPCOM Legacy Redirector plugin

  Scenario: Export redirects to a CSV file
    Given there is a published post with a slug of "export-destination"
    And there is a redirect from "/export-test-1" to "/export-destination"

    When I run `wp wpcom-legacy-redirector export-to-csv --csv=/tmp/exported-redirects.csv --overwrite`
    Then the return code should be 0

  Scenario: Error when no filename provided
    When I try `wp wpcom-legacy-redirector export-to-csv`
    Then STDERR should contain:
      """
      missing --csv parameter
      """
