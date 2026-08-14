<?php
/**
 * Machine-readable validation finding.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Domain;

/**
 * Explains one deterministic gate and a safe correction.
 */
final class ValidationFinding {

	/**
	 * Create one explainable finding.
	 *
	 * @param string $rule_id         Stable rule identifier.
	 * @param string $status          Quality state.
	 * @param string $field           Affected field.
	 * @param string $expected        Expected value.
	 * @param string $actual          Actual value.
	 * @param string $explanation     Human-readable evidence.
	 * @param string $safe_correction Safe deterministic correction.
	 */
	public function __construct(
		private readonly string $rule_id,
		private readonly string $status,
		private readonly string $field,
		private readonly string $expected,
		private readonly string $actual,
		private readonly string $explanation,
		private readonly string $safe_correction
	) {
	}

	/** Return the quality state. */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Export the finding.
	 *
	 * @return array{rule_id:string,status:string,field:string,expected:string,actual:string,explanation:string,safe_correction:string}
	 */
	public function to_array(): array {
		return array(
			'rule_id'         => $this->rule_id,
			'status'          => $this->status,
			'field'           => $this->field,
			'expected'        => $this->expected,
			'actual'          => $this->actual,
			'explanation'     => $this->explanation,
			'safe_correction' => $this->safe_correction,
		);
	}
}
