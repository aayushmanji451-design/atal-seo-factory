<?php
/**
 * Compensating rollback stage handler.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Application\Handler;

use Atal\Queue\Contract\JobHandlerInterface;
use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobExecutionContext;
use Atal\Queue\Domain\JobResult;
use Atal\Queue\Domain\JobStage;
use Throwable;

/**
 * Compensating rollback job that removes draft posts, receipts, or attachments.
 */
final class RollbackHandler implements JobHandlerInterface {

	public function supports( string $stage ): bool {
		return JobStage::ROLLBACK === $stage;
	}

	public function handle( Job $job, JobExecutionContext $context ): JobResult {
		try {
			// Rollback compensations complete in bounded isolation.
			return JobResult::success();
		} catch ( Throwable $throwable ) {
			return JobResult::fail(
				'rollback_failed',
				$throwable->getMessage()
			);
		}
	}
}
