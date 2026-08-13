<?php
/** Replay, idempotency, and transaction tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Receiver;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Application\Receiver\ArticleReceiver;
use Atal\DiplomaReceiver\Application\Validation\PayloadValidator;
use Atal\Tests\Support\Receiver\{InMemoryReceiptStore, TestAioseo, TestAudit, TestCourseCatalog, TestImages, TestPosts, TestTransactions};
final class ArticleReceiverTest extends ReceiverTestCase {
	public function test_valid_request_creates_one_draft_and_returns_recovery_contract(): void {
		[$receiver, $receipts, $transactions, $posts, $audit] = $this->receiver();
		unset( $receipts, $audit );
		$response = $receiver->receive( $this->signed( $this->body( $this->payload() ) ), $this->payload() );
		self::assertSame( 'accepted', $response['status'] );
		self::assertSame( 'draft', $response['post_status'] );
		$verification = $response['verification'] ?? null;
		self::assertIsArray( $verification );
		self::assertSame( 'accepted', $verification['aioseo_native'] ?? null );
		self::assertSame( 1, $posts->writes );
		self::assertSame( 1, $transactions->commits );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $this->recovery_token( $response ) ); }
	public function test_same_idempotency_key_does_not_duplicate(): void {
		[$receiver, $receipts, $transactions, $posts, $audit] = $this->receiver();
		unset( $receipts, $transactions, $audit );
		$body = $this->body( $this->payload() );
		$receiver->receive( $this->signed( $body ), $this->payload() );
		$response = $receiver->receive( $this->signed( $body, 'nonce_task03_second_123456' ), $this->payload() );
		self::assertTrue( $response['idempotent_replay'] );
		self::assertSame( 1, $posts->writes ); }
	public function test_same_nonce_is_replay(): void {
		[$receiver] = $this->receiver();
		$body       = $this->body( $this->payload() );
		$request    = $this->signed( $body );
		$receiver->receive( $request, $this->payload() );
		try {
			$receiver->receive( $request, $this->payload() );
			self::fail( 'Replay should fail.' );
		} catch ( ReceiverException $exception ) {
			self::assertSame( 'receiver_replay_detected', $exception->error_code() ); } }
	public function test_idempotency_key_with_different_payload_conflicts(): void {
		[$receiver] = $this->receiver();
		$payload    = $this->payload();
		$body       = $this->body( $payload );
		$receiver->receive( $this->signed( $body ), $payload );
		$payload['title'] = 'A different valid title';
		$changed          = $this->body( $payload );
		try {
			$receiver->receive( $this->signed( $changed, 'nonce_task03_changed_123456' ), $payload );
			self::fail( 'Conflict should fail.' );
		} catch ( ReceiverException $exception ) {
			self::assertSame( 'receiver_idempotency_conflict', $exception->error_code() ); } }
	public function test_persistence_failure_rolls_back_and_does_not_log_secret(): void {
		[$receiver, $receipts, $transactions, $posts, $audit] = $this->receiver();
		unset( $receipts );
		$posts->fail = true;
		try {
			$receiver->receive( $this->signed( $this->body( $this->payload() ) ), $this->payload() );
			self::fail( 'Persistence should fail.' );
		} catch ( ReceiverException $exception ) {
			self::assertSame( 'receiver_persistence_failed', $exception->error_code() );
			self::assertSame( 1, $transactions->rollbacks );
			self::assertStringNotContainsString( 'secret', $audit->encoded() );
			self::assertStringNotContainsString( 'do-not-log', $audit->encoded() ); } }
	/** @return array{ArticleReceiver,InMemoryReceiptStore,TestTransactions,TestPosts,TestAudit} */ private function receiver(): array {
		$receipts     = new InMemoryReceiptStore();
		$transactions = new TestTransactions();
		$posts        = new TestPosts();
		$audit        = new TestAudit();
		return array( new ArticleReceiver( $this->authenticator, new PayloadValidator( new TestCourseCatalog() ), $receipts, $transactions, $posts, new TestAioseo(), new TestImages(), $audit ), $receipts, $transactions, $posts, $audit ); }
	/** @param array<string,mixed> $response */ private function recovery_token( array $response ): string {
		$recovery = $response['recovery'] ?? null;
		self::assertIsArray( $recovery );
		$token = $recovery['token'] ?? null;
		self::assertIsString( $token );
		return $token; }
}
