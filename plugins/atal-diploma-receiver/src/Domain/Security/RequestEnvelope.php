<?php
/** Immutable signed request envelope. @package AtalDiplomaReceiver */

declare(strict_types=1);

namespace Atal\DiplomaReceiver\Domain\Security;

final class RequestEnvelope {
	public function __construct(
		private readonly string $method,
		private readonly string $route,
		private readonly string $body,
		private readonly string $timestamp,
		private readonly string $nonce,
		private readonly string $idempotency_key,
		private readonly string $signature
	) {
	}

	public function timestamp(): string {
		return $this->timestamp; }
	public function nonce(): string {
		return $this->nonce; }
	public function idempotency_key(): string {
		return $this->idempotency_key; }
	public function signature(): string {
		return $this->signature; }
	public function request_hash(): string {
		return hash( 'sha256', $this->body ); }
	public function nonce_hash(): string {
		return hash( 'sha256', $this->nonce ); }
	public function idempotency_hash(): string {
		return hash( 'sha256', $this->idempotency_key ); }

	public function canonical_string(): string {
		return implode(
			"\n",
			array(
				strtoupper( $this->method ),
				$this->route,
				$this->timestamp,
				$this->nonce,
				$this->idempotency_key,
				$this->request_hash(),
			)
		);
	}
}
