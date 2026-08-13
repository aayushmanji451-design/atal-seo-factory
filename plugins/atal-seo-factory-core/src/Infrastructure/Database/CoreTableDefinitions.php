<?php
/**
 * Version 1 Core table definitions.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\Database;

/**
 * Produces dbDelta-compatible definitions without executing them.
 */
final class CoreTableDefinitions {

	/**
	 * @param TableNames $tables          Resolved table names.
	 * @param string     $charset_collate Database charset/collation clause.
	 *
	 * @return array<string,string>
	 */
	public function sql( TableNames $tables, string $charset_collate ): array {
		$suffix = ' ENGINE=InnoDB' . ( '' === trim( $charset_collate ) ? '' : ' ' . trim( $charset_collate ) );

		return array(
			'courses'      => "CREATE TABLE {$tables->courses()} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n course_key varchar(191) NOT NULL,\n target_site varchar(64) NOT NULL,\n canonical_name varchar(255) NOT NULL,\n payload_json longtext NOT NULL,\n source_hash char(64) NOT NULL,\n contract_version varchar(32) NOT NULL,\n created_at datetime NOT NULL,\n updated_at datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY course_key (course_key),\n KEY target_site (target_site)\n){$suffix};",
			'topics'       => "CREATE TABLE {$tables->topics()} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n topic_key varchar(191) NOT NULL,\n course_key varchar(191) NOT NULL,\n target_site varchar(64) NOT NULL,\n title text NOT NULL,\n payload_json longtext NOT NULL,\n source_hash char(64) NOT NULL,\n contract_version varchar(32) NOT NULL,\n created_at datetime NOT NULL,\n updated_at datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY topic_key (topic_key),\n KEY course_key (course_key),\n KEY target_site (target_site)\n){$suffix};",
			'articles'     => "CREATE TABLE {$tables->articles()} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n article_key varchar(191) NOT NULL,\n course_key varchar(191) NOT NULL,\n target_site varchar(64) NOT NULL,\n status varchar(64) NOT NULL DEFAULT 'draft',\n payload_json longtext NULL,\n created_at datetime NOT NULL,\n updated_at datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY article_key (article_key),\n KEY course_key (course_key),\n KEY status (status)\n){$suffix};",
			'assets'       => "CREATE TABLE {$tables->assets()} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n asset_key varchar(191) NOT NULL,\n course_key varchar(191) NOT NULL,\n target_site varchar(64) NOT NULL,\n status varchar(64) NOT NULL DEFAULT 'pending',\n payload_json longtext NULL,\n created_at datetime NOT NULL,\n updated_at datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY asset_key (asset_key),\n KEY course_key (course_key),\n KEY status (status)\n){$suffix};",
			'publish_jobs' => "CREATE TABLE {$tables->publish_jobs()} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n job_key varchar(191) NOT NULL,\n article_key varchar(191) NULL,\n status varchar(64) NOT NULL DEFAULT 'pending',\n idempotency_key varchar(191) NOT NULL,\n attempts int(10) unsigned NOT NULL DEFAULT 0,\n available_at datetime NULL,\n created_at datetime NOT NULL,\n updated_at datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY job_key (job_key),\n UNIQUE KEY idempotency_key (idempotency_key),\n KEY status_available (status,available_at)\n){$suffix};",
			'cost_ledger'  => "CREATE TABLE {$tables->cost_ledger()} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n ledger_key varchar(191) NOT NULL,\n job_key varchar(191) NULL,\n provider varchar(100) NOT NULL,\n operation varchar(100) NOT NULL,\n amount_micros bigint(20) unsigned NOT NULL DEFAULT 0,\n currency char(3) NOT NULL DEFAULT 'USD',\n context_json longtext NULL,\n created_at datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY ledger_key (ledger_key),\n KEY job_key (job_key),\n KEY created_at (created_at)\n){$suffix};",
			'audit_logs'   => "CREATE TABLE {$tables->audit_logs()} (\n id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n event_key varchar(191) NOT NULL,\n actor_type varchar(64) NOT NULL,\n action varchar(100) NOT NULL,\n entity_type varchar(64) NOT NULL,\n entity_key varchar(191) NOT NULL,\n context_json longtext NULL,\n created_at datetime NOT NULL,\n PRIMARY KEY  (id),\n UNIQUE KEY event_key (event_key),\n KEY entity (entity_type,entity_key),\n KEY created_at (created_at)\n){$suffix};",
		);
	}
}
