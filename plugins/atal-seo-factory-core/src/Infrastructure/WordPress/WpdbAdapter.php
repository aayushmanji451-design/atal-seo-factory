<?php
/**
 * WordPress database adapter.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress;

use Atal\SeoFactory\Domain\Storage\RowStoreInterface;
use Atal\SeoFactory\Domain\Storage\SchemaDatabaseInterface;
use Atal\SeoFactory\Domain\Storage\TransactionManagerInterface;
use RuntimeException;
use wpdb;

/**
 * Wraps the narrow wpdb operations used by the skeleton.
 */
final class WpdbAdapter implements SchemaDatabaseInterface, RowStoreInterface, TransactionManagerInterface {

	public function __construct( private readonly wpdb $database ) {
	}

	public function table_prefix(): string {
		return $this->database->prefix;
	}

	public function charset_collate(): string {
		return $this->database->get_charset_collate();
	}

	public function create_or_update_table( string $sql ): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			$root = defined( 'ABSPATH' ) ? constant( 'ABSPATH' ) : '';
			if ( is_string( $root ) && is_readable( $root . 'wp-admin/includes/upgrade.php' ) ) {
				require_once $root . 'wp-admin/includes/upgrade.php';
			}
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			throw new RuntimeException( 'WordPress dbDelta is unavailable.' );
		}

		dbDelta( $sql );
	}

	public function drop_table( string $table_name ): void {
		$this->assert_identifier( $table_name );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal validated table name in an explicit rollback path.
		$result = $this->database->query( "DROP TABLE IF EXISTS {$table_name}" );
		if ( false === $result ) {
			throw new RuntimeException( 'Unable to roll back Core table: ' . $table_name );
		}
	}

	public function table_exists( string $table_name ): bool {
		$this->assert_identifier( $table_name );
		$query = $this->database->prepare( 'SHOW TABLES LIKE %s', $this->database->esc_like( $table_name ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only schema health check.
		return $table_name === $this->database->get_var( $query );
	}

	public function find_source_hash( string $table_name, string $identity_column, string $identity ): ?string {
		$this->assert_identifier( $table_name );
		$this->assert_identifier( $identity_column );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Both identifiers are internal and validated; the value is prepared.
		$query = $this->database->prepare( "SELECT source_hash FROM {$table_name} WHERE {$identity_column} = %s LIMIT 1", $identity );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Canonical import reads current source hashes.
		$value = $this->database->get_var( $query );

		return is_string( $value ) ? $value : null;
	}

	public function upsert_row( string $table_name, string $identity_column, string $identity, array $data, array $formats ): void {
		$this->assert_identifier( $table_name );
		$this->assert_identifier( $identity_column );
		$existing = $this->find_source_hash( $table_name, $identity_column, $identity );

		if ( null === $existing ) {
			$data[ $identity_column ] = $identity;
			$formats[]                = '%s';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository-owned canonical table insert.
			$result = $this->database->insert( $table_name, $data, $formats );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository-owned canonical table update.
			$result = $this->database->update( $table_name, $data, array( $identity_column => $identity ), $formats, array( '%s' ) );
		}

		if ( false === $result ) {
			throw new RuntimeException( 'Unable to persist canonical row in ' . $table_name );
		}
	}

	public function begin(): void {
		$this->run_transaction_statement( 'START TRANSACTION' );
	}

	public function commit(): void {
		$this->run_transaction_statement( 'COMMIT' );
	}

	public function rollback(): void {
		$this->run_transaction_statement( 'ROLLBACK' );
	}

	/**
	 * @param string $sql Transaction statement.
	 */
	private function run_transaction_statement( string $sql ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Fixed internal transaction statements only.
		if ( false === $this->database->query( $sql ) ) {
			throw new RuntimeException( 'Unable to execute database transaction statement.' );
		}
	}

	/**
	 * @param string $identifier Database identifier.
	 */
	private function assert_identifier( string $identifier ): void {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
			throw new RuntimeException( 'Unsafe database identifier.' );
		}
	}
}
