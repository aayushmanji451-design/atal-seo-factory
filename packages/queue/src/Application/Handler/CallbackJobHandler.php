<?php
/**
 * Callback-driven stage handler.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Application\Handler;

use Atal\Queue\Contract\JobHandlerInterface;
use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobExecutionContext;
use Atal\Queue\Domain\JobResult;
use Closure;

/**
 * Executes a stage using an injected closure, ideal for decoupled adapters and tests.
 */
final class CallbackJobHandler implements JobHandlerInterface {

	/**
	 * Create callback handler.
	 *
	 * @param string                                                 $stage    Supported pipeline stage.
	 * @param Closure(Job, JobExecutionContext):JobResult $callback Handler logic.
	 */
	public function __construct(
		private readonly string $stage,
		private readonly Closure $callback
	) {
	}

	public function supports( string $stage ): bool {
		return $this->stage === $stage;
	}

	public function handle( Job $job, JobExecutionContext $context ): JobResult {
		$fn = $this->callback;

		return $fn( $job, $context );
	}
}
