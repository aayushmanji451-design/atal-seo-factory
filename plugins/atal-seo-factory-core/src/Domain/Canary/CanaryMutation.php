<?php
/** One local canary draft mutation. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Domain\Canary;

final class CanaryMutation {
	/** @param array<string,mixed>|null $previous_state Prior owned state. */
	public function __construct( private readonly int $post_id, private readonly bool $created, private readonly ?array $previous_state ) {}
	public function post_id(): int {
		return $this->post_id; }
	public function created(): bool {
		return $this->created; }
	/** @return array<string,mixed>|null */ public function previous_state(): ?array {
		return $this->previous_state; }
}
