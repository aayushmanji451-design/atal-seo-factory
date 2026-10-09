<?php
/**
 * Daily batch aggregation entity for automated daily runs.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

use Atal\Queue\Support\Arr;

/**
 * Immutable aggregation of one day's planned publications across Institute and Diploma.
 */
final class DailyBatch {

	public const STATUS_PREPARING         = 'preparing';
	public const STATUS_AWAITING_APPROVAL = 'awaiting_approval';
	public const STATUS_APPROVED          = 'approved';
	public const STATUS_EXECUTING         = 'executing';
	public const STATUS_COMPLETED         = 'completed';
	public const STATUS_SKIPPED           = 'skipped';

	/**
	 * Create a daily batch.
	 *
	 * @param string                      $batch_key         Batch identity.
	 * @param string                      $date_ymd          Date in YYYY-MM-DD.
	 * @param string                      $mode              Mode: daily_approval or full_auto.
	 * @param string                      $status            Batch status.
	 * @param int                         $institute_limit   Planned limit for Institute.
	 * @param int                         $diploma_limit     Planned limit for Diploma.
	 * @param int                         $generation_limit  Daily generation cap.
	 * @param int                         $publishing_limit  Daily publishing cap.
	 * @param int                         $created_at        Creation timestamp.
	 * @param int|null                    $approved_at       Approval timestamp.
	 * @param int|null                    $skipped_at        Skip timestamp.
	 * @param array<string,DailyBatchItem> $items             Items indexed by item_key.
	 */
	public function __construct(
		public readonly string $batch_key,
		public readonly string $date_ymd,
		public readonly string $mode,
		public readonly string $status,
		public readonly int $institute_limit,
		public readonly int $diploma_limit,
		public readonly int $generation_limit,
		public readonly int $publishing_limit,
		public readonly int $created_at,
		public readonly ?int $approved_at = null,
		public readonly ?int $skipped_at = null,
		public readonly array $items = array()
	) {
	}

	/**
	 * Compute batch key from date.
	 *
	 * @param string $date_ymd Date string.
	 */
	public static function compute_key( string $date_ymd ): string {
		return 'batch_' . preg_replace( '/[^0-9]/', '', $date_ymd );
	}

	/**
	 * Total items in the batch.
	 */
	public function total_count(): int {
		return count( $this->items );
	}

	/**
	 * Total valid items that passed quality gates.
	 */
	public function valid_count(): int {
		$count = 0;
		foreach ( $this->items as $item ) {
			if ( $item->is_valid() ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Total blocked items that failed one or more quality gates.
	 */
	public function blocked_count(): int {
		$count = 0;
		foreach ( $this->items as $item ) {
			if ( ! $item->is_valid() ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Total published items.
	 */
	public function published_count(): int {
		$count = 0;
		foreach ( $this->items as $item ) {
			if ( DailyBatchItem::STATUS_PUBLISHED === $item->status ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Total failed items during queue execution.
	 */
	public function failed_count(): int {
		$count = 0;
		foreach ( $this->items as $item ) {
			if ( DailyBatchItem::STATUS_FAILED === $item->status ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Items belonging to a specific site.
	 *
	 * @param string $site Target site.
	 *
	 * @return list<DailyBatchItem>
	 */
	public function items_for_site( string $site ): array {
		$result = array();
		foreach ( $this->items as $item ) {
			if ( $item->target_site === $site ) {
				$result[] = $item;
			}
		}

		return $result;
	}

	/**
	 * Only valid items.
	 *
	 * @return list<DailyBatchItem>
	 */
	public function valid_items(): array {
		$result = array();
		foreach ( $this->items as $item ) {
			if ( $item->is_valid() ) {
				$result[] = $item;
			}
		}

		return $result;
	}

	/**
	 * Return summary metrics for owner dashboard.
	 *
	 * @return array{batch_key:string,date_ymd:string,mode:string,status:string,total:int,valid:int,blocked:int,published:int,failed:int,institute_target:int,diploma_target:int,approved_at:int|null}
	 */
	public function summary(): array {
		return array(
			'batch_key'        => $this->batch_key,
			'date_ymd'         => $this->date_ymd,
			'mode'             => $this->mode,
			'status'           => $this->status,
			'total'            => $this->total_count(),
			'valid'            => $this->valid_count(),
			'blocked'          => $this->blocked_count(),
			'published'        => $this->published_count(),
			'failed'           => $this->failed_count(),
			'institute_target' => $this->institute_limit,
			'diploma_target'   => $this->diploma_limit,
			'approved_at'      => $this->approved_at,
		);
	}

	/**
	 * Replace or add one item in the batch.
	 *
	 * @param DailyBatchItem $item Updated item.
	 */
	public function with_item( DailyBatchItem $item ): self {
		$items                    = $this->items;
		$items[ $item->item_key ] = $item;

		return $this->with( array( 'items' => $items ) );
	}

	/**
	 * Hydrate from array.
	 *
	 * @param array<mixed,mixed> $row Raw row.
	 */
	public static function from_array( array $row ): self {
		$items_raw = $row['items'] ?? array();
		$items     = array();
		if ( is_array( $items_raw ) ) {
			foreach ( $items_raw as $key => $item_row ) {
				if ( $item_row instanceof DailyBatchItem ) {
					$items[ $item_row->item_key ] = $item_row;
				} elseif ( is_array( $item_row ) ) {
					$item                     = DailyBatchItem::from_array( $item_row );
					$items[ $item->item_key ] = $item;
				}
			}
		}

		$approved_raw = $row['approved_at'] ?? null;
		$skipped_raw  = $row['skipped_at'] ?? null;

		return new self(
			Arr::string( $row, 'batch_key' ),
			Arr::string( $row, 'date_ymd' ),
			Arr::string( $row, 'mode', QueueConfig::MODE_DAILY_APPROVAL ),
			Arr::string( $row, 'status', self::STATUS_PREPARING ),
			Arr::int( $row, 'institute_limit', QueueConfig::DEFAULT_INSTITUTE_DAILY_LIMIT ),
			Arr::int( $row, 'diploma_limit', QueueConfig::DEFAULT_DIPLOMA_DAILY_LIMIT ),
			Arr::int( $row, 'generation_limit', QueueConfig::DEFAULT_GENERATION_LIMIT ),
			Arr::int( $row, 'publishing_limit', QueueConfig::DEFAULT_PUBLISHING_LIMIT ),
			Arr::int( $row, 'created_at', (int) gmdate( 'U' ) ),
			is_numeric( $approved_raw ) ? (int) $approved_raw : null,
			is_numeric( $skipped_raw ) ? (int) $skipped_raw : null,
			$items
		);
	}

	/**
	 * Export to array.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		$items = array();
		foreach ( $this->items as $key => $item ) {
			$items[ $key ] = $item->to_array();
		}

		return array(
			'batch_key'        => $this->batch_key,
			'date_ymd'         => $this->date_ymd,
			'mode'             => $this->mode,
			'status'           => $this->status,
			'institute_limit'  => $this->institute_limit,
			'diploma_limit'    => $this->diploma_limit,
			'generation_limit' => $this->generation_limit,
			'publishing_limit' => $this->publishing_limit,
			'created_at'       => $this->created_at,
			'approved_at'      => $this->approved_at,
			'skipped_at'       => $this->skipped_at,
			'items'            => $items,
		);
	}

	/**
	 * Return a modified copy.
	 *
	 * @param array<string,mixed> $changes Overrides.
	 */
	public function with( array $changes ): self {
		$merged = array_merge( $this->to_array(), $changes );

		return self::from_array( $merged );
	}
}
