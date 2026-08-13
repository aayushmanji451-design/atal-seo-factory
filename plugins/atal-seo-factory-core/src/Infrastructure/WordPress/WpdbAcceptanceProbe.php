<?php
/**
 * WordPress Task 02 acceptance evidence.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress;

use Atal\SeoFactory\Application\Acceptance\AcceptanceProbeInterface;
use RuntimeException;
use wpdb;

/**
 * Reads bounded evidence and observes HTTP calls without blocking them.
 */
final class WpdbAcceptanceProbe implements AcceptanceProbeInterface {

	/** @var int Outbound requests observed in the current acceptance run. */
	private int $remote_requests = 0;

	/** @var bool Whether the HTTP observation filter is installed. */
	private bool $monitoring = false;

	public function __construct( private readonly wpdb $database ) {
	}

	public function content_snapshot(): array {
		$this->assert_identifier( $this->database->posts );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only snapshot from the validated native posts table.
		$rows       = $this->database->get_results( "SELECT ID, post_type, post_status, post_modified_gmt FROM {$this->database->posts} WHERE post_type IN ('post','page') ORDER BY ID ASC", ARRAY_A );
		$normalized = is_array( $rows ) ? $rows : array();
		$ids        = array_map( fn ( array $row ): int => $this->integer_value( $row['ID'] ?? null ), $normalized );

		return array(
			'count'             => count( $normalized ),
			'ids_fingerprint'   => $this->fingerprint( $ids ),
			'state_fingerprint' => $this->fingerprint( $normalized ),
		);
	}

	public function table_row_counts( array $table_names ): array {
		$counts = array();
		foreach ( $table_names as $table_name ) {
			$this->assert_identifier( $table_name );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal validated table name; scalar acceptance read.
			$counts[ $table_name ] = $this->integer_value( $this->database->get_var( "SELECT COUNT(*) FROM {$table_name}" ) );
		}

		return $counts;
	}

	public function course_summary( string $courses_table ): array {
		$this->assert_identifier( $courses_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal validated table name; bounded canonical rows.
		$rows    = $this->database->get_results( "SELECT target_site, payload_json FROM {$courses_table} ORDER BY id ASC", ARRAY_A );
		$summary = array(
			'active_total'       => 0,
			'institute_families' => 0,
			'diploma_identities' => 0,
			'institute_options'  => 0,
		);

		if ( ! is_array( $rows ) ) {
			return $summary;
		}

		foreach ( $rows as $row ) {
			$payload = json_decode( $this->string_value( $row['payload_json'] ?? null ), true );
			if ( ! is_array( $payload ) ) {
				continue;
			}
			if ( 'ACTIVE_CANONICAL' === ( $payload['course_status'] ?? null ) ) {
				++$summary['active_total'];
			}
			$target = $this->string_value( $row['target_site'] ?? null );
			if ( 'atal_institute' === $target ) {
				++$summary['institute_families'];
				$options                       = $payload['options'] ?? array();
				$summary['institute_options'] += is_array( $options ) ? count( $options ) : 0;
			} elseif ( 'atal_diploma' === $target ) {
				++$summary['diploma_identities'];
			}
		}

		return $summary;
	}

	public function publishing_snapshot( string $publish_jobs_table ): array {
		$this->assert_identifier( $publish_jobs_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal validated table name; bounded safety snapshot.
		$rows       = $this->database->get_results( "SELECT id, job_key, status, attempts, updated_at FROM {$publish_jobs_table} ORDER BY id ASC", ARRAY_A );
		$normalized = is_array( $rows ) ? $rows : array();
		$attempts   = array_sum( array_map( fn ( array $row ): int => $this->integer_value( $row['attempts'] ?? null ), $normalized ) );

		return array(
			'count'             => count( $normalized ),
			'attempts'          => $attempts,
			'state_fingerprint' => $this->fingerprint( $normalized ),
		);
	}

	public function audit_log_count( string $audit_logs_table ): int {
		$this->assert_identifier( $audit_logs_table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal validated table name; scalar safety read.
		return $this->integer_value( $this->database->get_var( "SELECT COUNT(*) FROM {$audit_logs_table}" ) );
	}

	public function start_remote_request_monitor(): void {
		$this->remote_requests = 0;
		if ( ! $this->monitoring ) {
			add_filter( 'pre_http_request', array( $this, 'observe_remote_request' ), PHP_INT_MIN, 3 );
			$this->monitoring = true;
		}
	}

	public function remote_request_count(): int {
		return $this->remote_requests;
	}

	public function stop_remote_request_monitor(): void {
		if ( $this->monitoring ) {
			remove_filter( 'pre_http_request', array( $this, 'observe_remote_request' ), PHP_INT_MIN );
			$this->monitoring = false;
		}
	}

	/**
	 * Observe an outbound WordPress HTTP request without changing its result.
	 *
	 * @param mixed               $preempt     Existing preempt value.
	 * @param array<string,mixed> $parsed_args Request arguments.
	 * @param string              $url         Request URL. Never retained.
	 */
	public function observe_remote_request( mixed $preempt, array $parsed_args, string $url ): mixed {
		unset( $parsed_args, $url );
		++$this->remote_requests;

		return $preempt;
	}

	private function assert_identifier( string $identifier ): void {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
			throw new RuntimeException( 'Unsafe database identifier.' );
		}
	}

	private function fingerprint( mixed $value ): string {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			throw new RuntimeException( 'Unable to fingerprint bounded acceptance evidence.' );
		}

		return hash( 'sha256', $encoded );
	}

	private function integer_value( mixed $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}

	private function string_value( mixed $value ): string {
		return is_string( $value ) ? $value : '';
	}
}
