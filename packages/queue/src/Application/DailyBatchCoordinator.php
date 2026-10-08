<?php
/**
 * Daily batch coordinator managing 25+25 daily automation and approval workflows.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Application;

use Atal\Queue\Contract\BatchRepositoryInterface;
use Atal\Queue\Contract\JobRepositoryInterface;
use Atal\Queue\Contract\QueueControlStoreInterface;
use Atal\Queue\Domain\DailyBatch;
use Atal\Queue\Domain\DailyBatchItem;
use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobStage;
use Atal\Queue\Domain\QueueConfig;
use Atal\Queue\Domain\QueueControlState;
use Atal\Topics\Application\CanonicalTopicPolicy;
use Atal\Topics\Application\DeterministicRotation;
use Atal\Topics\Application\TopicValidator;
use Atal\Topics\Domain\QualityState;
use Atal\Topics\Domain\RotationCandidate;
use Atal\Topics\Domain\TopicIdentity;
use Atal\Topics\Domain\TopicProposal;
use InvalidArgumentException;
use RuntimeException;

/**
 * Automates daily batch preparation, validation, single approval, and staggering.
 */
final class DailyBatchCoordinator {

	/**
	 * Create coordinator.
	 *
	 * @param CanonicalTopicPolicy        $policy    Canonical topic policy.
	 * @param DeterministicRotation       $rotation  Sequential weighted rotation.
	 * @param TopicValidator              $validator Quality gate validator.
	 * @param JobRepositoryInterface      $jobs      Job repository.
	 * @param BatchRepositoryInterface    $batches   Batch repository.
	 * @param QueueControlStoreInterface  $controls  Queue configuration and control switches.
	 */
	public function __construct(
		private readonly CanonicalTopicPolicy $policy,
		private readonly DeterministicRotation $rotation,
		private readonly TopicValidator $validator,
		private readonly JobRepositoryInterface $jobs,
		private readonly BatchRepositoryInterface $batches,
		private readonly QueueControlStoreInterface $controls
	) {
	}

	/**
	 * Prepare the daily batch according to configured limits and mode.
	 *
	 * @param string           $date_ymd        Target publishing date in YYYY-MM-DD.
	 * @param QueueConfig|null $config_override Optional configuration override.
	 * @param int              $now             Current timestamp (0 for gmdate('U')).
	 */
	public function prepare_daily_batch( string $date_ymd, ?QueueConfig $config_override = null, int $now = 0 ): DailyBatch {
		$time   = 0 === $now ? (int) gmdate( 'U' ) : $now;
		$config = $config_override ?? $this->controls->get_config();

		$batch_key = DailyBatch::compute_key( $date_ymd );
		$existing  = $this->batches->find( $batch_key );
		if ( null !== $existing && in_array( $existing->status, array( DailyBatch::STATUS_APPROVED, DailyBatch::STATUS_EXECUTING, DailyBatch::STATUS_COMPLETED ), true ) ) {
			return $existing;
		}

		$items = array();

		// Generate candidate items for ATAL Institute.
		$institute_items = $this->prepare_items_for_site( 'atal_institute', $batch_key, $config->institute_daily_limit, $date_ymd );
		foreach ( $institute_items as $item ) {
			$items[ $item->item_key ] = $item;
		}

		// Generate candidate items for Atal Diploma.
		$diploma_items = $this->prepare_items_for_site( 'atal_diploma', $batch_key, $config->diploma_daily_limit, $date_ymd );
		foreach ( $diploma_items as $item ) {
			$items[ $item->item_key ] = $item;
		}

		$initial_status = $config->requires_daily_approval()
			? DailyBatch::STATUS_AWAITING_APPROVAL
			: DailyBatch::STATUS_APPROVED;

		$batch = new DailyBatch(
			$batch_key,
			$date_ymd,
			$config->mode,
			$initial_status,
			$config->institute_daily_limit,
			$config->diploma_daily_limit,
			$config->generation_daily_limit,
			$config->publishing_daily_limit,
			$time,
			QueueConfig::MODE_FULL_AUTO === $config->mode ? $time : null,
			null,
			$items
		);

		$this->batches->save( $batch );

		// If in FULL AUTO mode, queue valid items immediately without waiting for owner approval.
		if ( QueueConfig::MODE_FULL_AUTO === $config->mode ) {
			$batch = $this->enqueue_valid_items( $batch, $config, $time );
		}

		return $batch;
	}

	/**
	 * Owner gives ONE approval for the entire daily batch (Daily Approval Mode).
	 *
	 * Valid posts are staggered through the day.
	 * Blocked/failed posts do not stop or delay the rest of the batch.
	 *
	 * @param string $batch_key Batch identity.
	 * @param int    $now       Approval epoch.
	 *
	 * @throws RuntimeException When batch is not found or not in awaiting state.
	 */
	public function approve_batch( string $batch_key, int $now = 0 ): DailyBatch {
		$time  = 0 === $now ? (int) gmdate( 'U' ) : $now;
		$batch = $this->batches->find( $batch_key );

		if ( null === $batch ) {
			throw new RuntimeException( 'Daily batch not found: ' . $batch_key );
		}
		if ( DailyBatch::STATUS_AWAITING_APPROVAL !== $batch->status ) {
			throw new RuntimeException( 'Batch is not awaiting approval; current status is: ' . $batch->status );
		}

		$config   = $this->controls->get_config();
		$approved = $batch->with(
			array(
				'status'      => DailyBatch::STATUS_APPROVED,
				'approved_at' => $time,
			)
		);

		return $this->enqueue_valid_items( $approved, $config, $time );
	}

	/**
	 * Skip today's batch.
	 *
	 * @param string $batch_key Batch identity.
	 * @param string $reason    Reason for skipping.
	 * @param int    $now       Timestamp.
	 */
	public function skip_batch( string $batch_key, string $reason = 'Skipped by owner', int $now = 0 ): DailyBatch {
		$time  = 0 === $now ? (int) gmdate( 'U' ) : $now;
		$batch = $this->batches->find( $batch_key );

		if ( null === $batch ) {
			throw new RuntimeException( 'Daily batch not found: ' . $batch_key );
		}

		$skipped = $batch->with(
			array(
				'status'     => DailyBatch::STATUS_SKIPPED,
				'skipped_at' => $time,
			)
		);

		// Cancel any jobs already enqueued for this batch.
		foreach ( $this->jobs->jobs_for_batch( $batch_key ) as $job ) {
			$this->jobs->cancel( $job->job_key, $reason, $time );
		}

		$this->batches->save( $skipped );

		return $skipped;
	}

	/**
	 * Enqueue all valid items with staggered schedule.
	 *
	 * @param DailyBatch  $batch  Approved batch.
	 * @param QueueConfig $config Queue configuration.
	 * @param int         $now    Current timestamp.
	 */
	private function enqueue_valid_items( DailyBatch $batch, QueueConfig $config, int $now ): DailyBatch {
		$updated_items = $batch->items;
		$stagger_index = 0;

		foreach ( $batch->items as $item_key => $item ) {
			if ( ! $item->is_valid() ) {
				// Blocked/failed items are isolated and never enqueued; they do NOT stop valid items.
				continue;
			}

			// Stagger start time through the day based on configured interval.
			$scheduled_at = $now + ( $stagger_index * $config->stagger_interval_seconds );
			++$stagger_index;

			$job = Job::create(
				$item->item_key,
				$batch->batch_key,
				JobStage::RENDER_IMAGE,
				$item->target_site,
				$scheduled_at,
				$config->max_job_attempts,
				$now,
				0,
				array( 'proposal' => $item->proposal_data )
			);

			$this->jobs->enqueue( $job );

			$updated_items[ $item_key ] = $item->with(
				array(
					'status'        => DailyBatchItem::STATUS_QUEUED,
					'current_stage' => JobStage::RENDER_IMAGE,
				)
			);
		}

		$executing_batch = $batch->with(
			array(
				'status' => DailyBatch::STATUS_EXECUTING,
				'items'  => $updated_items,
			)
		);

		$this->batches->save( $executing_batch );

		return $executing_batch;
	}

	/**
	 * Prepare and validate candidates for one target site up to limit.
	 *
	 * @param string $site      Canonical target site.
	 * @param string $batch_key Batch key.
	 * @param int    $limit     Target count.
	 * @param string $date_ymd  Date string.
	 *
	 * @return list<DailyBatchItem>
	 */
	private function prepare_items_for_site( string $site, string $batch_key, int $limit, string $date_ymd ): array {
		$courses    = $this->policy->courses_for_site( $site );
		$intent     = 'course_overview';
		$candidates = array();

		foreach ( $courses as $course ) {
			$key = $course['course_key'] ?? null;
			if ( is_string( $key ) ) {
				$candidates[] = new RotationCandidate( $site, $key, 1, $this->policy->blocked_intent_reason( $key, $intent ) );
			}
		}

		if ( array() === $candidates ) {
			return array();
		}

		$items      = array();
		$date_year  = (int) substr( $date_ymd, 0, 4 );
		$year       = ( $date_year >= 2025 && $date_year <= 2035 ) ? $date_year : 2026;
		$cand_count = count( $candidates );

		for ( $i = 0; $i < $limit; ++$i ) {
			try {
				$selection  = $this->rotation->next( $site, $candidates );
				$course_key = $selection->course_key();
			} catch ( RuntimeException $e ) {
				break;
			}

			$course = $this->policy->course( $course_key );
			if ( null === $course ) {
				continue;
			}

			$canonical_name = is_string( $course['canonical_name'] ?? null ) ? $course['canonical_name'] : $course_key;
			$cycle          = (int) floor( $i / $cand_count );

			if ( 0 === $cycle ) {
				$primary_kw = 'atal_diploma' === $site ? $canonical_name : $canonical_name . ' course';
				$slug       = strtolower( trim( (string) preg_replace( '/[^a-z0-9]+/i', '-', $canonical_name ), '-' ) );
				$title      = $canonical_name . ( 'atal_diploma' === $site ? '' : ' Course' ) . ': Duration and Fees | ' . ( 'atal_institute' === $site ? 'ATAL Institute' : 'Atal Diploma' );
			} else {
				$kw_suffix  = 'atal_diploma' === $site
					? ( 1 === $cycle ? ' program' : ' training' )
					: ( 1 === $cycle ? ' training' : ' diploma' );
				$primary_kw = $canonical_name . $kw_suffix;
				$slug       = strtolower( trim( (string) preg_replace( '/[^a-z0-9]+/i', '-', $canonical_name ), '-' ) ) . '-' . trim( $kw_suffix );
				$title      = $canonical_name . ' ' . ucwords( trim( $kw_suffix ) ) . ': Duration and Fees | ' . ( 'atal_institute' === $site ? 'ATAL Institute' : 'Atal Diploma' );
			}

			$expected_link = $this->policy->expected_internal_link( $course_key, $site ) ?? '';

			$duration_data = $course['duration'] ?? null;
			$duration      = is_array( $duration_data ) && is_string( $duration_data['display'] ?? null ) ? $duration_data['display'] : '';

			$fee_data = $course['fee'] ?? null;
			$fee      = is_array( $fee_data ) && is_string( $fee_data['display'] ?? null ) ? $fee_data['display'] : '';

			$facts = array();
			if ( '' !== $duration ) {
				$facts['duration'] = $duration;
			}
			if ( '' !== $fee ) {
				$facts['fee'] = $fee;
			}

			$proposal = new TopicProposal(
				new TopicIdentity( $site, $course_key, $intent, $primary_kw, $year ),
				$title,
				$slug,
				array( 'Overview of ' . $primary_kw, 'Course Details', 'Conclusion' ),
				array(
					'Explore the verified details of ' . $primary_kw . ' offering recognized curriculum and structured learning.',
					'This program includes practical training modules and comprehensive assessment designed for modern healthcare career advancement.',
					'In conclusion, apply online through the official portal to secure your enrollment in the upcoming session.',
				),
				array( 'What is the duration?', 'What are the admission requirements?' ),
				array( $expected_link ),
				$facts
			);

			$report   = $this->validator->validate( $proposal );
			$is_pass  = QualityState::PASS === $report->state();
			$findings = array_map( static fn ( $f ) => $f->to_array(), $report->findings() );

			$item_key = DailyBatchItem::compute_key( $site, $course_key, $intent, $primary_kw, $year );

			$items[] = new DailyBatchItem(
				$item_key,
				$batch_key,
				$site,
				$course_key,
				$intent,
				$primary_kw,
				$year,
				$proposal->title(),
				$proposal->slug(),
				$report->state(),
				$findings,
				$is_pass ? DailyBatchItem::STATUS_PASSED : DailyBatchItem::STATUS_BLOCKED,
				JobStage::VALIDATE,
				null,
				'',
				$is_pass ? '' : 'Failed quality gates: ' . $report->state(),
				$proposal->to_array()
			);
		}

		return $items;
	}

	/**
	 * Get high-level summary for owner dashboard.
	 *
	 * @param string|null $batch_key Specific batch or latest.
	 *
	 * @return array<string,mixed>
	 */
	public function get_summary( ?string $batch_key = null ): array {
		$batch  = null !== $batch_key ? $this->batches->find( $batch_key ) : $this->batches->find_latest();
		$config = $this->controls->get_config();
		$state  = $this->controls->get_state();

		$summary = array(
			'queue_state' => $state,
			'config'      => $config->to_array(),
		);

		if ( null !== $batch ) {
			$summary['batch'] = $batch->summary();
		}

		return $summary;
	}
}
