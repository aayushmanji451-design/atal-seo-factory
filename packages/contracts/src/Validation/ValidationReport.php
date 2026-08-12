<?php
/**
 * Knowledge validation report.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

/**
 * Immutable aggregate of validation issues, metrics, and genuine open blocks.
 */
final class ValidationReport {

	/**
	 * Validation issues.
	 *
	 * @var list<ValidationIssue>
	 */
	private readonly array $issues;

	/**
	 * Validation metrics.
	 *
	 * @var array<string, int|string|bool>
	 */
	private readonly array $metrics;

	/**
	 * Genuine open missing-data blocks.
	 *
	 * @var list<array<string, mixed>>
	 */
	private readonly array $missing_data_blocks;

	/**
	 * Create a validation report.
	 *
	 * @param list<ValidationIssue>         $issues              Validation issues.
	 * @param array<string,int|string|bool> $metrics             Validation metrics.
	 * @param list<array<string,mixed>>     $missing_data_blocks Genuine open blocks.
	 */
	public function __construct( array $issues, array $metrics, array $missing_data_blocks ) {
		$this->issues              = $issues;
		$this->metrics             = $metrics;
		$this->missing_data_blocks = $missing_data_blocks;
	}

	/**
	 * Determine whether every required validation passed.
	 */
	public function is_valid(): bool {
		return array() === $this->issues;
	}

	/**
	 * Return all issues.
	 *
	 * @return list<ValidationIssue>
	 */
	public function issues(): array {
		return $this->issues;
	}

	/**
	 * Return all metrics.
	 *
	 * @return array<string, int|string|bool>
	 */
	public function metrics(): array {
		return $this->metrics;
	}

	/**
	 * Return a metric by name.
	 *
	 * @param string $name Metric name.
	 *
	 * @return int|string|bool|null
	 */
	public function metric( string $name ): int|string|bool|null {
		return $this->metrics[ $name ] ?? null;
	}

	/**
	 * Return the genuine open missing-data blocks.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function missing_data_blocks(): array {
		return $this->missing_data_blocks;
	}
}
