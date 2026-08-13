<?php
/** Authenticated, idempotent draft receiver. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Receiver;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Application\Security\HmacAuthenticator;
use Atal\DiplomaReceiver\Application\Validation\PayloadValidator;
use Atal\DiplomaReceiver\Domain\Receiver\{AioseoAdapterInterface, AuditLoggerInterface, FeaturedImageVerifierInterface, PostServiceInterface, ReceiptStoreInterface, TransactionManagerInterface};
use Atal\DiplomaReceiver\Domain\Security\RequestEnvelope;
use Throwable;
final class ArticleReceiver {
	public function __construct( private readonly HmacAuthenticator $authenticator, private readonly PayloadValidator $validator, private readonly ReceiptStoreInterface $receipts, private readonly TransactionManagerInterface $transactions, private readonly PostServiceInterface $posts, private readonly AioseoAdapterInterface $aioseo, private readonly FeaturedImageVerifierInterface $images, private readonly AuditLoggerInterface $audit ) {}
	/**
	 * @param array<string,mixed> $payload Validated JSON object candidate.
	 * @return array<string,mixed>
	 */
	public function receive( RequestEnvelope $request, array $payload ): array {
		$this->authenticator->authenticate( $request );
		$article = $this->validator->validate_article( $payload );
		if ( ! $this->aioseo->detected() ) {
			throw new ReceiverException( 'receiver_aioseo_unavailable', 'All in One SEO is not active.', 503 ); }
		$this->images->verify( $article->featured_image_id() );
		if ( $this->receipts->nonce_exists( $request->nonce_hash() ) ) {
			$this->audit->record( 'request_rejected', 'replay', array( 'article_key' => $article->article_key() ) );
			throw new ReceiverException( 'receiver_replay_detected', 'The signed nonce has already been used.', 409 ); }
		$existing = $this->receipts->receipt( $request->idempotency_hash() );
		if ( null !== $existing ) {
			if ( $existing->request_hash() !== $request->request_hash() ) {
				throw new ReceiverException( 'receiver_idempotency_conflict', 'The idempotency key was used with a different payload.', 409 );
			} $response                    = $existing->response();
			$response['idempotent_replay'] = true;
			$this->audit->record( 'request_duplicate', 'accepted', array( 'article_key' => $article->article_key() ) );
			return $response; }
		$this->transactions->begin();
		try {
			$this->receipts->reserve( $request->nonce_hash(), $request->idempotency_hash(), $request->request_hash(), $article->article_key() );
			$mutation       = $this->posts->upsert_draft( $article );
			$recovery_token = bin2hex( random_bytes( 32 ) );
			$response       = array(
				'status'            => 'accepted',
				'article_key'       => $article->article_key(),
				'post_id'           => $mutation->post_id(),
				'post_status'       => 'draft',
				'created'           => $mutation->created(),
				'idempotent_replay' => false,
			);
			$this->receipts->complete( $request->idempotency_hash(), $response, hash( 'sha256', $recovery_token ), $mutation->previous_state(), $mutation->created() );
			$this->audit->record(
				'draft_received',
				'accepted',
				array(
					'article_key' => $article->article_key(),
					'post_id'     => $mutation->post_id(),
				)
			);
			$this->transactions->commit();
			$response['recovery'] = array(
				'route' => '/articles/rollback',
				'token' => $recovery_token,
			);
			return $response;
		} catch ( ReceiverException $exception ) {
			$this->transactions->rollback();
			throw $exception;
		} catch ( Throwable $throwable ) {
			$this->transactions->rollback();
			$this->audit->record( 'request_failed', 'rolled_back', array( 'article_key' => $article->article_key() ) );
			throw new ReceiverException( 'receiver_persistence_failed', 'The receiver transaction was rolled back safely.', 500 ); }
	}
}
