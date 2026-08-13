<?php
/** Stored idempotency receipt. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Receiver;

final class Receipt {
	/**
	 * @param array<string,mixed>      $response       Response data.
	 * @param array<string,mixed>|null $previous_state Prior state.
	 */
	public function __construct( private readonly string $request_hash, private readonly array $response, private readonly ?array $previous_state = null, private readonly bool $created = false ) {}
	public function request_hash(): string {
		return $this->request_hash; }
	/** @return array<string,mixed> */ public function response(): array {
		return $this->response; }
	/** @return array<string,mixed>|null */ public function previous_state(): ?array {
		return $this->previous_state; }
	public function created(): bool {
		return $this->created; }
}
