<?php
/** Receipt persistence boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Receiver;

interface ReceiptStoreInterface {
	public function nonce_exists( string $nonce_hash ): bool;
	public function receipt( string $idempotency_hash ): ?Receipt;
	public function reserve( string $nonce_hash, string $idempotency_hash, string $request_hash, string $article_key ): void;
	/**
	 * @param array<string,mixed>      $response       Stored response.
	 * @param array<string,mixed>|null $previous_state Prior state.
	 */
	public function complete( string $idempotency_hash, array $response, ?string $recovery_hash, ?array $previous_state, bool $created ): void;
	public function recovery_receipt( string $recovery_hash, string $article_key ): ?Receipt;
	public function mark_recovered( string $recovery_hash ): void;
}
