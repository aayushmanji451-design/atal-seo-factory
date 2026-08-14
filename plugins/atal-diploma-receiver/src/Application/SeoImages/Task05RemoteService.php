<?php
/** HMAC-authenticated receiver-side Task 05 actions. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\SeoImages;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Application\Security\HmacAuthenticator;
use Atal\DiplomaReceiver\Domain\Receiver\ReceiptStoreInterface;
use Atal\DiplomaReceiver\Domain\Receiver\TransactionManagerInterface;
use Atal\DiplomaReceiver\Domain\Security\RequestEnvelope;
use Atal\SeoImages\Application\AcceptanceCoordinator;
use Throwable;

final class Task05RemoteService {
	public function __construct( private readonly HmacAuthenticator $authenticator, private readonly ReceiptStoreInterface $receipts, private readonly AcceptanceCoordinator $coordinator, private readonly TransactionManagerInterface $transactions ) {}
	/**
	 * @param array<string,mixed> $payload Signed action payload.
	 * @return array<string,mixed>
	 */
	public function execute( RequestEnvelope $request, array $payload, string $action ): array {
		$this->authenticator->authenticate( $request );
		$expected = array(
			'action'         => $action,
			'post_id'        => 5704,
			'schema_version' => '1.0',
			'target_site'    => 'atal_diploma',
		);
		if ( $expected !== $payload || ! in_array( $action, array( 'run', 'verify', 'rollback' ), true ) ) {
			throw new ReceiverException( 'receiver_task05_invalid_payload', 'The Task 05 staging payload is invalid.', 422 ); }
		if ( $this->receipts->nonce_exists( $request->nonce_hash() ) ) {
			throw new ReceiverException( 'receiver_replay_detected', 'The signed nonce has already been used.', 409 ); }
		$existing = $this->receipts->receipt( $request->idempotency_hash() );
		if ( null !== $existing ) {
			if ( $existing->request_hash() !== $request->request_hash() ) {
				throw new ReceiverException( 'receiver_idempotency_conflict', 'The idempotency key was used with a different Task 05 payload.', 409 ); }
			$response                      = $existing->response();
			$response['idempotent_remote'] = true;
			return $response;
		}
		$key = 'task05_' . $action . '_atal_diploma';
		$this->transactions->begin();
		try {
			$this->receipts->reserve( $request->nonce_hash(), $request->idempotency_hash(), $request->request_hash(), $key );
			$response = match ( $action ) {
				'run' => $this->coordinator->run(), 'verify' => $this->coordinator->verify(), 'rollback' => $this->coordinator->rollback() };
			$response['idempotent_remote'] = false;
			$this->receipts->complete( $request->idempotency_hash(), $response, null, null, false );
			$this->transactions->commit();
			return $response;
		} catch ( ReceiverException $exception ) {
			$this->transactions->rollback();
			throw $exception;
		} catch ( Throwable $throwable ) {
			$this->transactions->rollback();
			throw new ReceiverException( 'receiver_task05_failed', 'The Task 05 receiver operation rolled back safely.', 500, array( 'exception' => $throwable::class ) );
		}
	}
}
