<?php
/** Signed Task 05 receiver route security tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\SeoImages;

use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Application\Security\HmacAuthenticator;
use Atal\DiplomaReceiver\Application\SeoImages\Task05RemoteService;
use Atal\DiplomaReceiver\Domain\Security\RequestEnvelope;
use Atal\SeoImages\Application\AcceptanceCoordinator;
use Atal\SeoImages\Application\CanonicalAssetResolver;
use Atal\SeoImages\Domain\AcceptanceFixture;
use Atal\Tests\Support\Receiver\FixedClock;
use Atal\Tests\Support\Receiver\InMemoryReceiptStore;
use Atal\Tests\Support\Receiver\StaticSecret;
use Atal\Tests\Support\Receiver\TestTransactions;
use Atal\Tests\Support\SeoImages\FakeAuditLogger;
use Atal\Tests\Support\SeoImages\FakeFixtureRepository;
use Atal\Tests\Support\SeoImages\FakeImageManager;
use Atal\Tests\Support\SeoImages\FakeRuntimeGuard;
use Atal\Tests\Support\SeoImages\FakeSeoAdapter;
use Atal\Tests\Support\SeoImages\FakeStateStore;
use PHPUnit\Framework\TestCase;

final class Task05RemoteSecurityTest extends TestCase {
	public function test_hmac_replay_and_remote_idempotency_contract(): void {
		$root         = dirname( __DIR__, 3 );
		$fixture      = new AcceptanceFixture( 'atal_diploma', 'diplomanext.ataldiploma.com', 5704, 'article_task04_atal_diploma_basic_health_care_course_overview_v1', 'diploma_basic_health_care', 'course_overview', 'Diploma in Basic Health Care: Duration and Fees | Atal Diploma', 'Explore the Diploma in Basic Health Care at Atal Diploma, with verified duration, fee, eligibility, learning focus, and approved course information.', 'Diploma in Basic Health Care' );
		$coordinator  = new AcceptanceCoordinator( $fixture, new CanonicalAssetResolver( $root . '/data/master', $root . '/data/schemas', KnowledgeValidator::create_default() ), new FakeRuntimeGuard(), new FakeFixtureRepository( 5700 ), new FakeSeoAdapter(), new FakeImageManager(), new FakeStateStore(), new FakeAuditLogger(), '0.5.0-dev', array( 5700 ) );
		$auth         = new HmacAuthenticator( new StaticSecret(), new FixedClock() );
		$receipts     = new InMemoryReceiptStore();
		$transactions = new TestTransactions();
		$service      = new Task05RemoteService( $auth, $receipts, $coordinator, $transactions );
		$payload      = array(
			'action'         => 'run',
			'post_id'        => 5704,
			'schema_version' => '1.0',
			'target_site'    => 'atal_diploma',
		);
		$body         = (string) json_encode( $payload, JSON_UNESCAPED_SLASHES );
		$first        = $this->signed( $auth, $body, 'task05-nonce-first-0001', 'task05-idempotency-0001' );
		$result       = $service->execute( $first, $payload, 'run' );
		self::assertSame( 'PASS', $result['status'] );
		self::assertFalse( $result['idempotent_remote'] );
		try {
			$service->execute( $first, $payload, 'run' );
			self::fail( 'Replay must fail.' );
		} catch ( ReceiverException $exception ) {
			self::assertSame( 'receiver_replay_detected', $exception->error_code() ); }
		$duplicate = $service->execute( $this->signed( $auth, $body, 'task05-nonce-second-0002', 'task05-idempotency-0001' ), $payload, 'run' );
		self::assertTrue( $duplicate['idempotent_remote'] );
		self::assertSame( 1, $transactions->begins );
		self::assertSame( 1, $transactions->commits );
		self::assertSame( 0, $transactions->rollbacks );
	}

	private function signed( HmacAuthenticator $auth, string $body, string $nonce, string $idempotency ): RequestEnvelope {
		$unsigned = new RequestEnvelope( 'POST', '/atal-diploma-receiver/v1/task-05/run', $body, '2000000000', $nonce, $idempotency, '' );
		return new RequestEnvelope( 'POST', '/atal-diploma-receiver/v1/task-05/run', $body, '2000000000', $nonce, $idempotency, $auth->sign( $unsigned ) );
	}
}
