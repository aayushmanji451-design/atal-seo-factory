<?php
/**
 * Bounded queue worker executing jobs with leases, retry backoff, and isolation.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Application;

use Atal\Queue\Contract\BatchRepositoryInterface;
use Atal\Queue\Contract\JobHandlerInterface;
use Atal\Queue\Contract\JobRepositoryInterface;
use Atal\Queue\Contract\QueueControlStoreInterface;
use Atal\Queue\Domain\DailyBatchItem;
use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobAttempt;
use Atal\Queue\Domain\JobExecutionContext;
use Atal\Queue\Domain\JobResult;
use Atal\Queue\Domain\JobStage;
use Atal\Queue\Domain\QueueControlState;
use Atal\Queue\Domain\RetryPolicy;
use Atal\Queue\Support\Arr;
use Throwable;

/**
 * Executes one bounded unit per step, isolating failures and respecting caps.
 */
final class QueueWorker {

	/**
	 * Create worker.
	 *
	 * @param JobRepositoryInterface          $jobs          Job repository.
	 * @param QueueControlStoreInterface      $controls      Control state and config.
	 * @param BatchRepositoryInterface|null   $batches       Daily batch repository.
	 * @param RetryPolicy                     $retry_policy  Capped backoff retry policy.
	 * @param list<JobHandlerInterface>       $handlers      Registered stage handlers.
	 * @param array<string,mixed>             $services      Injected runtime dependencies.
	 */
	public function __construct(
		private readonly JobRepositoryInterface $jobs,
		private readonly QueueControlStoreInterface $controls,
		private readonly ?BatchRepositoryInterface $batches = null,
		private readonly RetryPolicy $retry_policy = new RetryPolicy(),
		private readonly array $handlers = array(),
		private readonly array $services = array()
	) {
	}

	/**
	 * Run a single bounded step.
	 *
	 * @param string      $worker_id Worker identifier.
	 * @param int         $now       Current timestamp (0 for gmdate('U')).
	 * @param string|null $group     Optional stage group filter.
	 */
	public function run_step( string $worker_id, int $now = 0, ?string $group = null ): ?JobExecutionSummary {
		$time = 0 === $now ? (int) gmdate( 'U' ) : $now;

		// Check global queue control state.
		$state = $this->controls->get_state();
		if ( ! QueueControlState::can_execute( $state ) ) {
			return null;
		}

		// Reclaim expired leases from crashed workers (safe restart).
		$this->jobs->reclaim_expired_leases( $time );

		$config = $this->controls->get_config();

		// Claim the next available job atomically.
		$job = $this->jobs->claim_next( $worker_id, $config->worker_lease_seconds, $time, $group );
		if ( null === $job ) {
			return null;
		}

		// Enforce publishing cap before executing a publish stage.
		if ( JobStage::PUBLISH === $job->stage ) {
			$day_start  = (int) strtotime( 'today UTC', $time );
			$day_end    = $day_start + 86399;
			$published  = $this->jobs->count_published_today( $job->target_site, $day_start, $day_end );
			$site_limit = $config->limit_for_site( $job->target_site );

			if ( $published >= $site_limit || $published >= $config->publishing_daily_limit ) {
				// Defer to later without counting as failure.
				$deferred_time = $time + 1800;
				$this->jobs->record_failure( $job->job_key, $worker_id, 'publishing_cap_reached', 'Daily publishing limit reached; postponed.', $deferred_time, $time );

				$attempt = new JobAttempt(
					$job->job_key,
					$job->attempts + 1,
					$job->stage,
					$worker_id,
					$time,
					$time,
					JobAttempt::DEFERRED,
					'publishing_cap_reached',
					'Daily publishing limit reached; postponed.',
					$deferred_time
				);
				$this->jobs->record_attempt( $attempt );

				return new JobExecutionSummary( $job, JobResult::defer( $deferred_time, 'publishing_cap_reached' ), $time, $time, 'deferred' );
			}
		}

		$handler = $this->find_handler( $job->stage );
		if ( null === $handler ) {
			$code    = 'unsupported_stage';
			$message = 'No handler registered for stage ' . $job->stage;
			$ended   = $time;

			$this->jobs->record_dead_letter( $job->job_key, $worker_id, $code, $message, $ended );
			$this->jobs->record_attempt(
				new JobAttempt( $job->job_key, $job->attempts, $job->stage, $worker_id, $time, $ended, JobAttempt::DEAD, $code, $message )
			);

			$this->update_batch_item_status( $job, DailyBatchItem::STATUS_FAILED, $message );

			return new JobExecutionSummary( $job, JobResult::fail( $code, $message ), $time, $ended, 'dead' );
		}

		$context = new JobExecutionContext( $worker_id, $time, $this->services );

		try {
			$result = $handler->handle( $job, $context );
		} catch ( Throwable $throwable ) {
			$result = JobResult::retry(
				'unhandled_exception',
				Arr::safe_message( $throwable->getMessage() )
			);
		}

		$ended = 0 === $now ? (int) gmdate( 'U' ) : $now;
		if ( $ended < $time ) {
			$ended = $time;
		}

		return $this->process_result( $job, $result, $worker_id, $time, $ended, $config );
	}

	/**
	 * Run up to max_steps bounded units.
	 *
	 * @param string      $worker_id Worker ID.
	 * @param int         $max_steps Maximum steps to execute.
	 * @param int         $now       Current timestamp.
	 * @param string|null $group     Optional stage group filter.
	 *
	 * @return list<JobExecutionSummary>
	 */
	public function run_batch( string $worker_id, int $max_steps = 10, int $now = 0, ?string $group = null ): array {
		$results = array();
		$time    = 0 === $now ? (int) gmdate( 'U' ) : $now;

		for ( $i = 0; $i < $max_steps; ++$i ) {
			$summary = $this->run_step( $worker_id, $time, $group );
			if ( null === $summary ) {
				break;
			}
			$results[] = $summary;
		}

		return $results;
	}

	/**
	 * Process the outcome of a handler execution.
	 *
	 * @param Job                               $job       Job.
	 * @param JobResult                         $result    Result.
	 * @param string                            $worker_id Worker ID.
	 * @param int                               $started   Start time.
	 * @param int                               $ended     End time.
	 * @param \Atal\Queue\Domain\QueueConfig    $config    Queue configuration.
	 */
	private function process_result( Job $job, JobResult $result, string $worker_id, int $started, int $ended, $config ): JobExecutionSummary {
		if ( JobResult::SUCCESS === $result->status ) {
			$this->jobs->complete( $job->job_key, $worker_id, $ended );
			$this->jobs->record_attempt(
				new JobAttempt( $job->job_key, $job->attempts, $job->stage, $worker_id, $started, $ended, JobAttempt::SUCCESS )
			);

			$this->advance_pipeline( $job, $ended, $config );

			return new JobExecutionSummary( $job, $result, $started, $ended, 'succeeded' );
		}

		if ( JobResult::DEFER === $result->status ) {
			$this->jobs->record_failure( $job->job_key, $worker_id, $result->code, $result->message, $result->available_at, $ended );
			$this->jobs->record_attempt(
				new JobAttempt( $job->job_key, $job->attempts, $job->stage, $worker_id, $started, $ended, JobAttempt::DEFERRED, $result->code, $result->message, $result->available_at )
			);

			return new JobExecutionSummary( $job, $result, $started, $ended, 'deferred' );
		}

		if ( JobResult::FAIL === $result->status || $this->retry_policy->exhausted( $job->attempts ) ) {
			// Fatal or retries exhausted: move to dead letter.
			$this->jobs->record_dead_letter( $job->job_key, $worker_id, $result->code, $result->message, $ended );
			$this->jobs->record_attempt(
				new JobAttempt( $job->job_key, $job->attempts, $job->stage, $worker_id, $started, $ended, JobAttempt::DEAD, $result->code, $result->message )
			);

			// Failure isolation: flag this batch item without stopping any other item in the batch!
			$this->update_batch_item_status( $job, DailyBatchItem::STATUS_FAILED, $result->message );

			return new JobExecutionSummary( $job, $result, $started, $ended, 'dead' );
		}

		// Transient failure: schedule retry with capped exponential backoff.
		$delay    = $this->retry_policy->delay_for( $job->attempts, $job->job_key );
		$retry_at = $ended + $delay;

		$this->jobs->record_failure( $job->job_key, $worker_id, $result->code, $result->message, $retry_at, $ended );
		$this->jobs->record_attempt(
			new JobAttempt( $job->job_key, $job->attempts, $job->stage, $worker_id, $started, $ended, JobAttempt::RETRY, $result->code, $result->message, $retry_at )
		);

		return new JobExecutionSummary( $job, $result, $started, $ended, 'retrying' );
	}

	/**
	 * Transition an item to the next pipeline stage.
	 *
	 * @param Job                            $job    Current job.
	 * @param int                            $ended  Completion epoch.
	 * @param \Atal\Queue\Domain\QueueConfig $config Configuration.
	 */
	private function advance_pipeline( Job $job, int $ended, $config ): void {
		$next_stage = JobStage::next( $job->stage );

		if ( null !== $next_stage ) {
			// For publishing stage, stagger by configured interval to avoid publishing all at once.
			$available_at = $ended;
			if ( JobStage::PUBLISH === $next_stage ) {
				$available_at = $ended + $config->stagger_interval_seconds;
			}

			$next_job = Job::create(
				$job->item_key,
				$job->batch_key,
				$next_stage,
				$job->target_site,
				$available_at,
				$config->max_job_attempts,
				$ended,
				0,
				$job->payload
			);

			$this->jobs->enqueue( $next_job );
			$this->update_batch_item_status( $job, DailyBatchItem::STATUS_RUNNING, '', $next_stage );
		} elseif ( JobStage::PUBLISH === $job->stage ) {
			// Pipeline completed.
			$this->update_batch_item_status( $job, DailyBatchItem::STATUS_PUBLISHED, '', 'completed' );
		}
	}

	/**
	 * Update daily batch item state safely.
	 *
	 * @param Job         $job     Job.
	 * @param string      $status  New status.
	 * @param string      $error   Optional error message.
	 * @param string|null $stage   Optional stage.
	 */
	private function update_batch_item_status( Job $job, string $status, string $error = '', ?string $stage = null ): void {
		if ( null === $this->batches || '' === $job->batch_key ) {
			return;
		}

		$batch = $this->batches->find( $job->batch_key );
		if ( null === $batch ) {
			return;
		}

		$item = $batch->items[ $job->item_key ] ?? null;
		if ( null === $item ) {
			return;
		}

		$changes = array(
			'status' => $status,
		);
		if ( '' !== $error ) {
			$changes['last_error'] = $error;
		}
		if ( null !== $stage ) {
			$changes['current_stage'] = $stage;
		}

		$updated_item  = $item->with( $changes );
		$updated_batch = $batch->with_item( $updated_item );

		$this->batches->save( $updated_batch );
	}

	/**
	 * Find a handler supporting the given stage.
	 *
	 * @param string $stage Stage name.
	 */
	private function find_handler( string $stage ): ?JobHandlerInterface {
		foreach ( $this->handlers as $handler ) {
			if ( $handler->supports( $stage ) ) {
				return $handler;
			}
		}

		return null;
	}
}
