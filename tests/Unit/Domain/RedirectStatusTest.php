<?php
/**
 * RedirectStatus enum unit tests.
 *
 * @package Automattic\LegacyRedirector\Tests\Unit\Domain
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Tests\Unit\Domain;

use Automattic\LegacyRedirector\Domain\RedirectStatus;
use Automattic\LegacyRedirector\Tests\Unit\MonkeyStubs;
use InvalidArgumentException;

/**
 * RedirectStatusTest class.
 *
 * @covers \Automattic\LegacyRedirector\Domain\RedirectStatus
 */
final class RedirectStatusTest extends MonkeyStubs {

	/**
	 * Test get_default returns 301.
	 *
	 * @covers \Automattic\LegacyRedirector\Domain\RedirectStatus::get_default
	 */
	public function test_get_default_returns_301(): void {
		$status = RedirectStatus::get_default();

		$this->assertSame( 301, $status->value );
		$this->assertSame( RedirectStatus::MOVED_PERMANENTLY, $status );
	}

	/**
	 * Test from_int with valid codes.
	 *
	 * @dataProvider valid_status_codes_provider
	 * @covers \Automattic\LegacyRedirector\Domain\RedirectStatus::from_int
	 *
	 * @param int            $code     The status code.
	 * @param RedirectStatus $expected The expected enum case.
	 */
	public function test_from_int_valid_codes( int $code, RedirectStatus $expected ): void {
		$status = RedirectStatus::from_int( $code );

		$this->assertSame( $expected, $status );
	}

	/**
	 * Data provider for valid status codes.
	 *
	 * @return array<string, array{int, RedirectStatus}>
	 */
	public function valid_status_codes_provider(): array {
		return array(
			'301' => array( 301, RedirectStatus::MOVED_PERMANENTLY ),
			'302' => array( 302, RedirectStatus::FOUND ),
			'303' => array( 303, RedirectStatus::SEE_OTHER ),
			'307' => array( 307, RedirectStatus::TEMPORARY_REDIRECT ),
			'308' => array( 308, RedirectStatus::PERMANENT_REDIRECT ),
		);
	}

	/**
	 * Test from_int throws for invalid code.
	 *
	 * @covers \Automattic\LegacyRedirector\Domain\RedirectStatus::from_int
	 */
	public function test_from_int_throws_for_invalid_code(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid redirect status code: 404' );

		RedirectStatus::from_int( 404 );
	}

	/**
	 * Test from_int throws for 200.
	 *
	 * @covers \Automattic\LegacyRedirector\Domain\RedirectStatus::from_int
	 */
	public function test_from_int_throws_for_200(): void {
		$this->expectException( InvalidArgumentException::class );

		RedirectStatus::from_int( 200 );
	}

	/**
	 * Test is_permanent for permanent redirects.
	 *
	 * @dataProvider permanent_status_provider
	 * @covers \Automattic\LegacyRedirector\Domain\RedirectStatus::is_permanent
	 *
	 * @param RedirectStatus $status The status to test.
	 */
	public function test_is_permanent_true( RedirectStatus $status ): void {
		$this->assertTrue( $status->is_permanent() );
	}

	/**
	 * Data provider for permanent status codes.
	 *
	 * @return array<string, array{RedirectStatus}>
	 */
	public function permanent_status_provider(): array {
		return array(
			'301' => array( RedirectStatus::MOVED_PERMANENTLY ),
			'308' => array( RedirectStatus::PERMANENT_REDIRECT ),
		);
	}

	/**
	 * Test is_permanent for temporary redirects.
	 *
	 * @dataProvider temporary_status_provider
	 * @covers \Automattic\LegacyRedirector\Domain\RedirectStatus::is_permanent
	 *
	 * @param RedirectStatus $status The status to test.
	 */
	public function test_is_permanent_false( RedirectStatus $status ): void {
		$this->assertFalse( $status->is_permanent() );
	}

	/**
	 * Data provider for temporary status codes.
	 *
	 * @return array<string, array{RedirectStatus}>
	 */
	public function temporary_status_provider(): array {
		return array(
			'302' => array( RedirectStatus::FOUND ),
			'303' => array( RedirectStatus::SEE_OTHER ),
			'307' => array( RedirectStatus::TEMPORARY_REDIRECT ),
		);
	}

	/**
	 * Test is_temporary for temporary redirects.
	 *
	 * @dataProvider temporary_status_provider
	 * @covers \Automattic\LegacyRedirector\Domain\RedirectStatus::is_temporary
	 *
	 * @param RedirectStatus $status The status to test.
	 */
	public function test_is_temporary_true( RedirectStatus $status ): void {
		$this->assertTrue( $status->is_temporary() );
	}

	/**
	 * Test is_temporary for permanent redirects.
	 *
	 * @dataProvider permanent_status_provider
	 * @covers \Automattic\LegacyRedirector\Domain\RedirectStatus::is_temporary
	 *
	 * @param RedirectStatus $status The status to test.
	 */
	public function test_is_temporary_false( RedirectStatus $status ): void {
		$this->assertFalse( $status->is_temporary() );
	}

	/**
	 * Test label returns human-readable names.
	 *
	 * @dataProvider label_provider
	 * @covers \Automattic\LegacyRedirector\Domain\RedirectStatus::label
	 *
	 * @param RedirectStatus $status         The status.
	 * @param string         $expected_label The expected label.
	 */
	public function test_label( RedirectStatus $status, string $expected_label ): void {
		$this->assertSame( $expected_label, $status->label() );
	}

	/**
	 * Data provider for labels.
	 *
	 * @return array<string, array{RedirectStatus, string}>
	 */
	public function label_provider(): array {
		return array(
			'301' => array( RedirectStatus::MOVED_PERMANENTLY, 'Moved Permanently' ),
			'302' => array( RedirectStatus::FOUND, 'Found' ),
			'303' => array( RedirectStatus::SEE_OTHER, 'See Other' ),
			'307' => array( RedirectStatus::TEMPORARY_REDIRECT, 'Temporary Redirect' ),
			'308' => array( RedirectStatus::PERMANENT_REDIRECT, 'Permanent Redirect' ),
		);
	}
}
