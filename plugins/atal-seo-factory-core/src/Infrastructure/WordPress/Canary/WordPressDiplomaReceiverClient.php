<?php
/** Approved-host-only signed Diploma receiver client. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Infrastructure\WordPress\Canary;

use Atal\SeoFactory\Application\Canary\CanaryException;
use Atal\SeoFactory\Application\Canary\HmacRequestSigner;
use Atal\SeoFactory\Domain\Canary\CanaryArticle;
use Atal\SeoFactory\Domain\Canary\DiplomaReceiverClientInterface;
use WP_Error;

final class WordPressDiplomaReceiverClient implements DiplomaReceiverClientInterface {
	public const TARGET_HOST                      = 'diplomanext.ataldiploma.com';
	private const BASE_URL                        = 'https://diplomanext.ataldiploma.com/wp-json';
	private const ARTICLE_ROUTE                   = '/atal-diploma-receiver/v1/articles';
	private const ROLLBACK_ROUTE                  = '/atal-diploma-receiver/v1/articles/rollback';
	private const HEALTH_ROUTE                    = '/atal-diploma-receiver/v1/health';
	/** @var list<string> */ private array $hosts = array();

	public function __construct( private readonly HmacRequestSigner $signer ) {}

	public function send( CanaryArticle $article ): array {
		$this->assert_health();
		return $this->post( self::ARTICLE_ROUTE, $article->receiver_payload(), 'task04-send-' . hash( 'sha256', $article->article_key() ) );
	}

	public function rollback( string $article_key, string $recovery_token ): array {
		return $this->post(
			self::ROLLBACK_ROUTE,
			array(
				'article_key'    => $article_key,
				'recovery_token' => $recovery_token,
				'schema_version' => '1.0',
				'target_site'    => 'atal_diploma',
			),
			'task04-rollback-' . hash( 'sha256', $article_key )
		);
	}

	public function contacted_hosts(): array {
		return $this->hosts; }

	private function assert_health(): void {
		$url = $this->url( self::HEALTH_ROUTE );
		$this->record_host( $url );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'            => 10,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
			)
		);
		$data     = $this->response( $response );
		if ( true !== ( $data['hostname_valid'] ?? false ) || true !== ( $data['search_indexing_disabled'] ?? false ) || true !== ( $data['aioseo_detected'] ?? false ) || '4.9.8' !== ( $data['aioseo_version'] ?? null ) || false !== ( $data['old_atal_connector_active'] ?? true ) ) {
			throw new CanaryException( 'Diploma staging health did not satisfy the Task 04 safety preflight.' );
		}
	}

	/**
	 * @param array<string,mixed> $payload Receiver payload.
	 *
	 * @return array<string,mixed>
	 */
	private function post( string $route, array $payload, string $idempotency_key ): array {
		$body = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $body ) {
			throw new CanaryException( 'Unable to encode the bounded Diploma request.' );
		}
		$secret = defined( WordPressCanaryRuntimeGuard::SECRET_CONSTANT ) ? constant( WordPressCanaryRuntimeGuard::SECRET_CONSTANT ) : null;
		if ( ! is_string( $secret ) ) {
			throw new CanaryException( 'Diploma receiver authentication is not configured.' );
		}
		$timestamp = (string) time();
		$nonce     = 'task04-' . bin2hex( random_bytes( 16 ) );
		$signature = $this->signer->sign( 'POST', $route, $timestamp, $nonce, $idempotency_key, $body, $secret );
		$url       = $this->url( $route );
		$this->record_host( $url );
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'            => 15,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'body'               => $body,
				'headers'            => array(
					'Content-Type'           => 'application/json',
					'X-ATAL-Timestamp'       => $timestamp,
					'X-ATAL-Nonce'           => $nonce,
					'X-ATAL-Idempotency-Key' => $idempotency_key,
					'X-ATAL-Signature'       => $signature,
				),
			)
		);
		return $this->response( $response );
	}

	private function url( string $route ): string {
		return self::BASE_URL . $route; }

	private function record_host( string $url ): void {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( self::TARGET_HOST !== $host ) {
			throw new CanaryException( 'Task 04 blocked a non-staging outbound host.' );
		}
		$this->hosts[] = self::TARGET_HOST;
	}

	/**
	 * @param array<string,mixed>|WP_Error $response WordPress HTTP response.
	 *
	 * @return array<string,mixed>
	 */
	private function response( array|WP_Error $response ): array {
		if ( $response instanceof WP_Error ) {
			throw new CanaryException( 'The Diploma staging request failed safely.' );
		}
		$status  = wp_remote_retrieve_response_code( $response );
		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			throw new CanaryException( 'The Diploma receiver returned a malformed response.' );
		}
		if ( 200 > $status || 299 < $status ) {
			$code = is_string( $decoded['code'] ?? null ) ? $decoded['code'] : 'receiver_error';
			throw new CanaryException( 'Diploma receiver rejected the canary: ' . $code );
		}
		/** @var array<string,mixed> $decoded */
		return $decoded;
	}
}
