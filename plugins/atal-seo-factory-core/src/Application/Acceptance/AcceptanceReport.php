<?php
/**
 * Task 02 staging acceptance report.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

use Atal\SeoFactory\Plugin;

/**
 * Produces a bounded JSON-safe report.
 */
final class AcceptanceReport {

	/**
	 * @param list<AcceptanceCheck> $checks       Individual checks.
	 * @param string                $started_at   UTC ISO timestamp.
	 * @param string                $completed_at UTC ISO timestamp.
	 */
	public function __construct(
		private readonly array $checks,
		private readonly string $started_at,
		private readonly string $completed_at
	) {
	}

	public function status(): string {
		$statuses = array_map( static fn ( AcceptanceCheck $check ): string => $check->status(), $this->checks );
		if ( in_array( AcceptanceCheck::FAIL, $statuses, true ) ) {
			return AcceptanceCheck::FAIL;
		}

		return in_array( AcceptanceCheck::WARNING, $statuses, true ) ? AcceptanceCheck::WARNING : AcceptanceCheck::PASS;
	}

	/**
	 * @return array{report_version:string,plugin_version:string,scope:string,status:string,started_at:string,completed_at:string,checks:list<array{check_id:string,status:string,expected:mixed,actual:mixed,message:string}>}
	 */
	public function to_array(): array {
		return array(
			'report_version' => '1.0',
			'plugin_version' => Plugin::VERSION,
			'scope'          => 'task-02-staging-acceptance',
			'status'         => $this->status(),
			'started_at'     => $this->started_at,
			'completed_at'   => $this->completed_at,
			'checks'         => array_map( static fn ( AcceptanceCheck $check ): array => $check->to_array(), $this->checks ),
		);
	}
}
