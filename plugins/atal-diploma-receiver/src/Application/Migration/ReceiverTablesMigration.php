<?php
/** Initial receiver tables. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Migration;

use Atal\DiplomaReceiver\Infrastructure\Database\TableDefinitions;
use Atal\DiplomaReceiver\Infrastructure\Database\TableNames;
final class ReceiverTablesMigration implements MigrationInterface {
	public function __construct( private readonly SchemaDatabaseInterface $database, private readonly TableNames $tables, private readonly TableDefinitions $definitions ) {}
	public function version(): int {
		return 1; }
	public function up(): void {
		foreach ( $this->definitions->sql( $this->tables, $this->database->charset_collate() ) as $sql ) {
			$this->database->create_or_update( $sql ); } }
	public function down(): void {
		foreach ( array_reverse( $this->tables->all() ) as $table ) {
			$this->database->drop( $table ); } }
}
