<?php
/**
 * Versioned database migration runner.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Migration;

use Atal\SeoFactory\Domain\Storage\CoreStateStoreInterface;
use InvalidArgumentException;

/**
 * Applies pending versions once and supports intentional test rollback.
 */
final class MigrationRunner {

	/**
	 * Ordered migrations.
	 *
	 * @var list<MigrationInterface>
	 */
	private readonly array $migrations;

	/**
	 * @param list<MigrationInterface> $migrations Migrations in any order.
	 * @param CoreStateStoreInterface  $state_store Core state store.
	 */
	public function __construct( array $migrations, private readonly CoreStateStoreInterface $state_store ) {
		usort( $migrations, static fn ( MigrationInterface $left, MigrationInterface $right ): int => $left->version() <=> $right->version() );

		$versions = array_map( static fn ( MigrationInterface $migration ): int => $migration->version(), $migrations );
		if ( count( $versions ) !== count( array_unique( $versions ) ) ) {
			throw new InvalidArgumentException( 'Migration versions must be unique.' );
		}

		$this->migrations = $migrations;
	}

	/**
	 * Apply every version newer than the stored version.
	 */
	public function migrate_to_latest(): void {
		$current = $this->state_store->database_version();

		foreach ( $this->migrations as $migration ) {
			if ( $migration->version() <= $current ) {
				continue;
			}

			$migration->up();
			$current = $migration->version();
			$this->state_store->set_database_version( $current );
		}
	}

	/**
	 * Roll back applied migrations to an earlier version.
	 *
	 * This method is not called by deactivation or uninstall.
	 *
	 * @param int $target_version Version to retain.
	 */
	public function rollback_to( int $target_version ): void {
		if ( 0 > $target_version ) {
			throw new InvalidArgumentException( 'Rollback version cannot be negative.' );
		}

		$current  = $this->state_store->database_version();
		$reversed = array_reverse( $this->migrations );

		foreach ( $reversed as $migration ) {
			if ( $migration->version() > $current || $migration->version() <= $target_version ) {
				continue;
			}

			$migration->down();
			$current = $this->previous_version( $migration->version() );
			$this->state_store->set_database_version( $current );
		}
	}

	/**
	 * Return the previous defined migration version.
	 *
	 * @param int $version Current version.
	 */
	private function previous_version( int $version ): int {
		$previous = 0;
		foreach ( $this->migrations as $migration ) {
			if ( $migration->version() >= $version ) {
				break;
			}
			$previous = $migration->version();
		}

		return $previous;
	}
}
