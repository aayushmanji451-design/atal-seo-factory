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

	private ?InMemoryKnowledgeRepository $repository = null;

	/** @var array{courses:array<string,\Atal\SeoFactory\Domain\Knowledge\CourseRecord>,topics:array<string,\Atal\SeoFactory\Domain\Knowledge\TopicRecord>}|null */
	private ?array $repository_snapshot = null;

	public function attach_repository( InMemoryKnowledgeRepository $repository ): void {
		$this->repository = $repository;
	}

	public function begin(): void {
		++$this->begins;
		$this->repository_snapshot = null === $this->repository ? null : $this->repository->snapshot();
	}

	public function commit(): void {
		++$this->commits;
		$this->repository_snapshot = null;
	}

	public function rollback(): void {
		++$this->rollbacks;
		if ( null !== $this->repository && null !== $this->repository_snapshot ) {
			$this->repository->restore( $this->repository_snapshot );
		}
		$this->repository_snapshot = null;
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
