<?php
/**
 * Contract for persisting and retrieving daily batches.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Contract;

use Atal\Queue\Domain\DailyBatch;

/**
 * Storage gateway for daily publication batches.
 */
interface BatchRepositoryInterface {

	/**
	 * Save or update a daily batch.
	 *
	 * @param DailyBatch $batch Batch to persist.
	 */
	public function save( DailyBatch $batch ): void;

	/**
	 * Find a batch by its unique key.
	 *
	 * @param string $batch_key Batch key.
	 */
	public function find( string $batch_key ): ?DailyBatch;

	/**
	 * Find the most recent daily batch.
	 */
	public function find_latest(): ?DailyBatch;

	/**
	 * Return all stored batches.
	 *
	 * @return list<DailyBatch>
	 */
	public function all(): array;
}
