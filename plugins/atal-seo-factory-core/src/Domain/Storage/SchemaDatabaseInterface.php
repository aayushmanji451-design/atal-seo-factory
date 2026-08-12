<?php
/**
 * Database schema operations.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Domain\Storage;

/**
 * Minimal schema gateway used by migrations and health checks.
 */
interface SchemaDatabaseInterface {

	public function table_prefix(): string;

	public function charset_collate(): string;

	/**
	 * @param string $sql CREATE TABLE statement.
	 */
	public function create_or_update_table( string $sql ): void;

	/**
	 * @param string $table_name Fully qualified table name.
	 */
	public function drop_table( string $table_name ): void;

	/**
	 * @param string $table_name Fully qualified table name.
	 */
	public function table_exists( string $table_name ): bool;
}
