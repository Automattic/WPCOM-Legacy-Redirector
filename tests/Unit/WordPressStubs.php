<?php
/**
 * WordPress class stubs for unit tests.
 *
 * These stubs provide minimal implementations of WordPress classes
 * needed for unit testing. They are kept separate to allow tests
 * to control when they're loaded.
 *
 * @package Automattic\LegacyRedirector
 */

// WordPress constants for unit tests.
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

// WP_Post stub for unit tests.
if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal WP_Post stub for unit testing.
	 */
	class WP_Post {
		/**
		 * The post status.
		 *
		 * @var string
		 */
		public string $post_status = 'publish';

		/**
		 * Constructor.
		 *
		 * @param string $status The post status.
		 */
		public function __construct( string $status = 'publish' ) {
			$this->post_status = $status;
		}
	}
}

// WP_Error stub for unit tests.
if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub for unit testing.
	 */
	class WP_Error {
		/**
		 * The error code.
		 *
		 * @var string
		 */
		private string $code;

		/**
		 * The error message.
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * Get the error code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Get the error message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}
