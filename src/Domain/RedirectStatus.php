<?php
/**
 * RedirectStatus enum.
 *
 * @package Automattic\LegacyRedirector\Domain
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Domain;

use InvalidArgumentException;

/**
 * HTTP redirect status codes.
 *
 * Represents valid HTTP redirect status codes that can be used
 * when performing a redirect.
 *
 * phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Enum methods can use $this.
 */
enum RedirectStatus: int {

	/**
	 * 301 Moved Permanently - The resource has been permanently moved.
	 * Search engines will update their index. This is the default.
	 */
	case MOVED_PERMANENTLY = 301;

	/**
	 * 302 Found - The resource is temporarily at a different URI.
	 * Search engines will keep the original URL indexed.
	 */
	case FOUND = 302;

	/**
	 * 303 See Other - The response can be found at another URI using GET.
	 * Typically used after a POST request.
	 */
	case SEE_OTHER = 303;

	/**
	 * 307 Temporary Redirect - Like 302, but the request method must not change.
	 */
	case TEMPORARY_REDIRECT = 307;

	/**
	 * 308 Permanent Redirect - Like 301, but the request method must not change.
	 */
	case PERMANENT_REDIRECT = 308;

	/**
	 * Get the default redirect status (301 Moved Permanently).
	 *
	 * @return self
	 */
	public static function get_default(): self {
		return self::MOVED_PERMANENTLY;
	}

	/**
	 * Create a RedirectStatus from an integer.
	 *
	 * @param int $code The HTTP status code.
	 * @return self
	 *
	 * @throws InvalidArgumentException If the code is not a valid redirect status.
	 */
	public static function from_int( int $code ): self {
		$status = self::tryFrom( $code );

		if ( null === $status ) {
			$valid_codes = implode( ', ', array_column( self::cases(), 'value' ) );
			$message     = sprintf( 'Invalid redirect status code: %d. Valid codes are: %s', $code, $valid_codes );
			throw new InvalidArgumentException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not rendered output.
		}

		return $status;
	}

	/**
	 * Check if this is a permanent redirect.
	 *
	 * @return bool True if 301 or 308.
	 */
	public function is_permanent(): bool {
		return in_array( $this, array( self::MOVED_PERMANENTLY, self::PERMANENT_REDIRECT ), true );
	}

	/**
	 * Check if this is a temporary redirect.
	 *
	 * @return bool True if 302, 303, or 307.
	 */
	public function is_temporary(): bool {
		return ! $this->is_permanent();
	}

	/**
	 * Get the human-readable name for this status.
	 *
	 * @return string The status name (e.g., "Moved Permanently").
	 */
	public function label(): string {
		return match ( $this ) {
			self::MOVED_PERMANENTLY  => 'Moved Permanently',
			self::FOUND              => 'Found',
			self::SEE_OTHER          => 'See Other',
			self::TEMPORARY_REDIRECT => 'Temporary Redirect',
			self::PERMANENT_REDIRECT => 'Permanent Redirect',
		};
	}
}
