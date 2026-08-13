<?php
/** Receiver-owned draft mutation result. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Receiver;

final class MutationResult {
	/** @param array<string,mixed>|null $previous_state */
	public function __construct( private readonly int $post_id, private readonly bool $created, private readonly ?array $previous_state ) {}
	public function post_id(): int {
		return $this->post_id; }
	public function created(): bool {
		return $this->created; }
	/** @return array<string,mixed>|null */
	public function previous_state(): ?array {
		return $this->previous_state; }
}
