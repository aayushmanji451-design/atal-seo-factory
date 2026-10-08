<?php
/**
 * In-memory repository for daily publication batches.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Infrastructure\InMemory;

use Atal\Queue\Contract\BatchRepositoryInterface;
use Atal\Queue\Domain\DailyBatch;

/**
 * Transient batch store for tests.
 */
final class InMemoryBatchRepository implements BatchRepositoryInterface {

	/**
	 * Stored batches indexed by batch_key.
	 *
	 * @var array<string,DailyBatch>
	 */
	private array $batches = array();

	public function save( DailyBatch $batch ): void {
		$this->batches[ $batch->batch_key ] = $batch;
	}

	public function find( string $batch_key ): ?DailyBatch {
		return $this->batches[ $batch_key ] ?? null;
	}

	public function find_latest(): ?DailyBatch {
		if ( array() === $this->batches ) {
			return null;
		}

		$sorted = array_values( $this->batches );
		usort( $sorted, static fn ( DailyBatch $a, DailyBatch $b ): int => $b->created_at <=> $a->created_at );

		return $sorted[0];
	}

	public function all(): array {
		return array_values( $this->batches );
	}
}
