<?php
/**
 * WordPress post type redirect query repository.
 *
 * @package Automattic\LegacyRedirector\Infrastructure\WordPress
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress;

use Automattic\LegacyRedirector\Domain\Destination;
use Automattic\LegacyRedirector\Domain\DestinationPostId;
use Automattic\LegacyRedirector\Domain\DestinationUrl;
use Automattic\LegacyRedirector\Domain\Redirect;
use Automattic\LegacyRedirector\Domain\RedirectCriteria;
use Automattic\LegacyRedirector\Domain\RedirectQueryRepositoryInterface;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use DateTimeImmutable;
use WP_Post;
use WP_Query;

/**
 * Query repository implementation using WordPress custom post type.
 *
 * Handles listing, searching, and filtering redirects via WP_Query.
 * Separated from the command repository (PostTypeRedirectRepository)
 * following CQRS principles.
 */
final class PostTypeRedirectQueryRepository implements RedirectQueryRepositoryInterface {

	/**
	 * Find redirects matching the given criteria.
	 *
	 * @param RedirectCriteria $criteria The query criteria.
	 * @return Redirect[] Array of matching redirects.
	 */
	public function find_matching( RedirectCriteria $criteria ): array {
		$query = new WP_Query( $this->build_query_args( $criteria ) );

		return array_map(
			array( $this, 'map_post_to_redirect' ),
			$query->posts
		);
	}

	/**
	 * Count redirects matching the given criteria.
	 *
	 * @param RedirectCriteria $criteria The query criteria (limit/offset are ignored).
	 * @return int The total count of matching redirects.
	 */
	public function count_matching( RedirectCriteria $criteria ): int {
		// Build query args for counting (no pagination).
		$args = $this->build_query_args( $criteria );

		// For count, we just need the total without fetching all posts.
		$args['posts_per_page'] = 1;
		$args['fields']         = 'ids';

		$query = new WP_Query( $args );

		return (int) $query->found_posts;
	}

	/**
	 * Build WP_Query arguments from criteria.
	 *
	 * @param RedirectCriteria $criteria The query criteria.
	 * @return array<string, mixed> The query arguments.
	 */
	private function build_query_args( RedirectCriteria $criteria ): array {
		$args = array(
			'post_type'      => PostType::POST_TYPE,
			'post_status'    => $this->map_status_to_post_status( $criteria->status() ),
			'posts_per_page' => $criteria->limit(),
			'offset'         => $criteria->offset(),
			'orderby'        => $criteria->order_by(),
			'order'          => $criteria->order(),
		);

		// Add search filter.
		if ( $criteria->has_search() ) {
			$args['s'] = $criteria->search();
		}

		// Add destination type filter.
		$dest_type = $criteria->destination_type();
		if ( 'post' === $dest_type ) {
			$args['post_parent__not_in'] = array( 0 );
		} elseif ( 'url' === $dest_type ) {
			$args['post_parent'] = 0;
		}

		return $args;
	}

	/**
	 * Map status filter to WordPress post status.
	 *
	 * @param string|null $status The status filter ('enabled', 'disabled', or null).
	 * @return string|string[] The post status(es).
	 */
	private function map_status_to_post_status( ?string $status ) { // phpcs:ignore NeutronStandard.Functions.TypeHint.NoReturnType -- Mixed return.
		if ( 'enabled' === $status ) {
			return 'publish';
		}

		if ( 'disabled' === $status ) {
			return 'draft';
		}

		// Return both publish and draft for 'any' or null.
		return array( 'publish', 'draft' );
	}

	/**
	 * Map a WP_Post to a Redirect entity.
	 *
	 * @param WP_Post $post The post to map.
	 * @return Redirect The redirect entity.
	 */
	private function map_post_to_redirect( WP_Post $post ): Redirect {
		$source      = SourceUrl::from_string( $post->post_title );
		$destination = $this->extract_destination_from_post( $post );
		$created_at  = $this->parse_date( $post->post_date_gmt );

		return Redirect::reconstitute(
			$post->ID,
			$source,
			$destination,
			$post->post_status,
			$created_at
		);
	}

	/**
	 * Extract the destination from a post.
	 *
	 * @param WP_Post $post The redirect post.
	 * @return Destination The destination.
	 */
	private function extract_destination_from_post( WP_Post $post ): Destination {
		// Check for internal redirect (post_parent).
		if ( $post->post_parent > 0 ) {
			return Destination::from_post_id(
				DestinationPostId::from_int( $post->post_parent )
			);
		}

		// External or relative URL (post_excerpt).
		$excerpt = trim( $post->post_excerpt );
		if ( ! empty( $excerpt ) ) {
			return Destination::from_url(
				DestinationUrl::from_string( $excerpt )
			);
		}

		// Fallback to home if no destination found.
		return Destination::from_url( DestinationUrl::home() );
	}

	/**
	 * Parse a date string to DateTimeImmutable.
	 *
	 * @param string $date_string The date string (MySQL format).
	 * @return DateTimeImmutable|null The parsed date, or null if invalid.
	 */
	private function parse_date( string $date_string ): ?DateTimeImmutable {
		if ( empty( $date_string ) || '0000-00-00 00:00:00' === $date_string ) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date_string );

		return $date instanceof DateTimeImmutable ? $date : null;
	}
}
