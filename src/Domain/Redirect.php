<?php
/**
 * Redirect entity.
 *
 * @package Automattic\LegacyRedirector\Domain
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Domain;

use DateTimeImmutable;

/**
 * Redirect entity - the core domain model.
 *
 * Represents a redirect rule with identity (ID), lifecycle (status),
 * and the source/destination mapping. This is an entity because it
 * has a distinct identity that persists over time.
 */
final class Redirect {

	/**
	 * The redirect ID (null if not yet persisted).
	 *
	 * @var int|null
	 */
	private ?int $id;

	/**
	 * The source URL to redirect from.
	 *
	 * @var SourceUrl
	 */
	private SourceUrl $source;

	/**
	 * The destination to redirect to.
	 *
	 * @var Destination
	 */
	private Destination $destination;

	/**
	 * The post status (publish, draft, trash).
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * When the redirect was created.
	 *
	 * @var DateTimeImmutable|null
	 */
	private ?DateTimeImmutable $created_at;

	/**
	 * Private constructor - use named constructors.
	 *
	 * @param int|null               $id          The redirect ID.
	 * @param SourceUrl              $source      The source URL.
	 * @param Destination            $destination The destination.
	 * @param string                 $status      The status.
	 * @param DateTimeImmutable|null $created_at  When created.
	 */
	private function __construct(
		?int $id,
		SourceUrl $source,
		Destination $destination,
		string $status,
		?DateTimeImmutable $created_at
	) {
		$this->id          = $id;
		$this->source      = $source;
		$this->destination = $destination;
		$this->status      = $status;
		$this->created_at  = $created_at;
	}

	/**
	 * Create a new redirect (not yet persisted).
	 *
	 * @param SourceUrl   $source      The source URL.
	 * @param Destination $destination The destination.
	 * @return self
	 */
	public static function create( SourceUrl $source, Destination $destination ): self {
		return new self(
			null,
			$source,
			$destination,
			'publish',
			new DateTimeImmutable()
		);
	}

	/**
	 * Reconstitute a redirect from persistence.
	 *
	 * Used by the repository when loading from the database.
	 *
	 * @param int                    $id          The redirect ID.
	 * @param SourceUrl              $source      The source URL.
	 * @param Destination            $destination The destination.
	 * @param string                 $status      The status.
	 * @param DateTimeImmutable|null $created_at  When created.
	 * @return self
	 */
	public static function reconstitute(
		int $id,
		SourceUrl $source,
		Destination $destination,
		string $status,
		?DateTimeImmutable $created_at = null
	): self {
		return new self( $id, $source, $destination, $status, $created_at );
	}

	/**
	 * Get the redirect ID.
	 *
	 * @return int|null The ID, or null if not persisted.
	 */
	public function id(): ?int {
		return $this->id;
	}

	/**
	 * Check if this redirect has been persisted.
	 *
	 * @return bool True if persisted (has an ID).
	 */
	public function is_persisted(): bool {
		return null !== $this->id;
	}

	/**
	 * Get the source URL.
	 *
	 * @return SourceUrl
	 */
	public function source(): SourceUrl {
		return $this->source;
	}

	/**
	 * Get the destination.
	 *
	 * @return Destination
	 */
	public function destination(): Destination {
		return $this->destination;
	}

	/**
	 * Get the status.
	 *
	 * @return string The status (publish, draft, trash).
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Check if the redirect is active (published).
	 *
	 * @return bool True if status is 'publish'.
	 */
	public function is_active(): bool {
		return 'publish' === $this->status;
	}

	/**
	 * Check if the redirect is trashed.
	 *
	 * @return bool True if status is 'trash'.
	 */
	public function is_trashed(): bool {
		return 'trash' === $this->status;
	}

	/**
	 * Get the creation timestamp.
	 *
	 * @return DateTimeImmutable|null
	 */
	public function created_at(): ?DateTimeImmutable {
		return $this->created_at;
	}

	/**
	 * Create a copy with a new ID (used after persisting).
	 *
	 * @param int $id The new ID.
	 * @return self
	 */
	public function with_id( int $id ): self {
		return new self(
			$id,
			$this->source,
			$this->destination,
			$this->status,
			$this->created_at
		);
	}

	/**
	 * Create a copy with a new status.
	 *
	 * @param string $status The new status.
	 * @return self
	 */
	public function with_status( string $status ): self {
		return new self(
			$this->id,
			$this->source,
			$this->destination,
			$status,
			$this->created_at
		);
	}

	/**
	 * Publish this redirect (set status to publish).
	 *
	 * @return self
	 */
	public function publish(): self {
		return $this->with_status( 'publish' );
	}

	/**
	 * Trash this redirect (set status to trash).
	 *
	 * @return self
	 */
	public function trash(): self {
		return $this->with_status( 'trash' );
	}

	/**
	 * Create a copy with a new destination.
	 *
	 * @param Destination $destination The new destination.
	 * @return self
	 */
	public function with_destination( Destination $destination ): self {
		return new self(
			$this->id,
			$this->source,
			$destination,
			$this->status,
			$this->created_at
		);
	}
}
