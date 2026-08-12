<?php
/**
 * Validation issue value object.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

/**
 * Immutable validation failure.
 */
final class ValidationIssue {

	/**
	 * Create a validation issue.
	 *
	 * @param string $code    Stable machine-readable code.
	 * @param string $message Human-readable explanation.
	 * @param string $path    Contract path associated with the issue.
	 */
	public function __construct(
		private readonly string $code,
		private readonly string $message,
		private readonly string $path = '$'
	) {
	}

	/**
	 * Return the issue code.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * Return the issue message.
	 */
	public function message(): string {
		return $this->message;
	}

	/**
	 * Return the contract path.
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * Format the issue for CLI output.
	 */
	public function format(): string {
		return sprintf( '%s at %s: %s', $this->code, $this->path, $this->message );
	}
}
