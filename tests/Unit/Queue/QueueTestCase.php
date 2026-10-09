<?php
/**
 * Task 07 Queue test case.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Queue;

use Atal\Queue\Application\DailyBatchCoordinator;
use Atal\Queue\Application\Handler\DeliverDraftHandler;
use Atal\Queue\Application\Handler\PublishHandler;
use Atal\Queue\Application\Handler\RenderImageHandler;
use Atal\Queue\Application\Handler\RollbackHandler;
use Atal\Queue\Application\Handler\SaveSeoHandler;
use Atal\Queue\Application\Handler\ValidationHandler;
use Atal\Queue\Application\Handler\VerifyHandler;
use Atal\Queue\Application\QueueWorker;
use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobStage;
use Atal\Queue\Domain\JobState;
use Atal\Queue\Domain\QueueConfig;
use Atal\Queue\Domain\QueueControlState;
use Atal\Queue\Domain\RetryPolicy;
use Atal\Queue\Infrastructure\InMemory\InMemoryBatchRepository;
use Atal\Queue\Infrastructure\InMemory\InMemoryJobRepository;
use Atal\Queue\Infrastructure\InMemory\InMemoryQueueControlStore;
use Atal\Tests\Support\Topics\InMemoryRotationStateStore;
use Atal\Tests\Unit\Topics\TopicTestCase;
use Atal\Topics\Application\DeterministicRotation;

/**
 * Base fixture for background queue and daily automation tests.
 */
abstract class QueueTestCase extends TopicTestCase {

	protected InMemoryJobRepository $job_repo;

	protected InMemoryBatchRepository $batch_repo;

	protected InMemoryQueueControlStore $control_store;

	protected DeterministicRotation $rotation;

	protected function setUp(): void {
		parent::setUp();
		$this->job_repo      = new InMemoryJobRepository();
		$this->batch_repo    = new InMemoryBatchRepository();
		$this->control_store = new InMemoryQueueControlStore();
		$this->rotation      = new DeterministicRotation( new InMemoryRotationStateStore() );
	}

	protected function create_worker( ?RetryPolicy $retry_policy = null ): QueueWorker {
		$handlers = array(
			new ValidationHandler( $this->validator ),
			new RenderImageHandler(),
			new SaveSeoHandler(),
			new DeliverDraftHandler(),
			new VerifyHandler(),
			new PublishHandler(),
			new RollbackHandler(),
		);

		return new QueueWorker(
			$this->job_repo,
			$this->control_store,
			$this->batch_repo,
			$retry_policy ?? new RetryPolicy(),
			$handlers
		);
	}

	protected function create_coordinator(): DailyBatchCoordinator {
		return new DailyBatchCoordinator(
			$this->policy,
			$this->rotation,
			$this->validator,
			$this->job_repo,
			$this->batch_repo,
			$this->control_store
		);
	}

	/**
	 * Create a test job.
	 *
	 * @param string               $stage       Stage identifier.
	 * @param string               $site        Target site.
	 * @param string               $article_key Unique article key.
	 * @param array<string,mixed>  $payload     Custom payload.
	 * @param int                  $available_at Available timestamp.
	 */
	protected function make_job(
		string $stage = JobStage::VALIDATE,
		string $site = 'atal_institute',
		string $article_key = 'test_article_01',
		array $payload = array(),
		int $available_at = 1000
	): Job {
		return Job::create(
			$article_key,
			'batch_test',
			$stage,
			$site,
			$available_at,
			5,
			$available_at,
			0,
			$payload
		);
	}
}
