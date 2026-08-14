<?php
/**
 * Deterministic validation report.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Domain;

/**
 * Aggregates findings without hiding lower-severity evidence.
 */
final class ValidationReport {

	/**
	 * Create an aggregate report.
	 *
	 * @param array $findings Findings.
	 * @phpstan-param list<ValidationFinding> $findings
	 */
	public function __construct( private readonly array $findings ) {
	}

	/** Return the highest-severity quality state. */
	public function state(): string {
		$state = QualityState::PASS;
		foreach ( $this->findings as $finding ) {
			if ( QualityState::REJECTED === $finding->status() ) {
				return QualityState::REJECTED;
			}
			if ( QualityState::NEEDS_REVIEW === $finding->status() ) {
				$state = QualityState::NEEDS_REVIEW;
			}
		}

		return $state;
	}

	/**
	 * Return every finding.
	 *
	 * @return list<ValidationFinding>
	 */
	public function findings(): array {
		return $this->findings;
	}

	/**
	 * Export the report.
	 *
	 * @return array{state:string,findings:list<array<string,string>>}
	 */
	public function to_array(): array {
		return array(
			'state'    => $this->state(),
			'findings' => array_map( static fn ( ValidationFinding $finding ): array => $finding->to_array(), $this->findings ),
		);
	}
}
