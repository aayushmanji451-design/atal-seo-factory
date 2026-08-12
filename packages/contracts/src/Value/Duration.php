<?php
/**
 * Duration value object.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Value;

use InvalidArgumentException;

/**
 * Immutable approved duration.
 */
final class Duration {

	/**
	 * Create a duration.
	 *
	 * @param string $display           Approved display value.
	 * @param int    $normalized_months Duration in months.
	 *
	 * @throws InvalidArgumentException When the duration is invalid.
	 */
	public function __construct(
		private readonly string $display,
		private readonly int $normalized_months
	) {
		if ( '' === trim( $display ) ) {
			throw new InvalidArgumentException( 'Duration display value cannot be empty.' );
		}

		if ( 1 > $normalized_months ) {
			throw new InvalidArgumentException( 'Duration must be at least one month.' );
		}
	}

	/**
	 * Return the display value.
	 */
	public function display(): string {
		return $this->display;
	}

	/**
	 * Return normalized months.
	 */
	public function normalized_months(): int {
		return $this->normalized_months;
	}
}
