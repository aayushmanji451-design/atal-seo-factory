<?php
/**
 * DailyBatchCoordinator unit tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Queue;

use Atal\Queue\Domain\DailyBatch;
use Atal\Queue\Domain\DailyBatchItem;
use Atal\Queue\Domain\JobStage;
use Atal\Queue\Domain\QueueConfig;
use Atal\Topics\Domain\QualityState;

/**
 * Tests 25+25 daily automation, Daily Approval mode, Full Auto mode, quality gates, and staggering.
 */
final class DailyBatchCoordinatorTest extends QueueTestCase {

	public function test_prepare_daily_batch_generates_default_25_plus_25_candidates(): void {
		$coordinator = $this->create_coordinator();
		$batch       = $coordinator->prepare_daily_batch( '2026-10-09' );

		self::assertSame( '2026-10-09', $batch->date_ymd );
		self::assertSame( 25, $batch->institute_limit );
		self::assertSame( 25, $batch->diploma_limit );
		self::assertSame( 50, $batch->total_count() );

		// In default daily approval mode, batch waits for single owner approval.
		self::assertSame( DailyBatch::STATUS_AWAITING_APPROVAL, $batch->status );
		self::assertNull( $batch->approved_at );

		// All valid items passed canonical quality gates.
		self::assertGreaterThanOrEqual( 40, $batch->valid_count() );

		// Jobs are NOT enqueued into active queue before owner approves.
		self::assertEmpty( $this->job_repo->all_jobs() );
	}

	public function test_configurable_limits_are_respected(): void {
		$coordinator = $this->create_coordinator();
		$config      = new QueueConfig( 5, 5, 20, 20 );

		$batch = $coordinator->prepare_daily_batch( '2026-10-09', $config );

		self::assertSame( 5, $batch->institute_limit );
		self::assertSame( 5, $batch->diploma_limit );
		self::assertSame( 10, $batch->total_count() );
	}

	public function test_daily_approval_mode_requires_single_owner_approval_to_release_staggered_queue(): void {
		$coordinator = $this->create_coordinator();
		$batch       = $coordinator->prepare_daily_batch( '2026-10-09', null, 1000 );

		self::assertSame( DailyBatch::STATUS_AWAITING_APPROVAL, $batch->status );
		self::assertEmpty( $this->job_repo->all_jobs() );

		// Summary inspection.
		$summary = $batch->summary();
		self::assertSame( 50, $summary['total'] );
		self::assertGreaterThan( 0, $summary['valid'] );

		// Owner gives ONE single approval for the day.
		$approved = $coordinator->approve_batch( $batch->batch_key, 2000 );

		self::assertSame( DailyBatch::STATUS_EXECUTING, $approved->status );
		self::assertSame( 2000, $approved->approved_at );

		// All valid items are now enqueued with staggered intervals (default 900s).
		$enqueued_jobs = $this->job_repo->jobs_for_batch( $batch->batch_key );
		self::assertSame( $approved->valid_count(), count( $enqueued_jobs ) );

		// Verify first stage in the execution pipeline is RENDER_IMAGE and schedules are staggered.
		$previous_available = 2000;
		foreach ( $enqueued_jobs as $i => $job ) {
			self::assertSame( JobStage::RENDER_IMAGE, $job->stage );
			if ( 0 === $i ) {
				self::assertSame( 2000, $job->available_at );
			} else {
				self::assertGreaterThanOrEqual( $previous_available, $job->available_at );
				$previous_available = $job->available_at;
			}
		}
	}

	public function test_skip_batch_marks_batch_skipped_and_cancels_jobs(): void {
		$coordinator = $this->create_coordinator();
		$batch       = $coordinator->prepare_daily_batch( '2026-10-09', null, 1000 );

		$skipped = $coordinator->skip_batch( $batch->batch_key, 'Owner holiday override', 1500 );

		self::assertSame( DailyBatch::STATUS_SKIPPED, $skipped->status );
		self::assertEmpty( $this->job_repo->all_jobs() );
	}

	public function test_full_auto_mode_automatically_queues_without_manual_approval(): void {
		$coordinator = $this->create_coordinator();
		$config      = new QueueConfig( 10, 10, 30, 30, QueueConfig::MODE_FULL_AUTO, 600 );
		$this->control_store->set_config( $config );

		$batch = $coordinator->prepare_daily_batch( '2026-10-09', null, 5000 );

		// In Full Auto mode, batch is automatically approved and transitions to executing.
		self::assertSame( DailyBatch::STATUS_EXECUTING, $batch->status );
		self::assertSame( 5000, $batch->approved_at );

		// Valid items are immediately enqueued with staggered schedule.
		$jobs = $this->job_repo->jobs_for_batch( $batch->batch_key );
		self::assertSame( $batch->valid_count(), count( $jobs ) );
	}

	public function test_isolated_blocked_items_do_not_prevent_valid_items_from_queueing(): void {
		$coordinator = $this->create_coordinator();
		$batch       = $coordinator->prepare_daily_batch( '2026-10-09', null, 1000 );

		// Artificially inject one failed/blocked item into the batch before approval.
		$blocked_item       = new DailyBatchItem(
			'blocked_item_key',
			$batch->batch_key,
			'atal_institute',
			'institute_general_duty_assistant',
			'course_overview',
			'Blocked Test Post',
			2026,
			'Blocked Title',
			'blocked-slug',
			QualityState::REJECTED,
			array(
				array(
					'rule_id' => 'topic.claims.blocked',
					'status'  => 'rejected',
				),
			),
			DailyBatchItem::STATUS_BLOCKED
		);
		$batch_with_blocked = $batch->with_item( $blocked_item );
		$this->batch_repo->save( $batch_with_blocked );

		// Owner approves.
		$approved = $coordinator->approve_batch( $batch->batch_key, 2000 );

		// Enqueued count must strictly equal valid count, excluding the blocked item.
		$jobs = $this->job_repo->jobs_for_batch( $batch->batch_key );
		self::assertSame( $approved->valid_count(), count( $jobs ) );

		// Blocked item key is NOT in enqueued jobs.
		foreach ( $jobs as $job ) {
			self::assertNotSame( 'blocked_item_key', $job->item_key );
		}
	}
}
