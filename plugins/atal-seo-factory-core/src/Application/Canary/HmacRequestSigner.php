<?php
/** Task 03-compatible HMAC request signer. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Application\Canary;

final class HmacRequestSigner {
	public function sign( string $method, string $route, string $timestamp, string $nonce, string $idempotency_key, string $body, string $secret ): string {
		if ( 32 > strlen( $secret ) ) {
			throw new CanaryException( 'Diploma receiver authentication is not configured.' );
		}
		$canonical = implode( "\n", array( strtoupper( $method ), $route, $timestamp, $nonce, $idempotency_key, hash( 'sha256', $body ) ) );
		return hash_hmac( 'sha256', $canonical, $secret );
	}
}
