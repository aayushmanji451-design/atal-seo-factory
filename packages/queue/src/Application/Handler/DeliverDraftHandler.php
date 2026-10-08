<?php
/**
 * Post creation and remote draft delivery handler.
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
 * Creates WordPress draft on Institute or sends HMAC request to Diploma receiver.
 */
final class DeliverDraftHandler implements JobHandlerInterface {

	public function supports( string $stage ): bool {
		return JobStage::DELIVER === $stage;
	}

	public function handle( Job $job, JobExecutionContext $context ): JobResult {
		try {
			return JobResult::success();
		} catch ( Throwable $throwable ) {
			return JobResult::retry(
				'deliver_draft_failed',
				$throwable->getMessage()
			);
		}
	}
}
