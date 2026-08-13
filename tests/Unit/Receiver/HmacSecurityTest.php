<?php
/** Receiver HMAC/timestamp security tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Receiver;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Domain\Security\RequestEnvelope;
final class HmacSecurityTest extends ReceiverTestCase {
	public function test_valid_hmac_authenticates(): void {
		$request = $this->signed( $this->body( $this->payload() ) );
		$this->authenticator->authenticate( $request );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $request->signature() ); }
	/** @dataProvider invalid_envelopes */
	public function test_exact_authentication_errors( string $kind, string $code ): void {
		$body    = $this->body( $this->payload() );
		$valid   = $this->signed( $body );
		$request = match ( $kind ) {
			'missing'=>new RequestEnvelope( 'POST', '/atal-diploma-receiver/v1/articles', $body, (string) $this->clock->timestamp, 'nonce_task03_unit_123456', 'idem_task03_unit_123456', '' ), 'tampered'=>new RequestEnvelope( 'POST', '/atal-diploma-receiver/v1/articles', $body . ' ', (string) $this->clock->timestamp, 'nonce_task03_unit_123456', 'idem_task03_unit_123456', $valid->signature() ), 'expired'=>$this->signed( $body, 'nonce_task03_expired_123456', 'idem_task03_expired_123456', '/atal-diploma-receiver/v1/articles', $this->clock->timestamp - 301 ), default=>throw new \InvalidArgumentException( 'Unknown fixture.' )};
		try {
			$this->authenticator->authenticate( $request );
			self::fail( 'Authentication should fail.' );
		} catch ( ReceiverException $exception ) {
			self::assertSame( $code, $exception->error_code() ); } }
	/** @return iterable<string,array{string,string}> */ public static function invalid_envelopes(): iterable {
		yield 'unsigned' => array( 'missing', 'receiver_missing_auth' );
		yield 'tampered body' => array( 'tampered', 'receiver_invalid_signature' );
		yield 'expired' => array( 'expired', 'receiver_expired_timestamp' ); }
	public function test_signature_is_lowercase_sha256_without_secret_disclosure(): void {
		$signature = $this->signed( $this->body( $this->payload() ) )->signature();
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $signature );
		self::assertStringNotContainsString( 'task-03-unit-test-secret', $signature ); }
}
