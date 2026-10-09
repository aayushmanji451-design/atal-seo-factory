<?php
/**
 * Daily batch item representing one candidate post in the daily plan.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

use Atal\Queue\Support\Arr;
use Atal\Topics\Domain\QualityState;

/**
 * Immutable representation of one planned article in a daily batch.
 */
final class DailyBatchItem {

	public const STATUS_PENDING     = 'pending_validation';
	public const STATUS_PASSED      = 'validated_pass';
	public const STATUS_BLOCKED     = 'validated_blocked';
	public const STATUS_QUEUED      = 'queued';
	public const STATUS_RUNNING     = 'in_progress';
	public const STATUS_PUBLISHED   = 'published';
	public const STATUS_FAILED      = 'failed';
	public const STATUS_ROLLED_BACK = 'rolled_back';

	/**
	 * Create a batch item.
	 *
	 * @param string                    $item_key            Deterministic item key.
	 * @param string                    $batch_key           Owning batch key.
	 * @param string                    $target_site         Canonical target site.
	 * @param string                    $course_key          Canonical course key.
	 * @param string                    $intent              Approved search intent.
	 * @param string                    $primary_keyword     Normalized primary keyword.
	 * @param int                       $year                Publishing year.
	 * @param string                    $title               Article title.
	 * @param string                    $slug                Article slug.
	 * @param string                    $validation_state    Validation state (PASS, NEEDS_REVIEW, REJECTED).
	 * @param list<array<string,string>> $validation_findings Explainable findings.
	 * @param string                    $status              Batch lifecycle status.
	 * @param string                    $current_stage       Active or last completed stage.
	 * @param int|null                  $post_id             Target post ID if created.
	 * @param string                    $published_url       Final published URL if available.
	 * @param string                    $last_error          Last failure reason.
	 * @param array<string,mixed>       $proposal_data       Serialized proposal payload.
	 */
	public function __construct(
		public readonly string $item_key,
		public readonly string $batch_key,
		public readonly string $target_site,
		public readonly string $course_key,
		public readonly string $intent,
		public readonly string $primary_keyword,
		public readonly int $year,
		public readonly string $title,
		public readonly string $slug,
		public readonly string $validation_state = QualityState::PASS,
		public readonly array $validation_findings = array(),
		public readonly string $status = self::STATUS_PENDING,
		public readonly string $current_stage = JobStage::VALIDATE,
		public readonly ?int $post_id = null,
		public readonly string $published_url = '',
		public readonly string $last_error = '',
		public readonly array $proposal_data = array()
	) {
	}

	/**
	 * Compute deterministic item key.
	 *
	 * @param string $target_site     Site.
	 * @param string $course_key      Course.
	 * @param string $intent          Intent.
	 * @param string $primary_keyword Keyword.
	 * @param int    $year            Year.
	 */
	public static function compute_key( string $target_site, string $course_key, string $intent, string $primary_keyword, int $year ): string {
		$raw = implode( ':', array( $target_site, $course_key, $intent, trim( strtolower( $primary_keyword ) ), (string) $year ) );

		return 'item_' . substr( hash( 'sha256', $raw ), 0, 36 );
	}

	/**
	 * Whether the item passed all canonical safety and quality gates.
	 */
	public function is_valid(): bool {
		return QualityState::PASS === $this->validation_state;
	}

	/**
	 * Hydrate from persisted row.
	 *
	 * @param array<mixed,mixed> $row Raw row.
	 */
	public static function from_array( array $row ): self {
		$post_id_raw = $row['post_id'] ?? null;
		$post_id     = is_numeric( $post_id_raw ) ? (int) $post_id_raw : null;

		/** @var list<array<string,string>> $findings */
		$findings = is_array( $row['validation_findings'] ?? null ) ? $row['validation_findings'] : array();

		return new self(
			Arr::string( $row, 'item_key' ),
			Arr::string( $row, 'batch_key' ),
			Arr::string( $row, 'target_site' ),
			Arr::string( $row, 'course_key' ),
			Arr::string( $row, 'intent' ),
			Arr::string( $row, 'primary_keyword' ),
			Arr::int( $row, 'year', (int) gmdate( 'Y' ) ),
			Arr::string( $row, 'title' ),
			Arr::string( $row, 'slug' ),
			Arr::string( $row, 'validation_state', QualityState::PASS ),
			$findings,
			Arr::string( $row, 'status', self::STATUS_PENDING ),
			Arr::string( $row, 'current_stage', JobStage::VALIDATE ),
			$post_id,
			Arr::string( $row, 'published_url' ),
			Arr::string( $row, 'last_error' ),
			Arr::map( $row, 'proposal_data' )
		);
	}

	/**
	 * Export to array.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'item_key'            => $this->item_key,
			'batch_key'           => $this->batch_key,
			'target_site'         => $this->target_site,
			'course_key'          => $this->course_key,
			'intent'              => $this->intent,
			'primary_keyword'     => $this->primary_keyword,
			'year'                => $this->year,
			'title'               => $this->title,
			'slug'                => $this->slug,
			'validation_state'    => $this->validation_state,
			'validation_findings' => $this->validation_findings,
			'status'              => $this->status,
			'current_stage'       => $this->current_stage,
			'post_id'             => $this->post_id,
			'published_url'       => $this->published_url,
			'last_error'          => $this->last_error,
			'proposal_data'       => $this->proposal_data,
		);
	}

	/**
	 * Return a modified copy.
	 *
	 * @param array<string,mixed> $changes Overrides.
	 */
	public function with( array $changes ): self {
		return self::from_array( array_merge( $this->to_array(), $changes ) );
	}
}
