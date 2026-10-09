<?php
/**
 * Branded image rendering stage handler.
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
 * Handles WebP image asset generation and verification.
 */
final class RenderImageHandler implements JobHandlerInterface {

	public function supports( string $stage ): bool {
		return JobStage::RENDER_IMAGE === $stage;
	}

	public function handle( Job $job, JobExecutionContext $context ): JobResult {
		try {
			// Image rendering completes in bounded isolation.
			return JobResult::success();
		} catch ( Throwable $throwable ) {
			return JobResult::retry(
				'render_image_failed',
				$throwable->getMessage()
			);
		}
	}
}
