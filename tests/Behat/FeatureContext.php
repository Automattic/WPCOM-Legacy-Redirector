<?php
/**
 * Feature tests context class for wp-env based Behat testing.
 *
 * @package Automattic\LegacyRedirector
 */

namespace Automattic\LegacyRedirector\Tests\Behat;

use Automattic\BehatWpEnv\WpEnvFeatureContext;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Testwork\Hook\Scope\AfterSuiteScope;
use RuntimeException;

/**
 * Feature tests context class for WPCOM Legacy Redirector.
 *
 * Extends the Automattic Behat wp-env context with plugin-specific steps.
 */
final class FeatureContext extends WpEnvFeatureContext {

	/**
	 * Hosts added to allowed_redirect_hosts for cleanup.
	 *
	 * @var string[]
	 */
	private array $added_hosts = array();

	/**
	 * Get the plugin slug for wp-env command execution.
	 *
	 * @return string Plugin directory name.
	 */
	protected function get_plugin_slug(): string {
		return 'wpcom-legacy-redirector';
	}

	/**
	 * Plugin-specific database cleanup.
	 *
	 * @return void
	 */
	protected function plugin_specific_cleanup(): void {
		// Delete all redirect posts (custom post type).
		$this->run_wp_cli_command( 'post list --post_type=vip-legacy-redirect --format=ids', false );
		$redirect_ids = trim( $this->output );

		if ( ! empty( $redirect_ids ) ) {
			$this->run_wp_cli_command( "post delete {$redirect_ids} --force", false );
		}

		// Remove mu-plugins created for allowed_redirect_hosts.
		foreach ( $this->added_hosts as $host ) {
			$this->remove_mu_plugin( 'allowed_redirect_hosts-' . $host );
		}
		$this->added_hosts = array();
	}

	/**
	 * Create bypass 404 mu-plugin before test suite runs.
	 *
	 * This is created once for all scenarios, avoiding per-scenario overhead.
	 *
	 * @BeforeSuite
	 * @param BeforeSuiteScope $scope Suite scope.
	 * @return void
	 */
	public static function setup_bypass_mu_plugin( BeforeSuiteScope $scope ): void {
		$bypass_validation = <<<'PHP'
<?php
/**
 * Bypass 404 validation for Behat tests.
 */
add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
	if ( false !== strpos( $url, home_url() ) ) {
		return array( 'response' => array( 'code' => 404 ) );
	}
	return $preempt;
}, 10, 3 );
PHP;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding for shell safety.
		$encoded = base64_encode( $bypass_validation );
		$command = sprintf(
			"npx wp-env run tests-cli bash -c 'wp eval '\''if ( ! is_dir( WPMU_PLUGIN_DIR ) ) { mkdir( WPMU_PLUGIN_DIR, 0755, true ); } file_put_contents( WPMU_PLUGIN_DIR . \"/behat-bypass-404-check.php\", base64_decode( \"%s\" ) );'\'' 2>&1'",
			$encoded
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Required for Behat setup.
		exec( $command, $output, $exit_code );
	}

	/**
	 * Remove bypass 404 mu-plugin after test suite completes.
	 *
	 * @AfterSuite
	 * @param AfterSuiteScope $scope Suite scope.
	 * @return void
	 */
	public static function teardown_bypass_mu_plugin( AfterSuiteScope $scope ): void {
		$command = "npx wp-env run tests-cli bash -c 'wp eval '\\''if ( file_exists( WPMU_PLUGIN_DIR . \"/behat-bypass-404-check.php\" ) ) { unlink( WPMU_PLUGIN_DIR . \"/behat-bypass-404-check.php\" ); }'\\'' 2>&1'";

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Required for Behat teardown.
		exec( $command, $output, $exit_code );
	}

	/**
	 * Set up a WP installation with WPCOM Legacy Redirector plugin activated.
	 *
	 * @Given a WP install(ation) with the WPCOM Legacy Redirector plugin
	 * @throws RuntimeException If plugin activation fails.
	 * @return void
	 */
	public function given_a_wp_installation_with_the_wpcomlr_plugin(): void {
		// Check if already active first (most common case after first scenario).
		$this->run_wp_cli_command( 'plugin is-active wpcom-legacy-redirector', false );
		if ( 0 === $this->exit_code ) {
			return; // Already active, nothing to do.
		}

		// Try the CI folder name format.
		$this->run_wp_cli_command( 'plugin is-active WPCOM-Legacy-Redirector/wpcom-legacy-redirector.php', false );
		if ( 0 === $this->exit_code ) {
			return; // Already active, nothing to do.
		}

		// Not active, try to activate with lowercase slug (local development).
		$this->run_wp_cli_command( 'plugin activate wpcom-legacy-redirector', false );
		if ( 0 === $this->exit_code ) {
			return;
		}

		// Try CI folder name format.
		$this->run_wp_cli_command( 'plugin activate WPCOM-Legacy-Redirector/wpcom-legacy-redirector.php', false );
		if ( 0 === $this->exit_code ) {
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't require escaping.
		throw new RuntimeException(
			'Failed to activate WPCOM Legacy Redirector plugin: ' . $this->output . ' ' . $this->error_output
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Create a published post with a specific slug.
	 *
	 * @Given there is a published post with a slug of :post_name
	 * @throws RuntimeException If post creation fails.
	 * @param string $post_name Post slug to use.
	 * @return void
	 */
	public function there_is_a_published_post( string $post_name ): void {
		$command = sprintf(
			"post create --post_title='%s' --post_name='%s' --post_status='publish'",
			$post_name,
			$post_name
		);
		$this->run_wp_cli_command( $command, false );

		if ( 0 !== $this->exit_code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't require escaping.
			throw new RuntimeException( 'Failed to create post: ' . $this->output );
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
	public function i_add_host_to_allowed_redirect_hosts( string $host ): void {
		$filter_code = sprintf(
			"<?php add_filter( 'allowed_redirect_hosts', function( \$hosts ) { return array_merge( \$hosts, array( '%s' ) ); } );",
			$host
		);

		$this->create_mu_plugin( 'allowed_redirect_hosts-' . $host, $filter_code );
		$this->added_hosts[] = $host;
	}

	/**
	 * Temporary files created for cleanup.
	 *
	 * @var string[]
	 */
	private array $temp_files = array();

	/**
	 * Create a CSV file with given content.
	 *
	 * @Given a CSV file :filename with content:
	 * @throws RuntimeException If file creation fails.
	 * @param string                           $filename The filename to create.
	 * @param \Behat\Gherkin\Node\PyStringNode $content The CSV content.
	 * @return void
	 */
	public function given_a_csv_file_with_content( string $filename, \Behat\Gherkin\Node\PyStringNode $content ): void {
		$csv_content = (string) $content;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding for shell safety.
		$encoded = base64_encode( $csv_content );

		// Create file in the container's /tmp directory.
		$file_path = '/tmp/' . $filename;
		$command   = sprintf(
			"npx wp-env run tests-cli bash -c 'echo %s | base64 -d > %s' 2>&1",
			escapeshellarg( $encoded ),
			escapeshellarg( $file_path )
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Required for Behat file setup.
		exec( $command, $output, $exit_code );

		if ( 0 !== $exit_code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't require escaping.
			throw new RuntimeException( 'Failed to create CSV file: ' . implode( "\n", $output ) );
		}

		$this->temp_files[] = $file_path;
	}

	/**
	 * Clean up temporary files created during tests.
	 *
	 * @AfterScenario
	 * @return void
	 */
	public function cleanup_temp_files(): void {
		foreach ( $this->temp_files as $file_path ) {
			$command = sprintf(
				"npx wp-env run tests-cli bash -c 'rm -f %s' 2>&1",
				escapeshellarg( $file_path )
			);

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Required for Behat cleanup.
			exec( $command );
		}
		$this->temp_files = array();
	}

	/**
	 * Create a redirect via WP-CLI.
	 *
	 * @Given there is a redirect from :from to :to
	 * @throws RuntimeException If redirect creation fails.
	 * @param string $from The source URL path.
	 * @param string $to   The destination URL or post ID.
	 * @return void
	 */
	public function there_is_a_redirect_from_to( string $from, string $to ): void {
		$this->run_wp_cli_command(
			sprintf( 'wpcom-legacy-redirector insert-redirect %s %s', $from, $to ),
			false
		);

		if ( 0 !== $this->exit_code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't require escaping.
			throw new RuntimeException( 'Failed to create redirect: ' . $this->output . ' ' . $this->error_output );
		}
	}

	/**
	 * Save STDOUT value to a placeholder for later use.
	 *
	 * This allows capturing post IDs and other output for use in subsequent steps.
	 *
	 * @Given save STDOUT as {VARIABLE}
	 * @throws RuntimeException If no output to save.
	 * @return void
	 */
	public function save_stdout_as_variable(): void {
		// This step is handled by the base class or WP-CLI Behat framework.
		// Kept here for documentation purposes.
	}
}
