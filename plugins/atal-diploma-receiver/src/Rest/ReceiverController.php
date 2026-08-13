<?php
/** Versioned REST receiver controller. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Rest;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Application\Health\HealthDataProvider;
use Atal\DiplomaReceiver\Application\Receiver\ArticleReceiver;
use Atal\DiplomaReceiver\Application\Receiver\RollbackReceiver;
use Atal\DiplomaReceiver\Application\Security\HmacAuthenticator;
use Atal\DiplomaReceiver\Config\Identifiers;
use Atal\DiplomaReceiver\Domain\Security\RequestEnvelope;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;
final class ReceiverController {
	public function __construct( private readonly ArticleReceiver $articles, private readonly RollbackReceiver $rollbacks, private readonly HmacAuthenticator $authenticator, private readonly JsonPayloadDecoder $decoder, private readonly HealthDataProvider $health ) {}
	public function register(): void {
		register_rest_route(
			Identifiers::REST_NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			Identifiers::REST_NAMESPACE,
			'/articles',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'receive' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			Identifiers::REST_NAMESPACE,
			'/articles/rollback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rollback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			Identifiers::REST_NAMESPACE,
			'/contract-test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'contract_test' ),
				'permission_callback' => '__return_true',
			)
		);
	}
	public function health( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( $this->health->snapshot(), 200 ); }
	public function receive( WP_REST_Request $request ): WP_REST_Response {
		return $this->execute( $request, array( $this, 'receive_payload' ) ); }
	public function rollback( WP_REST_Request $request ): WP_REST_Response {
		return $this->execute( $request, array( $this, 'rollback_payload' ) ); }
	public function contract_test( WP_REST_Request $request ): WP_REST_Response {
		try {
			$payload  = $this->decoder->decode( $request->get_body() );
			$envelope = $this->envelope( $request );
			$this->authenticator->authenticate( $envelope );
			if ( array(
				'fixture_mode'   => 'isolated',
				'schema_version' => '1.0',
				'target_site'    => Identifiers::TARGET_SITE,
			) !== $payload ) {
				throw new ReceiverException( 'receiver_invalid_contract_fixture', 'The contract fixture must be isolated and target Atal Diploma.', 422 );
			} return new WP_REST_Response(
				array(
					'status'            => 'accepted',
					'fixture_mode'      => 'isolated',
					'writes'            => 0,
					'outbound_requests' => 0,
				),
				200
			);
		} catch ( ReceiverException $exception ) {
			return $this->error( $exception );
		} catch ( Throwable $throwable ) {
			return $this->error( new ReceiverException( 'receiver_internal_error', 'The receiver failed safely.', 500 ) ); }
	}
	/** @param callable(RequestEnvelope,array<string,mixed>):array<string,mixed> $operation */
	private function execute( WP_REST_Request $request, callable $operation ): WP_REST_Response {
		try {
			$payload = $this->decoder->decode( $request->get_body() );
			return new WP_REST_Response( $operation( $this->envelope( $request ), $payload ), 200 );
		} catch ( ReceiverException $exception ) {
			return $this->error( $exception );
		} catch ( Throwable $throwable ) {
			return $this->error( new ReceiverException( 'receiver_internal_error', 'The receiver failed safely.', 500 ) ); } }
	/**
	 * @param array<string,mixed> $payload Article payload.
	 * @return array<string,mixed>
	 */
	private function receive_payload( RequestEnvelope $envelope, array $payload ): array {
		return $this->articles->receive( $envelope, $payload ); }
	/**
	 * @param array<string,mixed> $payload Rollback payload.
	 * @return array<string,mixed>
	 */
	private function rollback_payload( RequestEnvelope $envelope, array $payload ): array {
		return $this->rollbacks->recover( $envelope, $payload ); }
	private function envelope( WP_REST_Request $request ): RequestEnvelope {
		return new RequestEnvelope( $request->get_method(), $request->get_route(), $request->get_body(), $request->get_header( 'x-atal-timestamp' ), $request->get_header( 'x-atal-nonce' ), $request->get_header( 'x-atal-idempotency-key' ), strtolower( $request->get_header( 'x-atal-signature' ) ) ); }
	private function error( ReceiverException $exception ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'code'    => $exception->error_code(),
				'message' => $exception->getMessage(),
				'data'    => array(
					'status'  => $exception->http_status(),
					'details' => $exception->details(),
				),
			),
			$exception->http_status()
		); }
}
