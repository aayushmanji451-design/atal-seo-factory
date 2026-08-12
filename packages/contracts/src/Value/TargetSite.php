<?php
/**
 * Target site value object.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Value;

use InvalidArgumentException;

/**
 * Immutable target-site boundary.
 */
final class TargetSite {

	public const INSTITUTE = 'atal_institute';

	public const DIPLOMA = 'atal_diploma';

	/**
	 * Validated site value.
	 *
	 * @var string
	 */
	private readonly string $value;

	/**
	 * Create a target site.
	 *
	 * @param string $value Raw site value.
	 *
	 * @throws InvalidArgumentException When the site is unsupported.
	 */
	public function __construct( string $value ) {
		if ( ! in_array( $value, array( self::INSTITUTE, self::DIPLOMA ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported target site: ' . $value );
		}

		$this->value = $value;
	}

	/**
	 * Return the site key.
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Determine whether this is the Institute site.
	 */
	public function is_institute(): bool {
		return self::INSTITUTE === $this->value;
	}

	/**
	 * Determine whether this is the Diploma site.
	 */
	public function is_diploma(): bool {
		return self::DIPLOMA === $this->value;
	}

	/**
	 * Return the site key for string contexts.
	 */
	public function __toString(): string {
		return $this->value;
	}
}
