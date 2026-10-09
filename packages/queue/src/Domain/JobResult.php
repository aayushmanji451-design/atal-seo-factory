<?php
/**
 * Stage handler result.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

/**
 * Explicit outcome returned by a stage handler.
 */
final class JobResult {

	public const SUCCESS = 'success';
	public const RETRY   = 'retry';
	public const FAIL    = 'fail';
	public const DEFER   = 'defer';

	/**
	 * Create a result.
	 *
	 * @param string $status       Result status.
	 * @param string $code         Machine-readable code.
	 * @param string $message      Redacted human-readable message.
	 * @param int    $available_at Reschedule time for a deferral.
	 */
	private function __construct(
		public readonly string $status,
		public readonly string $code,
		public readonly string $message,
		public readonly int $available_at
	) {
	}

	/**
	 * Stage completed.
	 */
	public static function success(): self {
		return new self( self::SUCCESS, '', '', 0 );
	}

	/**
	 * Transient failure; retry with backoff.
	 *
	 * @param string $code    Failure code.
	 * @param string $message Failure message.
	 */
	public static function retry( string $code, string $message ): self {
		return new self( self::RETRY, $code, $message, 0 );
	}

	/**
	 * Permanent failure; dead-letter immediately.
	 *
	 * @param string $code    Failure code.
	 * @param string $message Failure message.
	 */
	public static function fail( string $code, string $message ): self {
		return new self( self::FAIL, $code, $message, 0 );
	}

	/**
	 * Postpone without consuming an attempt.
	 *
	 * @param int    $available_at New earliest run time.
	 * @param string $code         Reason code.
	 * @param string $message      Reason message.
	 */
	public static function defer( int $available_at, string $code, string $message = '' ): self {
		return new self( self::DEFER, $code, $message, $available_at );
	}
}
