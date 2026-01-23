Feature: Import redirects from CSV
  As a site administrator
  I want to import redirects from a CSV file
  So that I can bulk create redirects

  Background:
    Given a WP installation with the WPCOM Legacy Redirector plugin

  Scenario: Import redirects from a valid CSV file
    Given there is a published post with a slug of "destination-post"
    And a CSV file "redirects.csv" with content:
      """
      /old-page,/destination-post
      /another-old-page,/destination-post
      """

    When I run `wp wpcom-legacy-redirector import-from-csv --csv=/tmp/redirects.csv --skip-validation`
    Then STDOUT should contain:
      """
      All of your redirects have been imported
      """

  Scenario: Import redirects with verbose output
    Given there is a published post with a slug of "verbose-destination"
    And a CSV file "verbose-redirects.csv" with content:
      """
      /verbose-test,/verbose-destination
      """

    When I run `wp wpcom-legacy-redirector import-from-csv --csv=/tmp/verbose-redirects.csv --skip-validation --verbose`
    Then STDOUT should contain:
      """
      Adding (CSV) redirect for /verbose-test to /verbose-destination
      """

  Scenario: Error when CSV file does not exist
    When I try `wp wpcom-legacy-redirector import-from-csv --csv=/tmp/nonexistent.csv`
    Then STDERR should contain:
      """
      Error: Invalid 'csv' file
      """
