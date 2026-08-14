<?php
/** Idempotency, cross-site, audit, and rollback tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Canary;

use Atal\Contracts\Value\TargetSite;
use Atal\DiplomaReceiver\Application\Security\HmacAuthenticator;
use Atal\DiplomaReceiver\Domain\Security\RequestEnvelope;
use Atal\SeoFactory\Application\Canary\HmacRequestSigner;
use Atal\Tests\Support\Receiver\FixedClock;
use Atal\Tests\Support\Receiver\StaticSecret;

final class CanaryCoordinatorTest extends CanaryTestCase {
	public function test_institute_canary_is_one_draft_and_idempotent(): void {
		$fixture = $this->coordinator();
		$first   = $fixture['coordinator']->run_institute( $this->json( TargetSite::INSTITUTE ) );
		$second  = $fixture['coordinator']->run_institute( $this->json( TargetSite::INSTITUTE ) );
		self::assertFalse( $first['idempotent'] );
		self::assertTrue( $second['idempotent'] );
		self::assertSame( 1, $fixture['posts']->writes );
		self::assertCount( 1, $fixture['posts']->posts );
		self::assertSame( 0, $first['live_domain_access_count'] );
	}

	public function test_diploma_canary_sends_once_and_records_aioseo_outcome(): void {
		$fixture = $this->coordinator();
		$first   = $fixture['coordinator']->run_diploma( $this->json( TargetSite::DIPLOMA, 601 ) );
		$second  = $fixture['coordinator']->run_diploma( $this->json( TargetSite::DIPLOMA, 601 ) );
		self::assertFalse( $first['idempotent'] );
		self::assertTrue( $second['idempotent'] );
		self::assertSame( 1, $fixture['client']->sends );
		self::assertSame( array( 'diplomanext.ataldiploma.com' ), $fixture['client']->contacted_hosts() );
		$payload = $first['payload'];
		self::assertIsArray( $payload );
		$verification = $payload['remote_verification'] ?? null;
		self::assertIsArray( $verification );
		self::assertSame( 'accepted', $verification['aioseo_payload'] ?? null );
		self::assertSame( 'accepted', $verification['aioseo_native'] ?? null );
		self::assertStringNotContainsString( 'recovery_token', (string) json_encode( $first ) );
	}

	public function test_verify_and_rollback_touch_only_two_canaries(): void {
		$fixture = $this->coordinator();
		$fixture['coordinator']->run_institute( $this->json( TargetSite::INSTITUTE ) );
		$fixture['coordinator']->run_diploma( $this->json( TargetSite::DIPLOMA, 601 ) );
		$verified = $fixture['coordinator']->verify();
		self::assertSame( 'PASS', $verified['status'] );
		self::assertSame( 0, $verified['live_domain_access_count'] );
		$rolled = $fixture['coordinator']->rollback();
		self::assertSame( 'PASS', $rolled['status'] );
		self::assertCount( 0, $fixture['posts']->posts );
		self::assertCount( 0, $fixture['client']->posts );
		self::assertSame( 1, $fixture['posts']->rollbacks );
		self::assertSame( 1, $fixture['client']->rollbacks );
	}

	public function test_explicit_run_recreates_both_drafts_after_rollback(): void {
		$fixture = $this->coordinator();
		$fixture['coordinator']->run_institute( $this->json( TargetSite::INSTITUTE ) );
		$fixture['coordinator']->run_diploma( $this->json( TargetSite::DIPLOMA, 601 ) );
		$fixture['coordinator']->rollback();
		$institute = $fixture['coordinator']->run_institute( $this->json( TargetSite::INSTITUTE ) );
		$diploma   = $fixture['coordinator']->run_diploma( $this->json( TargetSite::DIPLOMA, 601 ) );
		self::assertFalse( $institute['idempotent'] );
		self::assertFalse( $diploma['idempotent'] );
		self::assertCount( 1, $fixture['posts']->posts );
		self::assertCount( 1, $fixture['client']->posts );
		self::assertSame( 2, $fixture['posts']->writes );
		self::assertSame( 2, $fixture['client']->sends );
	}

	public function test_audit_never_contains_hmac_or_recovery_secret(): void {
		$fixture = $this->coordinator();
		$fixture['coordinator']->run_institute( $this->json( TargetSite::INSTITUTE ) );
		$fixture['coordinator']->run_diploma( $this->json( TargetSite::DIPLOMA, 601 ) );
		self::assertDoesNotMatchRegularExpression( '/secret|signature|authorization|recovery_token|password/i', $fixture['audit']->encoded() );
	}

	public function test_core_hmac_matches_receiver_contract(): void {
		$secret      = new StaticSecret();
		$clock       = new FixedClock();
		$receiver    = new HmacAuthenticator( $secret, $clock );
		$body        = '{"fixture":"task04"}';
		$route       = '/atal-diploma-receiver/v1/articles';
		$timestamp   = (string) $clock->timestamp;
		$nonce       = 'task04-nonce-123456789';
		$idempotency = 'task04-idempotency-123456789';
		$signature   = ( new HmacRequestSigner() )->sign( 'POST', $route, $timestamp, $nonce, $idempotency, $body, $secret->secret() );
		$envelope    = new RequestEnvelope( 'POST', $route, $body, $timestamp, $nonce, $idempotency, $signature );
		$receiver->authenticate( $envelope );
		self::assertSame( 64, strlen( $signature ) );
	}
}
