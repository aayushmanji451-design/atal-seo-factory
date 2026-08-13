<?php
/**
 * Task 02 acceptance check result.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

/**
 * Immutable machine-readable acceptance check.
 */
final class AcceptanceCheck {

	public const PASS = 'PASS';

	public const WARNING = 'WARNING';

	public const FAIL = 'FAIL';

	public function __construct(
		private readonly string $check_id,
		private readonly string $status,
		private readonly string $expected,
		private readonly string $actual,
		private readonly string $message
	) {
	}

	public function status(): string {
		return $this->status;
	}

	/**
	 * @return array{check_id:string,status:string,expected:string,actual:string,message:string}
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
