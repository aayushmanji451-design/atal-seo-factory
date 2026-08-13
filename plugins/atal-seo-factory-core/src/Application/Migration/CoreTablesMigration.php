<?php
/**
 * Initial Core tables migration.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Migration;

use Atal\SeoFactory\Domain\Storage\SchemaDatabaseInterface;
use Atal\SeoFactory\Infrastructure\Database\CoreTableDefinitions;
use Atal\SeoFactory\Infrastructure\Database\TableNames;

/**
 * Creates the seven new Core-owned tables as database version 1.
 */
final class CoreTablesMigration implements MigrationInterface {

	public function __construct(
		private readonly SchemaDatabaseInterface $database,
		private readonly TableNames $table_names,
		private readonly CoreTableDefinitions $definitions
	) {
	}

	public function version(): int {
		return 1;
	}

	public function up(): void {
		foreach ( $this->definitions->sql( $this->table_names, $this->database->charset_collate() ) as $sql ) {
			$this->database->create_or_update_table( $sql );
		}
	}

	public function down(): void {
		foreach ( array_reverse( $this->table_names->all() ) as $table_name ) {
			$this->database->drop_table( $table_name );
		}
	}
}
