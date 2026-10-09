<?php
/**
 * QueueWorker unit tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Queue;

use Atal\Queue\Application\Handler\CallbackJobHandler;
use Atal\Queue\Application\QueueWorker;
use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobExecutionContext;
use Atal\Queue\Domain\JobResult;
use Atal\Queue\Domain\JobStage;
use Atal\Queue\Domain\JobState;
use Atal\Queue\Domain\QueueConfig;
use Atal\Queue\Domain\QueueControlState;
use Atal\Queue\Domain\RetryPolicy;
use RuntimeException;

/**
 * Tests bounded execution, locking, leases, retry backoff, dead-letter, isolation, and safety stops.
 */
final class QueueWorkerTest extends QueueTestCase {

	public function test_run_step_claims_and_executes_stage_advancement(): void {
		$worker = $this->create_worker();
		$job    = $this->make_job( JobStage::RENDER_IMAGE, 'atal_institute', 'art_01', array(), 1000 );
		$this->job_repo->enqueue( $job );

		$summary = $worker->run_step( 'worker_1', 1000 );

		self::assertNotNull( $summary );
		self::assertSame( $job->job_key, $summary->job->job_key );
		self::assertSame( JobStage::RENDER_IMAGE, $summary->job->stage );
		self::assertSame( JobResult::SUCCESS, $summary->result->status );
		self::assertSame( 'succeeded', $summary->status );

		// Original job is succeeded.
		$finished = $this->job_repo->find_by_key( $job->job_key );
		self::assertNotNull( $finished );
		self::assertSame( JobState::SUCCEEDED, $finished->state );

		// Next stage job is enqueued in pending state.
		$next_job = $this->job_repo->find_by_item_and_stage( 'art_01', JobStage::SAVE_SEO );
		self::assertNotNull( $next_job );
		self::assertSame( JobState::PENDING, $next_job->state );
	}

	public function test_pipeline_advances_to_terminal_succeeded_on_last_stage(): void {
		$worker = $this->create_worker();
		$job    = $this->make_job(
			JobStage::PUBLISH,
			'atal_institute',
			'art_terminal',
			array( 'target_domain' => 'liveup2.atalinstitute.com' ),
			1000
		);
		$this->job_repo->enqueue( $job );

		$summary = $worker->run_step( 'worker_1', 1000 );

		self::assertNotNull( $summary );
		self::assertSame( JobResult::SUCCESS, $summary->result->status );
		self::assertSame( 'succeeded', $summary->status );

		$completed = $this->job_repo->find_by_key( $job->job_key );
		self::assertNotNull( $completed );
		self::assertSame( JobState::SUCCEEDED, $completed->state );
		self::assertSame( 1000, $completed->updated_at );
	}

	public function test_worker_lease_locks_job_preventing_concurrent_claim(): void {
		$job = $this->make_job( JobStage::RENDER_IMAGE, 'atal_institute', 'art_lock', array(), 1000 );
		$this->job_repo->enqueue( $job );

		// Worker 1 claims job with 300s lease.
		$claimed = $this->job_repo->claim_next( 'worker_1', 300, 1000 );
		self::assertNotNull( $claimed );
		self::assertSame( 'worker_1', $claimed->lease_owner );
		self::assertSame( 1300, $claimed->lease_expires_at );

		// Worker 2 attempts claim while lease is active at timestamp 1100.
		$concurrent = $this->job_repo->claim_next( 'worker_2', 300, 1100 );
		self::assertNull( $concurrent );
	}

	public function test_safe_restart_reclaims_expired_leases(): void {
		$worker = $this->create_worker();
		$job    = $this->make_job( JobStage::RENDER_IMAGE, 'atal_institute', 'art_crash', array(), 1000 );
		$this->job_repo->enqueue( $job );

		// Worker 1 claims job with lease expiring at 1300.
		$this->job_repo->claim_next( 'crashed_worker', 300, 1000 );

		// At timestamp 1301, new worker runs step and safely reclaims the lease.
		$summary = $worker->run_step( 'recovery_worker', 1301 );

		self::assertNotNull( $summary );
		self::assertSame( $job->job_key, $summary->job->job_key );
		self::assertSame( JobResult::SUCCESS, $summary->result->status );
	}

	public function test_transient_failure_records_exponential_backoff(): void {
		$failing_handler = new CallbackJobHandler(
			JobStage::RENDER_IMAGE,
			static function ( Job $job, JobExecutionContext $ctx ): JobResult {
				unset( $job, $ctx );

				return JobResult::retry( 'timeout', 'Simulated transient network timeout' );
			}
		);
		$worker          = new QueueWorker(
			$this->job_repo,
			$this->control_store,
			$this->batch_repo,
			new RetryPolicy( 5, 60, 1800 ),
			array( $failing_handler )
		);

		$job = $this->make_job( JobStage::RENDER_IMAGE, 'atal_institute', 'art_retry', array(), 1000 );
		$this->job_repo->enqueue( $job );

		$summary = $worker->run_step( 'worker_1', 1000 );

		self::assertNotNull( $summary );
		self::assertSame( JobResult::RETRY, $summary->result->status );

		$retrying = $this->job_repo->find_by_key( $job->job_key );
		self::assertNotNull( $retrying );
		self::assertSame( JobState::WAITING_RETRY, $retrying->state );
		self::assertGreaterThan( 1000, $retrying->available_at );
		self::assertSame( 'timeout', $retrying->last_error_code );

		$attempts = $this->job_repo->attempts_for_job( $job->job_key );
		self::assertCount( 1, $attempts );
		self::assertSame( 1, $attempts[0]->attempt_no );
	}

	public function test_exhausted_retries_transition_to_dead_letter(): void {
		$failing_handler = new CallbackJobHandler(
			JobStage::RENDER_IMAGE,
			static function ( Job $job, JobExecutionContext $ctx ): JobResult {
				unset( $job, $ctx );

				return JobResult::retry( 'err', 'Persistent error' );
			}
		);
		// Max attempts = 2.
		$worker = new QueueWorker(
			$this->job_repo,
			$this->control_store,
			$this->batch_repo,
			new RetryPolicy( 2, 10, 60 ),
			array( $failing_handler )
		);

		$job = $this->make_job( JobStage::RENDER_IMAGE, 'atal_institute', 'art_dead', array(), 1000 );
		$this->job_repo->enqueue( $job );

		// Attempt 1 -> waiting retry.
		$worker->run_step( 'worker_1', 1000 );
		$job_after_1 = $this->job_repo->find_by_key( $job->job_key );
		self::assertNotNull( $job_after_1 );
		self::assertSame( JobState::WAITING_RETRY, $job_after_1->state );

		// Attempt 2 at available timestamp -> exhausted -> dead-letter.
		$next_time = $job_after_1->available_at;
		$summary   = $worker->run_step( 'worker_1', $next_time );

		self::assertNotNull( $summary );
		self::assertSame( 'dead', $summary->status );

		$dead = $this->job_repo->find_by_key( $job->job_key );
		self::assertNotNull( $dead );
		self::assertSame( JobState::DEAD, $dead->state );
		self::assertSame( 'err', $dead->last_error_code );

		$attempts = $this->job_repo->attempts_for_job( $job->job_key );
		self::assertCount( 2, $attempts );
	}

	public function test_failure_isolation_does_not_block_other_jobs(): void {
		$failing_handler = new CallbackJobHandler(
			JobStage::RENDER_IMAGE,
			static function ( Job $job, JobExecutionContext $ctx ): JobResult {
				unset( $ctx );

				if ( 'bad_article' === $job->item_key ) {
					throw new RuntimeException( 'Fatal parse exception for bad article' );
				}

				return JobResult::success();
			}
		);
		$worker          = new QueueWorker(
			$this->job_repo,
			$this->control_store,
			$this->batch_repo,
			new RetryPolicy(),
			array( $failing_handler )
		);

		$bad_job  = $this->make_job( JobStage::RENDER_IMAGE, 'atal_institute', 'bad_article', array(), 1000 );
		$good_job = $this->make_job( JobStage::RENDER_IMAGE, 'atal_institute', 'good_article', array(), 1000 );
		$this->job_repo->enqueue( $bad_job );
		$this->job_repo->enqueue( $good_job );

		// Run bad job: fails and isolates.
		$summary1 = $worker->run_step( 'worker_1', 1000 );
		self::assertNotNull( $summary1 );
		self::assertSame( $bad_job->job_key, $summary1->job->job_key );
		self::assertSame( JobResult::RETRY, $summary1->result->status );

		// Run good job: processes smoothly without interruption.
		$summary2 = $worker->run_step( 'worker_1', 1000 );
		self::assertNotNull( $summary2 );
		self::assertSame( $good_job->job_key, $summary2->job->job_key );
		self::assertSame( JobResult::SUCCESS, $summary2->result->status );
	}

	public function test_pause_mode_prevents_job_execution(): void {
		$worker = $this->create_worker();
		$job    = $this->make_job( JobStage::RENDER_IMAGE, 'atal_institute', 'art_paused', array(), 1000 );
		$this->job_repo->enqueue( $job );

		$this->control_store->set_state( QueueControlState::PAUSED, 'Maintenance window' );

		$summary = $worker->run_step( 'worker_1', 1000 );
		self::assertNull( $summary );

		$unclaimed = $this->job_repo->find_by_key( $job->job_key );
		self::assertNotNull( $unclaimed );
		self::assertSame( JobState::PENDING, $unclaimed->state );
		self::assertSame( 0, $unclaimed->attempts );
	}

	public function test_emergency_stop_halts_queue_immediately(): void {
		$worker = $this->create_worker();
		$job    = $this->make_job( JobStage::RENDER_IMAGE, 'atal_institute', 'art_emstop', array(), 1000 );
		$this->job_repo->enqueue( $job );

		$this->control_store->set_state( QueueControlState::EMERGENCY_STOP, 'Immediate audit stop' );

		$summary = $worker->run_step( 'worker_1', 1000 );
		self::assertNull( $summary );

		$unclaimed = $this->job_repo->find_by_key( $job->job_key );
		self::assertNotNull( $unclaimed );
		self::assertSame( JobState::PENDING, $unclaimed->state );
	}

	public function test_daily_publishing_quota_defers_publishing_without_failing(): void {
		$worker = $this->create_worker();

		// Set publishing daily limit to 2.
		$config = new QueueConfig( 25, 25, 50, 2 );
		$this->control_store->set_config( $config );

		// Simulate 2 already published jobs today.
		$pub1 = $this->make_job( JobStage::PUBLISH, 'atal_institute', 'art_p1', array(), 500 )
			->with(
				array(
					'state'        => JobState::SUCCEEDED,
					'completed_at' => 600,
				)
			);
		$pub2 = $this->make_job( JobStage::PUBLISH, 'atal_institute', 'art_p2', array(), 700 )
			->with(
				array(
					'state'        => JobState::SUCCEEDED,
					'completed_at' => 800,
				)
			);
		$this->job_repo->enqueue( $pub1 );
		$this->job_repo->enqueue( $pub2 );

		// 3rd publish job exceeds the daily limit.
		$pub3 = $this->make_job(
			JobStage::PUBLISH,
			'atal_institute',
			'art_p3',
			array( 'target_domain' => 'liveup2.atalinstitute.com' ),
			1000
		);
		$this->job_repo->enqueue( $pub3 );

		$summary = $worker->run_step( 'worker_1', 1000 );

		self::assertNotNull( $summary );
		self::assertSame( JobResult::DEFER, $summary->result->status );

		$deferred = $this->job_repo->find_by_key( $pub3->job_key );
		self::assertNotNull( $deferred );
		self::assertSame( JobState::WAITING_RETRY, $deferred->state );
		self::assertSame( 'publishing_cap_reached', $deferred->last_error_code );
		self::assertGreaterThan( 1000, $deferred->available_at );
	}

	public function test_compensating_rollback_job_executes_successfully(): void {
		$worker   = $this->create_worker();
		$rollback = $this->make_job(
			JobStage::ROLLBACK,
			'atal_institute',
			'art_rollback',
			array( 'original_job_key' => 'job_123' ),
			1000
		);
		$this->job_repo->enqueue( $rollback );

		$summary = $worker->run_step( 'worker_1', 1000 );

		self::assertNotNull( $summary );
		self::assertSame( JobResult::SUCCESS, $summary->result->status );

		$completed = $this->job_repo->find_by_key( $rollback->job_key );
		self::assertNotNull( $completed );
		self::assertSame( JobState::SUCCEEDED, $completed->state );
	}
}
