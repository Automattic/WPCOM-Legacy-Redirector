<?php
/**
 * Feature tests context class for wp-env based Behat testing.
 *
 * @package Automattic\LegacyRedirector
 */

namespace Automattic\LegacyRedirector\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use RuntimeException;

/**
 * Feature tests context class for wp-env based Behat testing.
 *
 * This class implements custom step definitions for testing WPCOM Legacy Redirector
 * WP-CLI commands using wp-env instead of the wp-cli/wp-cli-tests framework.
 */
final class FeatureContext implements Context {

	/**
	 * Command output (STDOUT).
	 *
	 * @var string
	 */
	private $output = '';

	/**
	 * Command error output (STDERR).
	 *
	 * @var string
	 */
	private $error_output = '';

	/**
	 * Command exit code.
	 *
	 * @var int
	 */
	private $exit_code = 0;

	/**
	 * Previous command for "run previous command again" step.
	 *
	 * @var string|null
	 */
	private $previous_command = null;

	/**
	 * Saved variables from STDOUT.
	 *
	 * @var array<string, string>
	 */
	private $saved_variables = array();

	/**
	 * Hosts added to allowed_redirect_hosts for cleanup.
	 *
	 * @var string[]
	 */
	private $added_hosts = array();

	/**
	 * Execute a WP-CLI command inside wp-env tests container.
	 *
	 * @param string $command     The WP-CLI command to execute (without 'wp' prefix).
	 * @param bool   $should_fail Whether the command is expected to fail.
	 * @return void
	 */
	private function run_wp_cli_command( $command, $should_fail = false ): void {
		// Replace saved variables in command.
		foreach ( $this->saved_variables as $var => $value ) {
			$command = str_replace( '{' . $var . '}', $value, $command );
		}

		// Escape command for shell execution.
		$escaped_command = str_replace( "'", "'\\''", $command );

		// Run inside wp-env tests-cli container.
		$exec_command = sprintf(
			"npx wp-env run tests-cli --env-cwd=wp-content/plugins/WPCOM-Legacy-Redirector bash -c 'wp %s 2>&1'",
			$escaped_command
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Required for wp-env CLI testing.
		exec( $exec_command, $output_lines, $exit_code );

		// Filter out wp-env status messages.
		$filtered_lines = array_filter(
			$output_lines,
			function ( $line ) use ( $output_lines ) {
				// Remove wp-env status lines.
				return ! ( 0 === strpos( $line, 'ℹ ' ) ||
						0 === strpos( $line, '✔ ' ) ||
						0 === strpos( $line, '✖ ' ) ||
						( '' === trim( $line ) && count( $output_lines ) > 1 ) );
			}
		);

		$output             = implode( "\n", $filtered_lines );
		$this->output       = $output;
		$this->error_output = '';
		$this->exit_code    = $exit_code;

		// Parse STDERR from combined output.
		// WP-CLI prefixes errors with "Error:" typically.
		if ( 0 !== $exit_code || $should_fail ) {
			// Extract error lines and their continuation lines.
			$error_lines    = array();
			$in_error_block = false;

			foreach ( $filtered_lines as $line ) {
				// Check if this line starts an error block.
				if ( 0 === strpos( $line, 'Error:' ) || 0 === strpos( $line, 'Warning:' ) ) {
					$error_lines[]  = $line;
					$in_error_block = true;
				} elseif ( $in_error_block && ( 0 === strpos( $line, ' ' ) || 0 === strpos( $line, "\t" ) ) ) {
					// Continuation line (indented).
					$error_lines[] = $line;
				} else {
					// Not an error line, end the error block.
					$in_error_block = false;
				}
			}

			if ( ! empty( $error_lines ) ) {
				$this->error_output = implode( "\n", $error_lines );
				// Remove error lines from output.
				$non_error_lines = array_diff( $filtered_lines, $error_lines );
				$this->output    = implode( "\n", $non_error_lines );
			}
		}
	}

	/**
	 * Reset database state between scenarios.
	 *
	 * This ensures test isolation without recreating WordPress.
	 *
	 * @return void
	 */
	private function reset_database_state(): void {
		// Delete all redirect posts (custom post type).
		$this->run_wp_cli_command( 'post list --post_type=vip-legacy-redirect --format=ids', false );
		$redirect_ids = trim( $this->output );

		if ( ! empty( $redirect_ids ) ) {
			$this->run_wp_cli_command( "post delete {$redirect_ids} --force", false );
		}

		// Delete all regular posts except defaults.
		$this->run_wp_cli_command( 'post list --post_type=post --format=ids', false );
		$post_ids = trim( $this->output );

		if ( ! empty( $post_ids ) ) {
			$this->run_wp_cli_command( "post delete {$post_ids} --force", false );
		}

		// Remove mu-plugins created for allowed_redirect_hosts.
		foreach ( $this->added_hosts as $host ) {
			$this->run_wp_cli_command(
				sprintf(
					"eval 'if ( file_exists( WPMU_PLUGIN_DIR . \"/allowed_redirect_hosts-%s.php\" ) ) { unlink( WPMU_PLUGIN_DIR . \"/allowed_redirect_hosts-%s.php\" ); echo \"deleted\"; }'",
					$host,
					$host
				),
				false
			);
		}
		$this->added_hosts = array();

		// Clean transients.
		$this->run_wp_cli_command( 'transient delete --all', false );

		// Flush cache.
		$this->run_wp_cli_command( 'cache flush', false );

		// Reset saved variables.
		$this->saved_variables = array();
	}

	/**
	 * Set up clean state before each scenario.
	 *
	 * @BeforeScenario
	 * @param BeforeScenarioScope $scope Scenario scope.
	 * @return void
	 */
	public function before_scenario( BeforeScenarioScope $scope ): void {
		// Create an mu-plugin to bypass 404 validation for tests.
		// In wp-env Docker containers, HTTP requests to localhost don't work reliably.
		$bypass_validation = <<<'PHP'
<?php
/**
 * Bypass 404 validation for Behat tests.
 *
 * In wp-env, HTTP requests from inside the container don't work the same way,
 * so we need to bypass the 404 check that happens in insert_redirect().
 */
add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
	// Only bypass for requests to our own site.
	if ( false !== strpos( $url, home_url() ) ) {
		return array(
			'response' => array(
				'code' => 404,
			),
		);
	}
	return $preempt;
}, 10, 3 );
PHP;

		// Use base64 encoding to avoid shell escaping issues.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding for shell safety, not obfuscation.
		$encoded = base64_encode( $bypass_validation );

		// Ensure mu-plugins directory exists, then create the bypass file.
		$this->run_wp_cli_command(
			sprintf(
				'eval \'if ( ! is_dir( WPMU_PLUGIN_DIR ) ) { mkdir( WPMU_PLUGIN_DIR, 0755, true ); } file_put_contents( WPMU_PLUGIN_DIR . "/behat-bypass-404-check.php", base64_decode( "%s" ) );\'',
				$encoded
			),
			false
		);
	}

	/**
	 * Clean up after each scenario.
	 *
	 * @AfterScenario
	 * @param AfterScenarioScope $scope Scenario scope.
	 * @return void
	 */
	public function after_scenario( AfterScenarioScope $scope ): void {
		// Clean up database state after scenario.
		$this->reset_database_state();

		// Remove the bypass 404 check mu-plugin.
		$this->run_wp_cli_command(
			'eval \'if ( file_exists( WPMU_PLUGIN_DIR . "/behat-bypass-404-check.php" ) ) { unlink( WPMU_PLUGIN_DIR . "/behat-bypass-404-check.php" ); }\'',
			false
		);
	}

	/**
	 * Set up a basic WP installation.
	 *
	 * @Given a WP install
	 * @Given a WP installation
	 * @return void
	 */
	public function given_a_wp_installation(): void {
		// wp-env is already running with WordPress installed.
		// Just ensure we have a clean database state.
		$this->reset_database_state();
	}

	/**
	 * Set up a WP installation with WPCOM Legacy Redirector plugin activated.
	 *
	 * @Given a WP install(ation) with the WPCOM Legacy Redirector plugin
	 * @throws RuntimeException If plugin activation fails.
	 * @return void
	 */
	public function given_a_wp_installation_with_the_wpcomlr_plugin(): void {
		// Plugin is already loaded via .wp-env.json.
		// Reset database and ensure plugin is activated.
		$this->reset_database_state();

		// Try activating with the full plugin path (handles different folder names in CI vs local).
		// In CI, the folder is WPCOM-Legacy-Redirector (from GitHub repo name).
		$this->run_wp_cli_command( 'plugin activate WPCOM-Legacy-Redirector/wpcom-legacy-redirector.php', false );

		// If that fails, try the lowercase slug (for local development).
		if ( 0 !== $this->exit_code ) {
			$this->run_wp_cli_command( 'plugin activate wpcom-legacy-redirector', false );
		}

		// Check if plugin is active regardless of activation command result.
		// The plugin might already be active, which would cause activation to "fail".
		$this->run_wp_cli_command( 'plugin is-active WPCOM-Legacy-Redirector/wpcom-legacy-redirector.php', false );
		if ( 0 !== $this->exit_code ) {
			$this->run_wp_cli_command( 'plugin is-active wpcom-legacy-redirector', false );
		}

		if ( 0 !== $this->exit_code ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test context, output not rendered.
			throw new RuntimeException(
				'Failed to activate WPCOM Legacy Redirector plugin: ' . $this->output . ' ' . $this->error_output
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Create a published post with a specific slug.
	 *
	 * @Given there is a published post with a slug of :post_name
	 * @throws RuntimeException If post creation fails.
	 * @param string $post_name Post slug to use.
	 * @return void
	 */
	public function there_is_a_published_post( $post_name ): void {
		$command = sprintf(
			"post create --post_title='%s' --post_name='%s' --post_status='publish'",
			$post_name,
			$post_name
		);
		$this->run_wp_cli_command( $command, false );

		if ( 0 !== $this->exit_code ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test context, output not rendered.
			throw new RuntimeException(
				'Failed to create post: ' . $this->output
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Add host to allowed_redirect_hosts.
	 *
	 * @Given :host is allowed to be redirected
	 * @throws RuntimeException If filter setup fails.
	 * @param string $host Host name to add.
	 * @return void
	 */
	public function i_add_host_to_allowed_redirect_hosts( $host ): void {
		$filter_code = sprintf(
			"<?php add_filter( 'allowed_redirect_hosts', function( \$hosts ) { return array_merge( \$hosts, array( '%s' ) ); } );",
			$host
		);

		// Use base64 encoding to avoid shell escaping issues.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding for shell safety, not obfuscation.
		$encoded = base64_encode( $filter_code );

		// Create mu-plugin to add the filter.
		$command = sprintf(
			'eval \'file_put_contents( WPMU_PLUGIN_DIR . "/allowed_redirect_hosts-%s.php", base64_decode( "%s" ) );\'',
			$host,
			$encoded
		);
		$this->run_wp_cli_command( $command, false );

		if ( 0 !== $this->exit_code ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test context, output not rendered.
			throw new RuntimeException(
				'Failed to add host to allowed_redirect_hosts: ' . $this->output
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$this->added_hosts[] = $host;
	}

	/**
	 * Run a WP-CLI command that is expected to succeed.
	 *
	 * @When I run :command
	 * @When /^I run `([^`]+)`$/
	 * @param string $command The command to run.
	 * @return void
	 */
	public function i_run( $command ): void {
		// Remove backticks if present.
		$command = trim( $command, '`' );

		// Remove 'wp ' prefix if present (we add it in run_wp_cli_command).
		if ( 0 === strpos( $command, 'wp ' ) ) {
			$command = substr( $command, 3 );
		}

		$this->previous_command = $command;
		$this->run_wp_cli_command( $command, false );
	}

	/**
	 * Run a WP-CLI command that may fail.
	 *
	 * @When I try :command
	 * @When /^I try `([^`]+)`$/
	 * @param string $command The command to try.
	 * @return void
	 */
	public function i_try( $command ): void {
		// Remove backticks if present.
		$command = trim( $command, '`' );

		// Remove 'wp ' prefix if present.
		if ( 0 === strpos( $command, 'wp ' ) ) {
			$command = substr( $command, 3 );
		}

		$this->previous_command = $command;
		$this->run_wp_cli_command( $command, true );
	}

	/**
	 * Run the previous command again.
	 *
	 * @When /^I (run|try) the previous command again$/
	 * @throws RuntimeException If no previous command exists.
	 * @param string $action Either 'run' or 'try'.
	 * @return void
	 */
	public function i_run_the_previous_command_again( $action ): void {
		if ( empty( $this->previous_command ) ) {
			throw new RuntimeException( 'No previous command to run' );
		}

		if ( 'run' === $action ) {
			$this->i_run( $this->previous_command );
		} else {
			$this->i_try( $this->previous_command );
		}
	}

	/**
	 * Save STDOUT to a variable.
	 *
	 * @Given /^save STDOUT as \{([A-Z_]+)\}$/
	 * @param string $var_name Variable name to save to.
	 * @return void
	 */
	public function save_stdout_as( $var_name ): void {
		$this->saved_variables[ $var_name ] = trim( $this->output );
	}

	/**
	 * Assert that STDOUT exactly matches expected output.
	 *
	 * @Then STDOUT should be:
	 * @throws RuntimeException If STDOUT does not match.
	 * @param PyStringNode $expected Expected output.
	 * @return void
	 */
	public function stdout_should_be( PyStringNode $expected ): void {
		$actual        = trim( $this->output );
		$expected_text = $this->replace_variables( trim( $expected->getRaw() ) );

		if ( $actual !== $expected_text ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test context, output not rendered.
			throw new RuntimeException(
				sprintf(
					"STDOUT does not match.\nExpected:\n%s\n\nActual:\n%s",
					$expected_text,
					$actual
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Assert that STDOUT contains expected text.
	 *
	 * @Then STDOUT should contain:
	 * @throws RuntimeException If STDOUT does not contain expected text.
	 * @param PyStringNode $expected Expected text to find.
	 * @return void
	 */
	public function stdout_should_contain( PyStringNode $expected ): void {
		$actual        = trim( $this->output );
		$expected_text = $this->replace_variables( trim( $expected->getRaw() ) );

		if ( false === strpos( $actual, $expected_text ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test context, output not rendered.
			throw new RuntimeException(
				sprintf(
					"STDOUT does not contain expected text.\nExpected to find:\n%s\n\nActual output:\n%s",
					$expected_text,
					$actual
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Assert that STDERR exactly matches expected output.
	 *
	 * @Then STDERR should be:
	 * @throws RuntimeException If STDERR does not match.
	 * @param PyStringNode $expected Expected error output.
	 * @return void
	 */
	public function stderr_should_be( PyStringNode $expected ): void {
		$actual        = trim( $this->error_output );
		$expected_text = $this->replace_variables( trim( $expected->getRaw() ) );

		if ( $actual !== $expected_text ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test context, output not rendered.
			throw new RuntimeException(
				sprintf(
					"STDERR does not match.\nExpected:\n%s\n\nActual:\n%s",
					$expected_text,
					$actual
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Assert that STDERR contains expected text.
	 *
	 * @Then STDERR should contain:
	 * @throws RuntimeException If STDERR does not contain expected text.
	 * @param PyStringNode $expected Expected text to find in STDERR.
	 * @return void
	 */
	public function stderr_should_contain( PyStringNode $expected ): void {
		$actual        = trim( $this->error_output );
		$expected_text = $this->replace_variables( trim( $expected->getRaw() ) );

		if ( false === strpos( $actual, $expected_text ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test context, output not rendered.
			throw new RuntimeException(
				sprintf(
					"STDERR does not contain expected text.\nExpected to find:\n%s\n\nActual error output:\n%s",
					$expected_text,
					$actual
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Replace saved variables in text.
	 *
	 * @param string $text Text to process.
	 * @return string Text with variables replaced.
	 */
	private function replace_variables( $text ): string {
		foreach ( $this->saved_variables as $var => $value ) {
			$text = str_replace( '{' . $var . '}', $value, $text );
		}
		return $text;
	}
}
