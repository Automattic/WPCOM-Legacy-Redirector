Feature: Inserting a redirect
  As a user
  I want to insert a redirect
  So that specific requests are redirected

  Background:
    Given a WP installation with the WPCOM Legacy Redirector plugin

  Scenario: Insert a redirect to a path
    Given there is a published post with a slug of "bar"

    When I run `wp wpcom-legacy-redirector insert-redirect /foo /bar`
    Then STDOUT should contain:
      """
      Success: Inserted /foo -> /bar
      """

  @broken-wpenv
  Scenario: Insert a redirect to a safe URL
    # example.com seems to have an automatic bypass on allowed_redirect_hosts filter unlike other hosts.
    # In wp-env, this bypass doesn't work the same way.
    When I run `wp wpcom-legacy-redirector insert-redirect /foo https://example.com`
    Then STDOUT should contain:
      """
      Success: Inserted /foo -> https://example.com
      """

  Scenario: Redirect to disallowed host is not allowed
    When I try `wp wpcom-legacy-redirector insert-redirect /foo https://google.com`
    Then STDERR should contain:
      """
      Error: Couldn't insert /foo -> https://google.com (If you are doing an external redirect, make sure you safelist the domain using the "allowed_redirect_hosts" filter.)
      """

  @broken
  # Maybe Behat can't handle the wp_remote_get() check?
  Scenario: Can't insert a redirect from a page that doesn't have a 404 status
    Given there is a published post with a slug of "bar"

    When I try `wp wpcom-legacy-redirector insert-redirect /bar /hello-world`
    Then STDERR should contain:
      """
      Error: Couldn't insert /bar -> /hello-world (Redirects need to be from URLs that have a 404 status.)
      """

  Scenario: Can't insert a redirect to itself
    When I try `wp wpcom-legacy-redirector insert-redirect /foo /foo`
    Then STDERR should contain:
      """
      Error: Couldn't insert /foo -> /foo ("Redirect From" and "Redirect To" values are required and should not match.)
      """


  Scenario: Insert a redirect to a post ID
    Given I run `wp post create --post_title='Test post' --post_status="publish" --porcelain`
    And save STDOUT as {POST_ID}

    When I run `wp wpcom-legacy-redirector insert-redirect /foo1 {POST_ID}`
    Then STDOUT should contain:
      """
      Success: Inserted /foo1 -> {POST_ID}
      """
