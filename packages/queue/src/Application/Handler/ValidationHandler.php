<?php
/**
 * Quality gate validation stage handler.
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
use Atal\Topics\Application\TopicValidator;
use Atal\Topics\Domain\QualityState;
use Atal\Topics\Domain\TopicProposal;
use Throwable;

/**
 * Validates canonical contracts, intents, claims, and uniqueness before proceeding.
 */
final class ValidationHandler implements JobHandlerInterface {

	public function __construct(
		private readonly ?TopicValidator $validator = null
	) {
	}

	public function supports( string $stage ): bool {
		return JobStage::VALIDATE === $stage;
	}

	public function handle( Job $job, JobExecutionContext $context ): JobResult {
		try {
			$proposal_data = $job->payload['proposal'] ?? null;
			if ( null === $this->validator || ! is_array( $proposal_data ) ) {
				// If no proposal data is supplied or validator is not configured, pass as validated.
				return JobResult::success();
			}

			$proposal = TopicProposal::from_array( $proposal_data );
			$report   = $this->validator->validate( $proposal );

			if ( QualityState::PASS !== $report->state() ) {
				return JobResult::fail(
					'validation_failed',
					'Article failed deterministic quality gates with state: ' . $report->state()
				);
			}

			return JobResult::success();
		} catch ( Throwable $throwable ) {
			return JobResult::retry(
				'validation_exception',
				$throwable->getMessage()
			);
		}
	}
}
