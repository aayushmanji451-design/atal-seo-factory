<?php
/** Secret-free Core audit-log writer. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Infrastructure\WordPress\Canary;

use Atal\SeoFactory\Application\Canary\CanaryException;
use Atal\SeoFactory\Domain\Canary\CanaryAuditLoggerInterface;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use Atal\SeoImages\Contract\AuditLoggerInterface as Task05AuditLoggerInterface;
use wpdb;

final class WpdbCanaryAuditLogger implements CanaryAuditLoggerInterface, Task05AuditLoggerInterface {
	public function __construct( private readonly wpdb $database, private readonly TableNames $tables ) {}
	public function record( string $event, string $outcome, array $context = array() ): void {
		$safe    = array_intersect_key(
			$context,
			array(
				'article_key'   => true,
				'post_id'       => true,
				'target_site'   => true,
				'attachment_id' => true,
				'reused'        => true,
			)
		);
		$encoded = wp_json_encode( $safe, JSON_UNESCAPED_SLASHES );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository-owned bounded audit insert.
		$result = $this->database->insert(
			$this->tables->audit_logs(),
			array(
				'event_key'    => hash( 'sha256', $event . "\0" . $outcome . "\0" . microtime( true ) . "\0" . random_int( 1, PHP_INT_MAX ) ),
				'actor_type'   => 'administrator',
				'action'       => $event,
				'entity_type'  => 'canary_article',
				'entity_key'   => is_string( $safe['article_key'] ?? null ) ? $safe['article_key'] : 'task04',
				'context_json' => false === $encoded ? '{}' : $encoded,
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $result ) {
			throw new CanaryException( 'Unable to write the secret-free canary audit event.' );
		}
	}
}
