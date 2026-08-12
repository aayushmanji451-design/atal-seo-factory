<?php
/**
 * Transaction boundary contract.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Domain\Storage;

/**
 * Controls an explicit canonical knowledge import transaction.
 */
interface TransactionManagerInterface {

	public function begin(): void;

	public function commit(): void;

	public function rollback(): void;
}
