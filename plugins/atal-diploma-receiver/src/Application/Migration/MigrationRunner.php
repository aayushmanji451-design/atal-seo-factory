<?php
/** Receiver migration runner. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Migration;

use InvalidArgumentException;
final class MigrationRunner {
	/** @param list<MigrationInterface> $migrations */
	public function __construct( private readonly array $migrations, private readonly ReceiverStateStoreInterface $state ) {
		$versions = array_map( static fn( MigrationInterface $migration ): int => $migration->version(), $migrations );
		if ( count( $versions ) !== count( array_unique( $versions ) ) ) {
			throw new InvalidArgumentException( 'Receiver migration versions must be unique.' ); }
	}
	public function migrate_to_latest(): void {
		$current = $this->state->database_version();
		foreach ( $this->migrations as $migration ) {
			if ( $migration->version() > $current ) {
				$migration->up();
				$current = $migration->version();
				$this->state->set_database_version( $current ); }
		} }
	public function rollback_to( int $target ): void {
		$current = $this->state->database_version();
		foreach ( array_reverse( $this->migrations ) as $migration ) {
			if ( $migration->version() <= $current && $migration->version() > $target ) {
				$migration->down();
				$current = 0;
				$this->state->set_database_version( $current ); }
		} }
}
