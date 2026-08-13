<?php
/** Receiver dbDelta definitions. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\Database;

final class TableDefinitions {
	/** @return list<string> */
	public function sql( TableNames $tables, string $charset_collate ): array {
		$suffix = ' ENGINE=InnoDB' . ( '' === trim( $charset_collate ) ? '' : ' ' . trim( $charset_collate ) );
		return array(
			"CREATE TABLE {$tables->receipts()} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n idempotency_hash char(64) NOT NULL,\n nonce_hash char(64) NOT NULL,\n request_hash char(64) NOT NULL,\n article_key varchar(191) NOT NULL,\n status varchar(32) NOT NULL DEFAULT 'pending',\n response_json longtext NULL,\n recovery_hash char(64) NULL,\n previous_state_json longtext NULL,\n created_draft tinyint(1) NOT NULL DEFAULT 0,\n recovery_used tinyint(1) NOT NULL DEFAULT 0,\n created_at datetime NOT NULL,\n updated_at datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY idempotency_hash (idempotency_hash),\n UNIQUE KEY nonce_hash (nonce_hash),\n KEY article_key (article_key),\n KEY recovery_hash (recovery_hash)\n){$suffix};",
			"CREATE TABLE {$tables->audit()} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n event_key char(64) NOT NULL,\n event varchar(64) NOT NULL,\n outcome varchar(32) NOT NULL,\n context_json text NULL,\n created_at datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY event_key (event_key),\n KEY event_created (event,created_at)\n){$suffix};",
		);
	}
}
