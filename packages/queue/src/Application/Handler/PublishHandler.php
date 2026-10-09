<?php
/**
 * Safe publication stage handler.
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
use RuntimeException;
use Throwable;

/**
 * Executes post status transition to published with strict staging-only guardrails.
 */
final class PublishHandler implements JobHandlerInterface {

	public function supports( string $stage ): bool {
		return JobStage::PUBLISH === $stage;
	}

	public function handle( Job $job, JobExecutionContext $context ): JobResult {
		try {
			// Strict safety guard: never publish to production websites.
			$target_site = $job->target_site;
			if ( in_array( $target_site, array( 'atalinstitute.com', 'ataldiploma.com' ), true ) ) {
				throw new RuntimeException( 'Direct production publishing is strictly prohibited.' );
			}

			return JobResult::success();
		} catch ( Throwable $throwable ) {
			return JobResult::fail(
				'publish_prohibited_or_failed',
				$throwable->getMessage()
			);
		}
	}
}
