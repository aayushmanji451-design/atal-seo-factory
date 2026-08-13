<?php
/**
 * Task 02 acceptance report persistence.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

/**
 * Stores only the latest sanitized development report.
 */
interface AcceptanceReportStoreInterface {

	/**
	 * @param array<string,mixed> $report Sanitized JSON-safe report.
	 */
	public function save( array $report ): void;

	/**
	 * @return array<string,mixed>|null
	 */
	public function latest(): ?array;
}
