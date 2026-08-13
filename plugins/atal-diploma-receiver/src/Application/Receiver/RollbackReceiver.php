<?php
/** Authenticated remote recovery contract. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Receiver;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Application\Security\HmacAuthenticator;
use Atal\DiplomaReceiver\Config\Identifiers;
use Atal\DiplomaReceiver\Domain\Receiver\{AuditLoggerInterface, PostServiceInterface, ReceiptStoreInterface, TransactionManagerInterface};
use Atal\DiplomaReceiver\Domain\Security\RequestEnvelope;
use Throwable;
final class RollbackReceiver {
	public function __construct( private readonly HmacAuthenticator $authenticator, private readonly ReceiptStoreInterface $receipts, private readonly TransactionManagerInterface $transactions, private readonly PostServiceInterface $posts, private readonly AuditLoggerInterface $audit ) {}
	/**
	 * @param array<string,mixed> $payload Recovery payload.
	 * @return array<string,mixed>
	 */
	public function recover( RequestEnvelope $request, array $payload ): array {
		$this->authenticator->authenticate( $request );
		$validated   = $this->validate( $payload );
		$article_key = $validated['article_key'];
		if ( $this->receipts->nonce_exists( $request->nonce_hash() ) ) {
			throw new ReceiverException( 'receiver_replay_detected', 'The signed nonce has already been used.', 409 ); }
		$duplicate = $this->receipts->receipt( $request->idempotency_hash() );
		if ( null !== $duplicate ) {
			if ( $duplicate->request_hash() !== $request->request_hash() ) {
				throw new ReceiverException( 'receiver_idempotency_conflict', 'The idempotency key was used with a different payload.', 409 );
			} $response                    = $duplicate->response();
			$response['idempotent_replay'] = true;
			return $response; }
		$recovery_hash = hash( 'sha256', $validated['recovery_token'] );
		$source        = $this->receipts->recovery_receipt( $recovery_hash, $article_key );
		if ( null === $source ) {
			throw new ReceiverException( 'receiver_recovery_not_found', 'The recovery token is invalid, expired, or already used.', 404 ); }
		$post_id = $source->response()['post_id'] ?? null;
		if ( ! is_int( $post_id ) ) {
			throw new ReceiverException( 'receiver_recovery_not_found', 'The recovery record is incomplete.', 404 ); }
		$this->transactions->begin();
		try {
			$this->receipts->reserve( $request->nonce_hash(), $request->idempotency_hash(), $request->request_hash(), $article_key );
			$this->posts->recover( $post_id, $source->created(), $source->previous_state() );
			$this->receipts->mark_recovered( $recovery_hash );
			$response = array(
				'status'            => 'recovered',
				'article_key'       => $article_key,
				'post_id'           => $post_id,
				'idempotent_replay' => false,
			);
			$this->receipts->complete( $request->idempotency_hash(), $response, null, null, false );
			$this->audit->record(
				'draft_recovered',
				'accepted',
				array(
					'article_key' => $article_key,
					'post_id'     => $post_id,
				)
			);
			$this->transactions->commit();
			return $response; } catch ( ReceiverException $exception ) {
			$this->transactions->rollback();
			throw $exception;
			} catch ( Throwable $throwable ) {
				$this->transactions->rollback();
				throw new ReceiverException( 'receiver_recovery_failed', 'The recovery transaction was rolled back safely.', 500 ); }
	}
	/**
	 * @param array<string,mixed> $payload Recovery payload.
	 * @return array{article_key:string,recovery_token:string}
	 */
	private function validate( array $payload ): array {
		$keys = array_keys( $payload );
		sort( $keys );
		if ( array( 'article_key', 'recovery_token', 'schema_version', 'target_site' ) !== $keys ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'The recovery payload must contain exactly the documented fields.', 422 );
		} if ( '1.0' !== $payload['schema_version'] || Identifiers::TARGET_SITE !== $payload['target_site'] ) {
			throw new ReceiverException( 'receiver_wrong_site', 'The recovery target does not match this receiver.', 422 );
		} if ( ! is_string( $payload['article_key'] ) || 1 !== preg_match( '/^article_[a-z0-9_]{8,120}$/', $payload['article_key'] ) ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'The recovery article key is invalid.', 422 );
		} if ( ! is_string( $payload['recovery_token'] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $payload['recovery_token'] ) ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'The recovery token format is invalid.', 422 );
		} return array(
			'article_key'    => $payload['article_key'],
			'recovery_token' => $payload['recovery_token'],
		); }
}
