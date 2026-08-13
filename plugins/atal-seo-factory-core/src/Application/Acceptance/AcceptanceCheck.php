<?php
/**
 * One Task 02 staging acceptance result.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

use InvalidArgumentException;

/**
 * Carries a machine-readable status and sanitized evidence.
 */
final class AcceptanceCheck {

	public const PASS = 'PASS';

	public const WARNING = 'WARNING';

	public const FAIL = 'FAIL';

	public function __construct(
		private readonly string $check_id,
		private readonly string $status,
		private readonly mixed $expected,
		private readonly mixed $actual,
		private readonly string $message
	) {
		if ( ! in_array( $status, array( self::PASS, self::WARNING, self::FAIL ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported acceptance status.' );
		}
	}

	public function status(): string {
		return $this->status;
	}

	/**
	 * @return array{check_id:string,status:string,expected:mixed,actual:mixed,message:string}
	 */
	public function to_array(): array {
		return array(
			'check_id' => $this->check_id,
			'status'   => $this->status,
			'expected' => $this->expected,
			'actual'   => $this->actual,
			'message'  => $this->message,
		);
	}
}
