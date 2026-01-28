<?php
/**
 * Redirect query repository interface.
 *
 * @package Automattic\LegacyRedirector\Domain
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Domain;

/**
 * Repository interface for querying redirects.
 *
 * Separate from RedirectRepositoryInterface following CQRS principles:
 * - RedirectRepositoryInterface handles commands (create, update, delete, find by ID/source)
 * - RedirectQueryRepositoryInterface handles queries (list, search, filter, count)
 *
 * This separation allows for different optimisations:
 * - Command repository can use caching for individual lookups
 * - Query repository can use efficient database queries for bulk operations
 */
interface RedirectQueryRepositoryInterface {

	/**
	 * Find redirects matching the given criteria.
	 *
	 * @param RedirectCriteria $criteria The query criteria.
	 * @return Redirect[] Array of matching redirects.
	 */
	public function find_matching( RedirectCriteria $criteria ): array;

	/**
	 * Count redirects matching the given criteria.
	 *
	 * Useful for pagination - returns total count regardless of limit/offset.
	 *
	 * @param RedirectCriteria $criteria The query criteria (limit/offset are ignored).
	 * @return int The total count of matching redirects.
	 */
	public function count_matching( RedirectCriteria $criteria ): int;
}
