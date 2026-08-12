<?php
/**
 * Canonical course key value object.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Value;

use InvalidArgumentException;

/**
 * Immutable identifier for one canonical active course identity.
 */
final class CourseKey {

	/**
	 * Validated key value.
	 *
	 * @var string
	 */
	private readonly string $value;

	/**
	 * Create a course key.
	 *
	 * @param string $value Raw key value.
	 *
	 * @throws InvalidArgumentException When the key is not canonical.
	 */
	public function __construct( string $value ) {
		if ( 1 !== preg_match( '/^(institute|diploma)_[a-z0-9_]+$/', $value ) ) {
			throw new InvalidArgumentException( 'Invalid canonical course key: ' . $value );
		}

		$this->value = $value;
	}

	/**
	 * Return the canonical key.
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Compare course keys.
	 *
	 * @param self $other Other key.
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * Return the key for string contexts.
	 */
	public function __toString(): string {
		return $this->value;
	}
}
