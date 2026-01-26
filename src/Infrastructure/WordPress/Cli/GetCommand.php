<?php
/**
 * Get redirect CLI command.
 *
 * @package Automattic\LegacyRedirector
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Infrastructure\WordPress\Cli;

use Automattic\LegacyRedirector\Domain\RedirectRepositoryInterface;
use Automattic\LegacyRedirector\Domain\SourceUrl;
use WP_CLI;
use WP_CLI_Command;

/**
 * Get details of a single redirect.
 */
final class GetCommand extends WP_CLI_Command {

	/**
	 * The redirect repository.
	 *
	 * @var RedirectRepositoryInterface
	 */
	private RedirectRepositoryInterface $repository;

	/**
	 * Constructor.
	 *
	 * @param RedirectRepositoryInterface $repository The redirect repository.
	 */
	public function __construct( RedirectRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Get details of a redirect by source path or ID.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : The source path (e.g., /old-page) or redirect ID.
	 *
	 * [--by=<field>]
	 * : How to look up the redirect.
	 * ---
	 * default: source
	 * options:
	 *   - source
	 *   - id
	 * ---
	 *
	 * [--field=<field>]
	 * : Return a single field value.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get redirect by source path.
	 *     $ wp wpcom-legacy-redirector get /old-page
	 *
	 *     # Get redirect by ID.
	 *     $ wp wpcom-legacy-redirector get 123 --by=id
	 *
	 *     # Get just the destination.
	 *     $ wp wpcom-legacy-redirector get /old-page --field=to
	 *
	 *     # Get redirect as JSON.
	 *     $ wp wpcom-legacy-redirector get /old-page --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Key-value associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$lookup = $args[0];
		$by     = $assoc_args['by'] ?? 'source';
		$field  = $assoc_args['field'] ?? null;
		$format = $assoc_args['format'] ?? 'table';

		// Find the redirect.
		if ( 'id' === $by ) {
			$redirect = $this->repository->find_by_id( (int) $lookup );
		} else {
			try {
				$source   = SourceUrl::from_string( $lookup );
				$redirect = $this->repository->find_by_source( $source );
			} catch ( \InvalidArgumentException $e ) {
				WP_CLI::error( sprintf( 'Invalid source path: %s', $e->getMessage() ) );
				return;
			}
		}

		if ( null === $redirect ) {
			WP_CLI::error( sprintf( 'Redirect not found: %s', $lookup ) );
			return;
		}

		// Build output data.
		$dest = $redirect->destination();
		$data = array(
			'ID'     => $redirect->id(),
			'from'   => $redirect->source()->path(),
			'to'     => $dest->is_post_id()
				? $dest->as_post_id()->value()
				: $dest->as_url()->value(),
			'type'   => $dest->is_post_id() ? 'post' : 'url',
			'status' => $redirect->is_active() ? 'enabled' : 'disabled',
			'hash'   => $redirect->source()->hash(),
		);

		// Return single field if requested.
		if ( null !== $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				WP_CLI::error( sprintf( 'Invalid field: %s. Available fields: %s', $field, implode( ', ', array_keys( $data ) ) ) );
				return;
			}
			WP_CLI::line( (string) $data[ $field ] );
			return;
		}

		// Format as key-value pairs for table.
		if ( 'table' === $format ) {
			$items = array();
			foreach ( $data as $key => $value ) {
				$items[] = array(
					'Field' => $key,
					'Value' => $value,
				);
			}
			\WP_CLI\Utils\format_items( 'table', $items, array( 'Field', 'Value' ) );
			return;
		}

		// Other formats.
		\WP_CLI\Utils\format_items( $format, array( $data ), array_keys( $data ) );
	}
}
