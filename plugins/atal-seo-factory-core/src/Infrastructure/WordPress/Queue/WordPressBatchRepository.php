<?php
/**
 * WordPress option-backed daily batch repository.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress\Queue;

use Atal\Queue\Contract\BatchRepositoryInterface;
use Atal\Queue\Domain\DailyBatch;
use Atal\SeoFactory\Config\Identifiers;

/**
 * Persists daily batches in WordPress options.
 */
final class WordPressBatchRepository implements BatchRepositoryInterface {

	public function save( DailyBatch $batch ): void {
		$batches                      = $this->load_raw_map();
		$batches[ $batch->batch_key ] = $batch->to_array();
		update_option( Identifiers::OPTION_DAILY_BATCH, $batches, false );
	}

	public function find( string $batch_key ): ?DailyBatch {
		$batches = $this->load_raw_map();
		$raw     = $batches[ $batch_key ] ?? null;
		if ( null === $raw ) {
			return null;
		}

		return DailyBatch::from_array( $raw );
	}

	public function find_latest(): ?DailyBatch {
		$batches = $this->load_raw_map();
		if ( array() === $batches ) {
			return null;
		}

		$latest_key = array_key_first( $batches );
		$latest_raw = $batches[ $latest_key ];
		foreach ( $batches as $key => $raw ) {
			if ( strcmp( $key, $latest_key ) > 0 ) {
				$latest_key = $key;
				$latest_raw = $raw;
			}
		}

		return DailyBatch::from_array( $latest_raw );
	}

	/**
	 * @return list<DailyBatch>
	 */
	public function all(): array {
		$batches = $this->load_raw_map();
		$results = array();
		foreach ( $batches as $raw ) {
			$results[] = DailyBatch::from_array( $raw );
		}

		return $results;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function load_raw_map(): array {
		$raw = get_option( Identifiers::OPTION_DAILY_BATCH, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $key => $item ) {
			if ( is_string( $key ) && is_array( $item ) ) {
				/** @var array<string,mixed> $item */
				$clean[ $key ] = $item;
			}
		}

		return $clean;
	}
}
