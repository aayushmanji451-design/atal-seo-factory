<?php
/**
 * Context passed to job handlers during execution.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

/**
 * Execution environment and metadata for a running job.
 */
final class JobExecutionContext {

	/**
	 * Create context.
	 *
	 * @param string              $worker_id Worker identifier.
	 * @param int                 $now       Current timestamp.
	 * @param array<string,mixed> $services  Shared runtime dependencies.
	 */
	public function __construct(
		public readonly string $worker_id,
		public readonly int $now,
		public readonly array $services = array()
	) {
	}

	/**
	 * Get an injected service.
	 *
	 * @param string $name Service name.
	 */
	public function service( string $name ): mixed {
		return $this->services[ $name ] ?? null;
	}
}
