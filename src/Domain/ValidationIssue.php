<?php
/**
 * ValidationIssue value object.
 *
 * @package Automattic\LegacyRedirector\Domain
 */

declare( strict_types = 1 );

namespace Automattic\LegacyRedirector\Domain;

/**
 * Represents a validation issue found with a redirect's destination.
 *
 * Immutable value object that captures what redirect has an issue,
 * what type of issue it is, and any additional details.
 */
final class ValidationIssue {

	/**
	 * The redirect that has the issue.
	 *
	 * @var Redirect
	 */
	private Redirect $redirect;

	/**
	 * The type of issue.
	 *
	 * @var ValidationIssueType
	 */
	private ValidationIssueType $type;

	/**
	 * Optional extra information about the issue.
	 *
	 * @var string|null
	 */
	private ?string $extra_info;

	/**
	 * Constructor.
	 *
	 * @param Redirect            $redirect   The redirect with the issue.
	 * @param ValidationIssueType $type       The type of issue.
	 * @param string|null         $extra_info Optional extra information (e.g., HTTP status code, post status).
	 */
	public function __construct(
		Redirect $redirect,
		ValidationIssueType $type,
		?string $extra_info = null
	) {
		$this->redirect   = $redirect;
		$this->type       = $type;
		$this->extra_info = $extra_info;
	}

	/**
	 * Get the redirect.
	 *
	 * @return Redirect
	 */
	public function redirect(): Redirect {
		return $this->redirect;
	}

	/**
	 * Get the issue type.
	 *
	 * @return ValidationIssueType
	 */
	public function type(): ValidationIssueType {
		return $this->type;
	}

	/**
	 * Get the extra info.
	 *
	 * @return string|null
	 */
	public function extra_info(): ?string {
		return $this->extra_info;
	}

	/**
	 * Get the human-readable issue label.
	 *
	 * @return string
	 */
	public function label(): string {
		return $this->type->label();
	}

	/**
	 * Get the detailed issue description.
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->type->description( $this->extra_info );
	}

	/**
	 * Check if the redirect is broken and should be disabled.
	 *
	 * @return bool
	 */
	public function is_broken(): bool {
		return $this->type->is_broken();
	}

	/**
	 * Get the redirect ID.
	 *
	 * Convenience method for output formatting.
	 *
	 * @return int|null
	 */
	public function redirect_id(): ?int {
		return $this->redirect->id();
	}

	/**
	 * Get the redirect source path.
	 *
	 * Convenience method for output formatting.
	 *
	 * @return string
	 */
	public function source_path(): string {
		return $this->redirect->source()->path();
	}

	/**
	 * Get the destination as a string (URL or post ID).
	 *
	 * Convenience method for output formatting.
	 *
	 * @return string
	 */
	public function destination_string(): string {
		$dest = $this->redirect->destination();

		if ( $dest->is_post_id() ) {
			return (string) $dest->as_post_id()->value();
		}

		return $dest->as_url()->value();
	}

	/**
	 * Get the destination type label.
	 *
	 * @return string Either 'post' or 'url'.
	 */
	public function destination_type(): string {
		return $this->redirect->destination()->is_post_id() ? 'post' : 'url';
	}

	/**
	 * Get the redirect status label.
	 *
	 * @return string Either 'enabled' or 'disabled'.
	 */
	public function status_label(): string {
		return $this->redirect->is_active() ? 'enabled' : 'disabled';
	}

	/**
	 * Convert to array for CLI output formatting.
	 *
	 * @return array{ID: int|null, from: string, to: string, type: string, issue: string, status: string}
	 */
	public function to_array(): array {
		return array(
			'ID'     => $this->redirect_id(),
			'from'   => $this->source_path(),
			'to'     => $this->destination_string(),
			'type'   => $this->destination_type(),
			'issue'  => $this->label(),
			'status' => $this->status_label(),
		);
	}
}
