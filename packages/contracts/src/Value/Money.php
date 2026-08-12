<?php
/**
 * Money value object.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Value;

use InvalidArgumentException;

/**
 * Immutable fee amount with its currency and approved display value.
 */
final class Money {

	/**
	 * Create a monetary value.
	 *
	 * @param int    $amount   Whole-currency amount.
	 * @param string $currency ISO currency code.
	 * @param string $display  Approved display value.
	 *
	 * @throws InvalidArgumentException When the monetary value is invalid.
	 */
	public function __construct(
		private readonly int $amount,
		private readonly string $currency,
		private readonly string $display
	) {
		if ( 0 > $amount ) {
			throw new InvalidArgumentException( 'Fee amount cannot be negative.' );
		}

		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			throw new InvalidArgumentException( 'Currency must be a three-letter ISO code.' );
		}

		if ( '' === trim( $display ) ) {
			throw new InvalidArgumentException( 'Fee display value cannot be empty.' );
		}
	}

	/**
	 * Return the amount.
	 */
	public function amount(): int {
		return $this->amount;
	}

	/**
	 * Return the currency.
	 */
	public function currency(): string {
		return $this->currency;
	}

	/**
	 * Return the display value.
	 */
	public function display(): string {
		return $this->display;
	}
}
