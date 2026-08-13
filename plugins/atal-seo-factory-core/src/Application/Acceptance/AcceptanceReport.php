<?php
/**
 * Task 02 staging acceptance report.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

/**
 * Immutable downloadable JSON report data.
 */
final class AcceptanceReport {

	/**
	 * @param list<AcceptanceCheck>                                                                                                                  $checks         Acceptance checks.
	 * @param array<string,mixed>                                                                                                                    $environment    Runtime and version values.
	 * @param array{inserts:int,updates:int,unchanged:int,writes:int,planned_writes:list<array{entity_type:string,entity_key:string,action:string}>} $first_dry_run  Initial planned changes.
	 * @param array{inserts:int,updates:int,unchanged:int,writes:int,planned_writes:list<array{entity_type:string,entity_key:string,action:string}>} $second_dry_run Post-import dry-run.
	 */
	public function __construct(
		private readonly array $checks,
		private readonly array $environment,
		private readonly array $first_dry_run,
		private readonly array $second_dry_run
	) {
	}

	/**
	 * @return list<AcceptanceCheck>
	 */
	public function checks(): array {
		return $this->checks;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function environment(): array {
		return $this->environment;
	}

	/**
	 * @return array{inserts:int,updates:int,unchanged:int,writes:int,planned_writes:list<array{entity_type:string,entity_key:string,action:string}>}
	 */
	public function first_dry_run(): array {
		return $this->first_dry_run;
	}

	public function status(): string {
		$statuses = array_map( static fn ( AcceptanceCheck $check ): string => $check->status(), $this->checks );
		if ( in_array( AcceptanceCheck::FAIL, $statuses, true ) ) {
			return AcceptanceCheck::FAIL;
		}

		return in_array( AcceptanceCheck::WARNING, $statuses, true ) ? 'PASS_WITH_WARNINGS' : AcceptanceCheck::PASS;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'report_version' => 'task-02-acceptance-v1',
			'generated_at'   => gmdate( 'c' ),
			'overall_status' => $this->status(),
			'environment'    => $this->environment,
			'first_dry_run'  => $this->first_dry_run,
			'second_dry_run' => $this->second_dry_run,
			'checks'         => array_map(
				static fn ( AcceptanceCheck $check ): array => $check->to_array(),
				$this->checks
			),
		);
	}
}
