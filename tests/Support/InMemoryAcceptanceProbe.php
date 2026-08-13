<?php
/**
 * In-memory Task 02 acceptance evidence.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Support;

use Atal\SeoFactory\Application\Acceptance\AcceptanceProbeInterface;

/**
 * Mirrors the immutable WordPress-content and empty-job staging baseline.
 */
final class InMemoryAcceptanceProbe implements AcceptanceProbeInterface {

	private int $remote_requests = 0;

	public function __construct( private readonly InMemoryKnowledgeRepository $repository ) {
	}

	public function content_snapshot(): array {
		return array(
			'count'             => 4,
			'ids_fingerprint'   => hash( 'sha256', '1,2,3,4' ),
			'state_fingerprint' => hash( 'sha256', 'unchanged-content' ),
		);
	}

	public function table_row_counts( array $table_names ): array {
		$counts = array_fill_keys( $table_names, 0 );
		foreach ( $table_names as $table_name ) {
			if ( str_ends_with( $table_name, '_courses' ) ) {
				$counts[ $table_name ] = $this->repository->course_count();
			} elseif ( str_ends_with( $table_name, '_topics' ) ) {
				$counts[ $table_name ] = $this->repository->topic_count();
			}
		}

		return $counts;
	}

	public function course_summary( string $courses_table ): array {
		unset( $courses_table );
		$summary = array(
			'active_total'       => 0,
			'institute_families' => 0,
			'diploma_identities' => 0,
			'institute_options'  => 0,
		);
		foreach ( $this->repository->courses() as $course ) {
			$payload = json_decode( $course->payload_json(), true );
			if ( ! is_array( $payload ) ) {
				continue;
			}
			if ( 'ACTIVE_CANONICAL' === ( $payload['course_status'] ?? null ) ) {
				++$summary['active_total'];
			}
			if ( 'atal_institute' === $course->target_site() ) {
				++$summary['institute_families'];
				$options                       = $payload['options'] ?? array();
				$summary['institute_options'] += is_array( $options ) ? count( $options ) : 0;
			} elseif ( 'atal_diploma' === $course->target_site() ) {
				++$summary['diploma_identities'];
			}
		}

		return $summary;
	}

	public function publishing_snapshot( string $publish_jobs_table ): array {
		unset( $publish_jobs_table );

		return array(
			'count'             => 0,
			'attempts'          => 0,
			'state_fingerprint' => hash( 'sha256', '[]' ),
		);
	}

	public function audit_log_count( string $audit_logs_table ): int {
		unset( $audit_logs_table );

		return 0;
	}

	public function start_remote_request_monitor(): void {
		$this->remote_requests = 0;
	}

	public function remote_request_count(): int {
		return $this->remote_requests;
	}

	public function stop_remote_request_monitor(): void {
	}

	public function record_remote_request(): void {
		++$this->remote_requests;
	}
}
