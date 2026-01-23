<?php
/**
 * WP_CLI\Utils namespace stubs for unit tests.
 *
 * @package Automattic\LegacyRedirector
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

namespace WP_CLI\Utils;

if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
	/**
	 * Format items stub.
	 *
	 * @param string $format Output format.
	 * @param array  $items  Items to format.
	 * @param array  $fields Fields to display.
	 * @return void
	 */
	function format_items( string $format, array $items, array $fields ): void {
		$GLOBALS['wp_cli_format_items_calls'][] = array( $format, $items, $fields );
	}

	/**
	 * Make progress bar stub.
	 *
	 * @param string $message The progress message.
	 * @param int    $count   Total count.
	 * @return object A mock progress bar.
	 */
	function make_progress_bar( string $message, int $count ): object {
		return new class() {
			/**
			 * Tick the progress bar.
			 *
			 * @return void
			 */
			public function tick(): void {
				// No-op for tests.
			}

			/**
			 * Finish the progress bar.
			 *
			 * @return void
			 */
			public function finish(): void {
				// No-op for tests.
			}
		};
	}
}
