<?php
/**
 * Task07Panel admin unit and security tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Queue;

use Atal\Queue\Domain\DailyBatch;
use Atal\Queue\Domain\QueueConfig;
use Atal\Queue\Domain\QueueControlState;
use Atal\SeoFactory\Admin\Task07Panel;
use AtalWordPressStubState;
use RuntimeException;

/**
 * Tests capability enforcement, nonces, admin actions, and UI transitions.
 */
final class Task07PanelTest extends QueueTestCase {

	protected function tearDown(): void {
		AtalWordPressStubState::$current_user_can = true;
		AtalWordPressStubState::$nonce_valid      = true;
		$_POST                                    = array();
	}

	public function test_save_config_rejects_unauthorized_user(): void {
		AtalWordPressStubState::$current_user_can = false;
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'permission' );

		$this->panel()->save_config();
	}

	public function test_save_config_rejects_invalid_nonce(): void {
		AtalWordPressStubState::$nonce_valid = false;
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'could not be verified' );

		$this->panel()->save_config();
	}

	public function test_save_config_updates_queue_configuration(): void {
		$_POST = array(
			'mode'             => QueueConfig::MODE_FULL_AUTO,
			'institute_limit'  => '20',
			'diploma_limit'    => '20',
			'generation_limit' => '40',
			'publishing_limit' => '40',
			'stagger_interval' => '1200',
		);

		$this->panel()->save_config();

		$config = $this->control_store->get_config();
		self::assertSame( QueueConfig::MODE_FULL_AUTO, $config->mode );
		self::assertSame( 20, $config->institute_daily_limit );
		self::assertSame( 20, $config->diploma_daily_limit );
		self::assertSame( 40, $config->generation_daily_limit );
		self::assertSame( 40, $config->publishing_daily_limit );
		self::assertSame( 1200, $config->stagger_interval_seconds );
	}

	public function test_pause_resume_emergency_stop_transitions(): void {
		$panel = $this->panel();

		// Pause.
		$panel->pause();
		self::assertSame( QueueControlState::PAUSED, $this->control_store->get_state() );

		// Resume.
		$panel->resume();
		self::assertSame( QueueControlState::RUNNING, $this->control_store->get_state() );

		// Emergency Stop.
		$panel->emergency_stop();
		self::assertSame( QueueControlState::EMERGENCY_STOP, $this->control_store->get_state() );

		// Reset emergency.
		$panel->reset_emergency();
		self::assertSame( QueueControlState::PAUSED, $this->control_store->get_state() );
	}

	public function test_prepare_and_approve_batch_via_panel(): void {
		$panel = $this->panel();

		// Prepare today's batch.
		$panel->prepare_batch();
		$today     = gmdate( 'Y-m-d' );
		$batch_key = DailyBatch::compute_key( $today );
		$batch     = $this->batch_repo->find( $batch_key );
		self::assertNotNull( $batch );
		self::assertSame( DailyBatch::STATUS_AWAITING_APPROVAL, $batch->status );

		// Approve batch.
		$_POST = array( 'batch_key' => $batch_key );
		$panel->approve_batch();

		$approved = $this->batch_repo->find( $batch_key );
		self::assertNotNull( $approved );
		self::assertSame( DailyBatch::STATUS_EXECUTING, $approved->status );

		// Valid items enqueued.
		$jobs = $this->job_repo->jobs_for_batch( $batch_key );
		self::assertNotEmpty( $jobs );
	}

	public function test_skip_batch_via_panel(): void {
		$panel = $this->panel();
		$panel->prepare_batch();
		$batch_key = DailyBatch::compute_key( gmdate( 'Y-m-d' ) );

		$_POST = array( 'batch_key' => $batch_key );
		$panel->skip_batch();

		$skipped = $this->batch_repo->find( $batch_key );
		self::assertNotNull( $skipped );
		self::assertSame( DailyBatch::STATUS_SKIPPED, $skipped->status );
	}

	public function test_run_worker_step_via_panel(): void {
		$panel = $this->panel();
		$job   = $this->make_job();
		$this->job_repo->enqueue( $job );

		$panel->run_worker_step();

		$processed = $this->job_repo->find_by_key( $job->job_key );
		self::assertNotNull( $processed );
		self::assertSame( 1, $processed->attempts );
	}

	private function panel(): Task07Panel {
		return new Task07Panel(
			$this->create_coordinator(),
			$this->create_worker(),
			$this->control_store,
			$this->batch_repo,
			$this->job_repo
		);
	}
}
