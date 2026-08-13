<?php
/**
 * Read-only acceptance evidence gateway.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

/**
 * Supplies bounded database/content evidence around the canonical import.
 */
interface AcceptanceProbeInterface {

	/**
	 * @return array{count:int,ids_fingerprint:string,state_fingerprint:string}
	 */
	public function content_snapshot(): array;

	/**
	 * @param list<string> $table_names Internal validated table names.
	 *
	 * @return array<string,int>
	 */
	public function table_row_counts( array $table_names ): array;

	/**
	 * @param string $courses_table Internal validated courses table.
	 *
	 * @return array{active_total:int,institute_families:int,diploma_identities:int,institute_options:int}
	 */
	public function course_summary( string $courses_table ): array;

	/**
	 * @param string $publish_jobs_table Internal validated jobs table.
	 *
	 * @return array{count:int,attempts:int,state_fingerprint:string}
	 */
	public function publishing_snapshot( string $publish_jobs_table ): array;

	/**
	 * @param string $audit_logs_table Internal validated audit table.
	 */
	public function audit_log_count( string $audit_logs_table ): int;

	public function start_remote_request_monitor(): void;

	public function remote_request_count(): int;

	public function stop_remote_request_monitor(): void;
}
