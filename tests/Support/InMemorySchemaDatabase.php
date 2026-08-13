<?php
/**
 * In-memory schema database test double.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Support;

use Atal\SeoFactory\Domain\Storage\SchemaDatabaseInterface;
use RuntimeException;

/**
 * Records table creation, rollback, and health reads.
 */
final class InMemorySchemaDatabase implements SchemaDatabaseInterface {

	/** @var array<string,bool> */
	private array $tables = array();

	private int $create_calls = 0;

	private int $drop_calls = 0;

	private int $read_calls = 0;

	public function table_prefix(): string {
		return 'wp_';
	}

	public function charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	public function create_or_update_table( string $sql ): void {
		if ( 1 !== preg_match( '/^CREATE TABLE ([A-Za-z0-9_]+)/', $sql, $matches ) ) {
			throw new RuntimeException( 'Unable to parse test table definition.' );
		}

		$this->tables[ $matches[1] ] = true;
		++$this->create_calls;
	}

	public function drop_table( string $table_name ): void {
		unset( $this->tables[ $table_name ] );
		++$this->drop_calls;
	}

	public function table_exists( string $table_name ): bool {
		++$this->read_calls;
		return isset( $this->tables[ $table_name ] );
	}

	/**
	 * @param list<string> $table_names Table names to mark present.
	 */
	public function seed_tables( array $table_names ): void {
		foreach ( $table_names as $table_name ) {
			$this->tables[ $table_name ] = true;
		}
	}

	public function table_count(): int {
		return count( $this->tables );
	}

	public function create_calls(): int {
		return $this->create_calls;
	}

	public function drop_calls(): int {
		return $this->drop_calls;
	}

	public function read_calls(): int {
		return $this->read_calls;
	}
}
