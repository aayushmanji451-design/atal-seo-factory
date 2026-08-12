<?php
/**
 * In-memory transaction test double.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Support;

use Atal\SeoFactory\Domain\Storage\TransactionManagerInterface;

/**
 * Counts explicit transaction boundaries.
 */
final class InMemoryTransactionManager implements TransactionManagerInterface {

	private int $begins = 0;

	private int $commits = 0;

	private int $rollbacks = 0;

	public function begin(): void {
		++$this->begins;
	}

	public function commit(): void {
		++$this->commits;
	}

	public function rollback(): void {
		++$this->rollbacks;
	}

	public function begins(): int {
		return $this->begins;
	}

	public function commits(): int {
		return $this->commits;
	}

	public function rollbacks(): int {
		return $this->rollbacks;
	}
}
