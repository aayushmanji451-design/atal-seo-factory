<?php
/** Task 03 receiver test doubles. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Support\Receiver;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Domain\Receiver\{AioseoAdapterInterface, ArticlePayload, AuditLoggerInterface, CourseCatalogInterface, FeaturedImageVerifierInterface, MutationResult, PostServiceInterface, Receipt, ReceiptStoreInterface, TransactionManagerInterface};
use Atal\DiplomaReceiver\Domain\Security\ClockInterface;
use Atal\DiplomaReceiver\Domain\Security\SecretProviderInterface;
use RuntimeException;
final class FixedClock implements ClockInterface {
	public function __construct( public int $timestamp = 2000000000 ) {} public function now(): int {
		return $this->timestamp; }
}
final class StaticSecret implements SecretProviderInterface {
	public function __construct( private readonly string $value = 'task-03-unit-test-secret-value-1234567890' ) {} public function secret(): string {
		return $this->value; }
}
final class TestCourseCatalog implements CourseCatalogInterface {
	public bool $valid = true;
	public function assert_valid(): void {
		if ( ! $this->valid ) {
			throw new RuntimeException( 'invalid catalog' );
		} } public function contains( string $course_key ): bool {
		return 'diploma_basic_health_care' === $course_key; }
}
final class InMemoryReceiptStore implements ReceiptStoreInterface {
	/** @var array<string,true> */ public array $nonces       = array();
	/** @var array<string,Receipt> */ public array $receipts  = array();
	/** @var array<string,string> */ public array $recoveries = array();
	public function nonce_exists( string $nonce_hash ): bool {
		return isset( $this->nonces[ $nonce_hash ] ); }
	public function receipt( string $idempotency_hash ): ?Receipt {
		return $this->receipts[ $idempotency_hash ] ?? null; }
	public function reserve( string $nonce_hash, string $idempotency_hash, string $request_hash, string $article_key ): void {
		$this->nonces[ $nonce_hash ]         = true;
		$this->receipts[ $idempotency_hash ] = new Receipt( $request_hash, array( 'article_key' => $article_key ) ); }
	/** @param array<string,mixed> $response @param array<string,mixed>|null $previous_state */
	public function complete( string $idempotency_hash, array $response, ?string $recovery_hash, ?array $previous_state, bool $created ): void {
		$request                             = $this->receipts[ $idempotency_hash ];
		$this->receipts[ $idempotency_hash ] = new Receipt( $request->request_hash(), $response, $previous_state, $created );
		if ( null !== $recovery_hash ) {
			$this->recoveries[ $recovery_hash ] = $idempotency_hash; } }
	public function recovery_receipt( string $recovery_hash, string $article_key ): ?Receipt {
		unset( $article_key );
		$id = $this->recoveries[ $recovery_hash ] ?? null;
		return null === $id ? null : ( $this->receipts[ $id ] ?? null ); }
	public function mark_recovered( string $recovery_hash ): void {
		unset( $this->recoveries[ $recovery_hash ] ); }
}
final class TestTransactions implements TransactionManagerInterface {
	public int $begins    = 0;
	public int $commits   = 0;
	public int $rollbacks = 0;
	public function begin(): void {
		++$this->begins;
	} public function commit(): void {
		++$this->commits;
	} public function rollback(): void {
		++$this->rollbacks; }
}
final class TestPosts implements PostServiceInterface {
	public int $writes     = 0;
	public int $recoveries = 0;
	public bool $fail      = false;
	public function upsert_draft( ArticlePayload $payload ): MutationResult {
		unset( $payload );
		if ( $this->fail ) {
			throw new RuntimeException( 'synthetic persistence failure with sensitive detail' );
		} ++$this->writes;
		return new MutationResult( 501, true, null );
	} public function recover( int $post_id, bool $created, ?array $previous_state ): void {
		unset( $post_id, $created, $previous_state );
		++$this->recoveries;
		$this->writes = 0; }
}
final class TestAioseo implements AioseoAdapterInterface {
	public function __construct( public bool $active = true ) {} public function detected(): bool {
		return $this->active;
	} public function version(): ?string {
		return $this->active ? '4.9.8' : null; }
}
final class TestImages implements FeaturedImageVerifierInterface {
	public bool $fail = false;
	public function verify( ?int $attachment_id ): void {
		unset( $attachment_id );
		if ( $this->fail ) {
			throw new ReceiverException( 'receiver_featured_image_invalid', 'Invalid image.', 422 ); } }
}
final class TestAudit implements AuditLoggerInterface {
	/** @var list<array<string,mixed>> */ public array $records = array();
	public function record( string $event, string $outcome, array $context = array() ): void {
		$this->records[] = array(
			'event'   => $event,
			'outcome' => $outcome,
			'context' => $context,
		);
	} public function encoded(): string {
		$value = json_encode( $this->records );
		return is_string( $value ) ? $value : ''; }
}
