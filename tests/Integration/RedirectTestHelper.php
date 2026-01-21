<?php
/**
 * Redirect test helper trait.
 *
 * Provides helper methods for creating redirects in integration tests.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Integration;

use Automattic\LegacyRedirector\Domain\Destination;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use Automattic\LegacyRedirector\Infrastructure\DI\Container;

/**
 * Trait providing helper methods for redirect operations in tests.
 */
trait RedirectTestHelper {

	/**
	 * Get the DI container instance.
	 *
	 * @return Container The container.
	 */
	abstract protected function container(): Container;

	/**
	 * Create a redirect using the new API.
	 *
	 * @param string     $from     The source URL path.
	 * @param string|int $to       The destination URL or post ID.
	 * @param bool       $validate Whether to validate the redirect (default false).
	 * @return int The redirect post ID.
	 *
	 * @throws \RuntimeException If the redirect could not be created.
	 */
	protected function create_redirect( string $from, $to, bool $validate = false ): int {
		$manager     = $this->container()->manager();
		$source      = SourceUrl::from_string( $from );
		$destination = Destination::from_mixed( $to );
		$result      = $manager->create_redirect( $source, $destination, $validate );

		if ( $result->is_error() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is not output to browser.
			throw new \RuntimeException( $result->error_message() );
		}

		return $result->redirect_id();
	}

	/**
	 * Create a redirect and return boolean success (mimics legacy API).
	 *
	 * @param string     $from     The source URL path.
	 * @param string|int $to       The destination URL or post ID.
	 * @param bool       $validate Whether to validate the redirect (default false).
	 * @return bool True on success.
	 */
	protected function create_redirect_bool( string $from, $to, bool $validate = false ): bool {
		try {
			$this->create_redirect( $from, $to, $validate );
			return true;
		} catch ( \RuntimeException $e ) {
			return false;
		}
	}

	/**
	 * Create a redirect and return the result object.
	 *
	 * Useful when testing error conditions.
	 *
	 * @param string     $from     The source URL path.
	 * @param string|int $to       The destination URL or post ID.
	 * @param bool       $validate Whether to validate the redirect (default false).
	 * @return \Automattic\LegacyRedirector\Application\RedirectCreationResult The result.
	 */
	protected function create_redirect_result( string $from, $to, bool $validate = false ) {
		$manager     = $this->container()->manager();
		$source      = SourceUrl::from_string( $from );
		$destination = Destination::from_mixed( $to );

		return $manager->create_redirect( $source, $destination, $validate );
	}
}
