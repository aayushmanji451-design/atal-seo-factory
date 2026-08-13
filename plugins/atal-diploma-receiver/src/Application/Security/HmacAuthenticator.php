<?php
/** HMAC SHA-256 authentication. @package AtalDiplomaReceiver */

declare(strict_types=1);

namespace Atal\DiplomaReceiver\Application\Security;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Domain\Security\ClockInterface;
use Atal\DiplomaReceiver\Domain\Security\RequestEnvelope;
use Atal\DiplomaReceiver\Domain\Security\SecretProviderInterface;

final class HmacAuthenticator {
	public const TOLERANCE_SECONDS = 300;

	public function __construct(
		private readonly SecretProviderInterface $secrets,
		private readonly ClockInterface $clock
	) {
	}

	public function authenticate( RequestEnvelope $request ): void {
		if ( '' === $request->timestamp() || '' === $request->nonce() || '' === $request->idempotency_key() || '' === $request->signature() ) {
			throw new ReceiverException( 'receiver_missing_auth', 'Required receiver authentication headers are missing.', 401 );
		}
		if ( 1 !== preg_match( '/^\d{10}$/', $request->timestamp() ) ) {
			throw new ReceiverException( 'receiver_invalid_timestamp', 'The request timestamp is invalid.', 401 );
		}
		if ( self::TOLERANCE_SECONDS < abs( $this->clock->now() - (int) $request->timestamp() ) ) {
			throw new ReceiverException( 'receiver_expired_timestamp', 'The request timestamp is outside the allowed window.', 401 );
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9._:-]{16,128}$/', $request->nonce() ) ) {
			throw new ReceiverException( 'receiver_invalid_nonce', 'The request nonce format is invalid.', 400 );
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9._:-]{16,128}$/', $request->idempotency_key() ) ) {
			throw new ReceiverException( 'receiver_invalid_idempotency_key', 'The idempotency key format is invalid.', 400 );
		}

		$secret = $this->secrets->secret();
		if ( 32 > strlen( $secret ) ) {
			throw new ReceiverException( 'receiver_not_configured', 'Receiver authentication is not configured.', 503 );
		}
		$expected = hash_hmac( 'sha256', $request->canonical_string(), $secret );
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $request->signature() ) || ! hash_equals( $expected, strtolower( $request->signature() ) ) ) {
			throw new ReceiverException( 'receiver_invalid_signature', 'The request signature is invalid.', 401 );
		}
	}

	public function sign( RequestEnvelope $request ): string {
		return hash_hmac( 'sha256', $request->canonical_string(), $this->secrets->secret() );
	}
}
