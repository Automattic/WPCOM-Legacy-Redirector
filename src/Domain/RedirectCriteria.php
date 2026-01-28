<?php
/**
 * RedirectCriteria value object.
 *
 * @package Automattic\LegacyRedirector\Domain
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Domain;

/**
 * Criteria for querying redirects.
 *
 * Immutable value object that encapsulates filtering, sorting, and
 * pagination parameters for redirect queries.
 */
final class RedirectCriteria {

	/**
	 * Filter by redirect status.
	 *
	 * @var string|null 'enabled', 'disabled', or null for any.
	 */
	private ?string $status;

	/**
	 * Filter by destination type.
	 *
	 * @var string|null 'post', 'url', or null for any.
	 */
	private ?string $destination_type;

	/**
	 * Search term for source paths.
	 *
	 * @var string|null
	 */
	private ?string $search;

	/**
	 * Field to order by.
	 *
	 * @var string
	 */
	private string $order_by;

	/**
	 * Sort direction.
	 *
	 * @var string 'ASC' or 'DESC'.
	 */
	private string $order;

	/**
	 * Maximum number of results.
	 *
	 * @var int
	 */
	private int $limit;

	/**
	 * Number of results to skip.
	 *
	 * @var int
	 */
	private int $offset;

	/**
	 * Constructor.
	 *
	 * @param string|null $status           Filter by status ('enabled', 'disabled', or null).
	 * @param string|null $destination_type Filter by destination type ('post', 'url', or null).
	 * @param string|null $search           Search term for source paths.
	 * @param string      $order_by         Field to order by (default: 'date').
	 * @param string      $order            Sort direction (default: 'DESC').
	 * @param int         $limit            Maximum results (default: 100).
	 * @param int         $offset           Results to skip (default: 0).
	 */
	public function __construct(
		?string $status = null,
		?string $destination_type = null,
		?string $search = null,
		string $order_by = 'date',
		string $order = 'DESC',
		int $limit = 100,
		int $offset = 0
	) {
		$this->status           = $status;
		$this->destination_type = $destination_type;
		$this->search           = $search;
		$this->order_by         = $order_by;
		$this->order            = strtoupper( $order );
		$this->limit            = max( 1, $limit );
		$this->offset           = max( 0, $offset );
	}

	/**
	 * Create criteria from CLI arguments.
	 *
	 * Factory method that maps common CLI option names to criteria properties.
	 *
	 * @param array $args Associative array of arguments.
	 * @return self
	 */
	public static function from_args( array $args ): self {
		$status = $args['status'] ?? null;
		if ( 'any' === $status ) {
			$status = null;
		}

		$destination_type = $args['destination-type'] ?? $args['destination_type'] ?? null;
		if ( 'any' === $destination_type ) {
			$destination_type = null;
		}

		return new self(
			$status,
			$destination_type,
			$args['search'] ?? null,
			$args['orderby'] ?? $args['order_by'] ?? 'date',
			$args['order'] ?? 'DESC',
			(int) ( $args['limit'] ?? 100 ),
			(int) ( $args['offset'] ?? 0 )
		);
	}

	/**
	 * Get status filter.
	 *
	 * @return string|null
	 */
	public function status(): ?string {
		return $this->status;
	}

	/**
	 * Get destination type filter.
	 *
	 * @return string|null
	 */
	public function destination_type(): ?string {
		return $this->destination_type;
	}

	/**
	 * Get search term.
	 *
	 * @return string|null
	 */
	public function search(): ?string {
		return $this->search;
	}

	/**
	 * Get order by field.
	 *
	 * @return string
	 */
	public function order_by(): string {
		return $this->order_by;
	}

	/**
	 * Get sort direction.
	 *
	 * @return string
	 */
	public function order(): string {
		return $this->order;
	}

	/**
	 * Get result limit.
	 *
	 * @return int
	 */
	public function limit(): int {
		return $this->limit;
	}

	/**
	 * Get result offset.
	 *
	 * @return int
	 */
	public function offset(): int {
		return $this->offset;
	}

	/**
	 * Check if filtering by enabled status.
	 *
	 * @return bool
	 */
	public function is_enabled_only(): bool {
		return 'enabled' === $this->status;
	}

	/**
	 * Check if filtering by disabled status.
	 *
	 * @return bool
	 */
	public function is_disabled_only(): bool {
		return 'disabled' === $this->status;
	}

	/**
	 * Check if filtering by post destination type.
	 *
	 * @return bool
	 */
	public function is_post_type_only(): bool {
		return 'post' === $this->destination_type;
	}

	/**
	 * Check if filtering by URL destination type.
	 *
	 * @return bool
	 */
	public function is_url_type_only(): bool {
		return 'url' === $this->destination_type;
	}

	/**
	 * Check if there's a search term.
	 *
	 * @return bool
	 */
	public function has_search(): bool {
		return null !== $this->search && '' !== $this->search;
	}

	/**
	 * Create a copy with modified limit.
	 *
	 * @param int $limit New limit.
	 * @return self
	 */
	public function with_limit( int $limit ): self {
		return new self(
			$this->status,
			$this->destination_type,
			$this->search,
			$this->order_by,
			$this->order,
			$limit,
			$this->offset
		);
	}

	/**
	 * Create a copy with modified offset.
	 *
	 * @param int $offset New offset.
	 * @return self
	 */
	public function with_offset( int $offset ): self {
		return new self(
			$this->status,
			$this->destination_type,
			$this->search,
			$this->order_by,
			$this->order,
			$this->limit,
			$offset
		);
	}
}
