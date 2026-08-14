<?php
/** Secret-free receiver audit trail. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\DiplomaReceiver\Domain\Receiver\AuditLoggerInterface;
use Atal\DiplomaReceiver\Infrastructure\Database\TableNames;
use Atal\SeoImages\Contract\AuditLoggerInterface as Task05AuditLoggerInterface;
use wpdb;
final class WpdbAuditLogger implements AuditLoggerInterface, Task05AuditLoggerInterface {
	public function __construct( private readonly wpdb $database, private readonly TableNames $tables ) {}
	public function record( string $event, string $outcome, array $context = array() ): void {
		$safe    = array_intersect_key(
			$context,
			array(
				'article_key'   => true,
				'post_id'       => true,
				'attachment_id' => true,
				'reused'        => true,
			)
		);
		$encoded = wp_json_encode( $safe, JSON_UNESCAPED_SLASHES );
		$this->database->insert(
			$this->tables->audit(),
			array(
				'event_key'    => hash( 'sha256', $event . "\0" . $outcome . "\0" . microtime( true ) . "\0" . random_int( 1, PHP_INT_MAX ) ),
				'event'        => $event,
				'outcome'      => $outcome,
				'context_json' => false === $encoded ? '{}' : $encoded,
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		); }
}
