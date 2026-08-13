<?php
/** Receiver wpdb transaction/schema adapter. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\DiplomaReceiver\Application\Migration\SchemaDatabaseInterface;
use Atal\DiplomaReceiver\Domain\Receiver\TransactionManagerInterface;
use RuntimeException;
use wpdb;
final class WpdbAdapter implements SchemaDatabaseInterface, TransactionManagerInterface {
	public function __construct( private readonly wpdb $database ) {}
	public function prefix(): string {
		return $this->database->prefix; }
	public function charset_collate(): string {
		return $this->database->get_charset_collate(); }
	public function create_or_update( string $sql ): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			$root = defined( 'ABSPATH' ) ? constant( 'ABSPATH' ) : '';
			if ( is_string( $root ) && is_readable( $root . 'wp-admin/includes/upgrade.php' ) ) {
				require_once $root . 'wp-admin/includes/upgrade.php';
			}
		} if ( ! function_exists( 'dbDelta' ) ) {
			throw new RuntimeException( 'WordPress dbDelta is unavailable.' );
		} dbDelta( $sql ); }
	public function drop( string $table ): void {
		$this->assert_identifier( $table );
		if ( false === $this->database->query( "DROP TABLE IF EXISTS {$table}" ) ) {
			throw new RuntimeException( 'Unable to roll back receiver table.' ); } }
	public function exists( string $table ): bool {
		$this->assert_identifier( $table );
		$query = $this->database->prepare( 'SHOW TABLES LIKE %s', $this->database->esc_like( $table ) );
		return $table === $this->database->get_var( $query ); }
	public function begin(): void {
		$this->statement( 'START TRANSACTION' ); }
	public function commit(): void {
		$this->statement( 'COMMIT' ); }
	public function rollback(): void {
		$this->statement( 'ROLLBACK' ); }
	private function statement( string $sql ): void {
		if ( false === $this->database->query( $sql ) ) {
			throw new RuntimeException( 'Receiver transaction statement failed.' ); } }
	private function assert_identifier( string $value ): void {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $value ) ) {
			throw new RuntimeException( 'Unsafe receiver database identifier.' ); } }
}
