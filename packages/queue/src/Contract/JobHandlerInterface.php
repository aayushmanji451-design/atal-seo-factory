<?php
/**
 * Contract for bounded stage handlers.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Contract;

use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobExecutionContext;
use Atal\Queue\Domain\JobResult;

/**
 * Executes exactly one bounded unit of work.
 */
interface JobHandlerInterface {

	/**
	 * Check if handler processes the given pipeline stage.
	 *
	 * @param string $stage Stage name from JobStage constants.
	 */
	public function supports( string $stage ): bool;

	/**
	 * Execute the bounded unit.
	 *
	 * @param Job                $job     Job being executed.
	 * @param JobExecutionContext $context Execution environment.
	 */
	public function handle( Job $job, JobExecutionContext $context ): JobResult;
}
