<?php
/** Hashed replay/idempotency persistence. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\DiplomaReceiver\Domain\Receiver\Receipt;
use Atal\DiplomaReceiver\Domain\Receiver\ReceiptStoreInterface;
use Atal\DiplomaReceiver\Infrastructure\Database\TableNames;
use RuntimeException;
use wpdb;
final class WpdbReceiptStore implements ReceiptStoreInterface {
	public function __construct( private readonly wpdb $database, private readonly TableNames $tables ) {}
	public function nonce_exists( string $nonce_hash ): bool {
		$query = $this->database->prepare( "SELECT id FROM {$this->tables->receipts()} WHERE nonce_hash = %s LIMIT 1", $nonce_hash );
		return null !== $this->database->get_var( $query ); }
	public function receipt( string $idempotency_hash ): ?Receipt {
		$query = $this->database->prepare( "SELECT request_hash,response_json,previous_state_json,created_draft FROM {$this->tables->receipts()} WHERE idempotency_hash = %s AND status IN ('accepted','recovered') LIMIT 1", $idempotency_hash );
		return $this->row_to_receipt( $this->database->get_row( $query, ARRAY_A ) ); }
	public function reserve( string $nonce_hash, string $idempotency_hash, string $request_hash, string $article_key ): void {
		$now    = gmdate( 'Y-m-d H:i:s' );
		$result = $this->database->insert(
			$this->tables->receipts(),
			array(
				'idempotency_hash' => $idempotency_hash,
				'nonce_hash'       => $nonce_hash,
				'request_hash'     => $request_hash,
				'article_key'      => $article_key,
				'status'           => 'pending',
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $result ) {
			throw new RuntimeException( 'Unable to reserve receiver request.' ); } }
	/**
	 * @param array<string,mixed>      $response       Response data.
	 * @param array<string,mixed>|null $previous_state Prior state.
	 */
	public function complete( string $idempotency_hash, array $response, ?string $recovery_hash, ?array $previous_state, bool $created ): void {
		$data   = array(
			'status'              => 'accepted',
			'response_json'       => $this->encode( $response ),
			'recovery_hash'       => $recovery_hash,
			'previous_state_json' => null === $previous_state ? null : $this->encode( $previous_state ),
			'created_draft'       => $created ? 1 : 0,
			'updated_at'          => gmdate( 'Y-m-d H:i:s' ),
		);
		$result = $this->database->update( $this->tables->receipts(), $data, array( 'idempotency_hash' => $idempotency_hash ), array( '%s', '%s', '%s', '%s', '%d', '%s' ), array( '%s' ) );
		if ( false === $result ) {
			throw new RuntimeException( 'Unable to complete receiver request.' ); } }
	public function recovery_receipt( string $recovery_hash, string $article_key ): ?Receipt {
		$query = $this->database->prepare( "SELECT request_hash,response_json,previous_state_json,created_draft FROM {$this->tables->receipts()} WHERE recovery_hash = %s AND article_key = %s AND recovery_used = 0 AND status = 'accepted' LIMIT 1", $recovery_hash, $article_key );
		return $this->row_to_receipt( $this->database->get_row( $query, ARRAY_A ) ); }
	public function mark_recovered( string $recovery_hash ): void {
		$result = $this->database->update(
			$this->tables->receipts(),
			array(
				'recovery_used' => 1,
				'status'        => 'recovered',
				'updated_at'    => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'recovery_hash' => $recovery_hash ),
			array( '%d', '%s', '%s' ),
			array( '%s' )
		);
		if ( false === $result ) {
			throw new RuntimeException( 'Unable to mark recovery complete.' ); } }
	/** @param array<string,mixed>|object|null $row */
	private function row_to_receipt( array|object|null $row ): ?Receipt {
		if ( ! is_array( $row ) ) {
			return null;
		} $response = $this->object_map( json_decode( is_string( $row['response_json'] ?? null ) ? $row['response_json'] : '', true ) );
		if ( null === $response ) {
			return null;
		} $previous = null;
		if ( is_string( $row['previous_state_json'] ?? null ) && '' !== $row['previous_state_json'] ) {
			$previous = $this->object_map( json_decode( $row['previous_state_json'], true ) );
		} $created = $row['created_draft'] ?? 0;
		return new Receipt( is_string( $row['request_hash'] ?? null ) ? $row['request_hash'] : '', $response, $previous, is_numeric( $created ) && 1 === (int) $created ); }
	/** @param array<string,mixed> $value */
	private function encode( array $value ): string {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			throw new RuntimeException( 'Unable to encode receiver state.' );
		} return $encoded; }
	/** @return array<string,mixed>|null */
	private function object_map( mixed $value ): ?array {
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			return null;
		} $result = array();
		foreach ( $value as $key => $item ) {
			if ( ! is_string( $key ) ) {
				return null;
			} $result[ $key ] = $item;
		} return $result; }
}
