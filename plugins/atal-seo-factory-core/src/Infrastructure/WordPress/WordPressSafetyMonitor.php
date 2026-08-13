<?php
/**
 * WordPress Task 02 acceptance safety monitor.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress;

use Atal\SeoFactory\Application\Acceptance\SafetyMonitorInterface;
use Atal\SeoFactory\Application\Acceptance\SafetyObservation;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use wpdb;

/**
 * Observes content, HTTP, queue, and audit-log activity during one request.
 */
final class WordPressSafetyMonitor implements SafetyMonitorInterface {

	/** @var int Number of post/page saves seen during acceptance. */
	private int $saved_posts_pages = 0;

	/** @var int Number of attachment changes seen during acceptance. */
	private int $attachment_changes = 0;

	/** @var int Number of Rank Math post-meta changes seen during acceptance. */
	private int $rank_math_changes = 0;

	/** @var int Number of outbound WordPress HTTP events seen during acceptance. */
	private int $external_requests = 0;

	/** @var int Executed publish-job count captured before acceptance. */
	private int $publish_jobs_before = 0;

	/** @var int Sensitive audit-log match count captured before acceptance. */
	private int $sensitive_logs_before = 0;

	public function __construct(
		private readonly wpdb $database,
		private readonly TableNames $tables
	) {
	}

	public function start(): void {
		$this->publish_jobs_before   = $this->executed_publish_jobs();
		$this->sensitive_logs_before = $this->sensitive_audit_logs();
		add_action( 'save_post', array( $this, 'capture_post_save' ), 10, 1 );
		add_action( 'add_attachment', array( $this, 'capture_attachment_change' ), 10, 1 );
		add_action( 'edit_attachment', array( $this, 'capture_attachment_change' ), 10, 1 );
		add_action( 'delete_attachment', array( $this, 'capture_attachment_change' ), 10, 1 );
		add_action( 'added_post_meta', array( $this, 'capture_post_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'capture_post_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'capture_post_meta' ), 10, 4 );
		add_action( 'http_api_debug', array( $this, 'capture_http_request' ), 10, 2 );
	}

	public function stop(): SafetyObservation {
		remove_action( 'save_post', array( $this, 'capture_post_save' ), 10 );
		remove_action( 'add_attachment', array( $this, 'capture_attachment_change' ), 10 );
		remove_action( 'edit_attachment', array( $this, 'capture_attachment_change' ), 10 );
		remove_action( 'delete_attachment', array( $this, 'capture_attachment_change' ), 10 );
		remove_action( 'added_post_meta', array( $this, 'capture_post_meta' ), 10 );
		remove_action( 'updated_post_meta', array( $this, 'capture_post_meta' ), 10 );
		remove_action( 'deleted_post_meta', array( $this, 'capture_post_meta' ), 10 );
		remove_action( 'http_api_debug', array( $this, 'capture_http_request' ), 10 );

		return new SafetyObservation(
			$this->saved_posts_pages,
			$this->attachment_changes,
			$this->rank_math_changes,
			$this->external_requests,
			max( 0, $this->executed_publish_jobs() - $this->publish_jobs_before ),
			max( 0, $this->sensitive_audit_logs() - $this->sensitive_logs_before )
		);
	}

	public function capture_post_save( int $post_id ): void {
		$post_type = get_post_type( $post_id );
		if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
			++$this->saved_posts_pages;
		}
	}

	public function capture_attachment_change( int $attachment_id ): void {
		unset( $attachment_id );
		++$this->attachment_changes;
	}

	public function capture_post_meta( int $meta_id, int $object_id, string $meta_key, mixed $meta_value = null ): void {
		unset( $meta_id, $object_id, $meta_value );
		if ( str_starts_with( $meta_key, 'rank_math_' ) ) {
			++$this->rank_math_changes;
		}
	}

	public function capture_http_request( mixed $response, string $context ): void {
		unset( $response, $context );
		++$this->external_requests;
	}

	private function executed_publish_jobs(): int {
		$table = $this->tables->publish_jobs();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal validated Task 02 table name; aggregate safety read only.
		$value = $this->database->get_var( "SELECT COUNT(*) FROM {$table} WHERE attempts > 0 OR status IN ('running', 'completed', 'failed')" );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	private function sensitive_audit_logs(): int {
		$table = $this->tables->audit_logs();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal validated Task 02 table name; keyword count never returns log data.
		$value = $this->database->get_var( "SELECT COUNT(*) FROM {$table} WHERE LOWER(COALESCE(context_json, '')) REGEXP 'password|api[_-]?key|hmac|shared[_-]?secret|auth(entication)?[_-]?token|bearer'" );

		return is_numeric( $value ) ? (int) $value : 0;
	}
}
