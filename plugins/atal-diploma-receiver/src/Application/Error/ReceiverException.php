<?php
/** Exact receiver error. @package AtalDiplomaReceiver */

declare(strict_types=1);

namespace Atal\DiplomaReceiver\Application\Error;

use RuntimeException;

final class ReceiverException extends RuntimeException {
	/** @param array<string,mixed> $details Non-sensitive validation details. */
	public function __construct(
		private readonly string $error_code,
		string $message,
		private readonly int $http_status,
		private readonly array $details = array()
	) {
		parent::__construct( $message );
	}

	public function error_code(): string {
		return $this->error_code;
	}

	public function http_status(): int {
		return $this->http_status;
	}

	/** @return array<string,mixed> */
	public function details(): array {
		return $this->details;
	}
}
