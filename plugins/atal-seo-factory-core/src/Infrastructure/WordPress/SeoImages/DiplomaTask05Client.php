<?php
/** Approved-host-only Task 05 Diploma acceptance client. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Infrastructure\WordPress\SeoImages;

use Atal\SeoFactory\Application\Canary\HmacRequestSigner;
use Atal\SeoFactory\Infrastructure\WordPress\Canary\WordPressCanaryRuntimeGuard;
use Atal\SeoImages\Exception\PipelineException;
use WP_Error;

final class DiplomaTask05Client {
	private const HOST                            = 'diplomanext.ataldiploma.com';
	private const BASE                            = 'https://diplomanext.ataldiploma.com/wp-json';
	private const PREFIX                          = '/atal-diploma-receiver/v1/task-05/';
	private const HEALTH                          = '/atal-diploma-receiver/v1/health';
	/** @var list<string> */ private array $hosts = array();
	public function __construct( private readonly HmacRequestSigner $signer ) {}
	/** @return array<string,mixed> */ public function run(): array {
		return $this->request( 'run' ); }
	/** @return array<string,mixed> */ public function verify(): array {
		return $this->request( 'verify' ); }
	/** @return array<string,mixed> */ public function rollback(): array {
		return $this->request( 'rollback' ); }
	/** @return list<string> */ public function contacted_hosts(): array {
		return $this->hosts; }
	/** @return array<string,mixed> */
	private function request( string $action ): array {
		$this->assert_health();
		$route   = self::PREFIX . $action;
		$payload = array(
			'action'         => $action,
			'post_id'        => 5704,
			'schema_version' => '1.0',
			'target_site'    => 'atal_diploma',
		);
		$body    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
		if ( false === $body ) {
			throw new PipelineException( 'Task 05 could not encode the Diploma request.' ); }
		$secret = WordPressCanaryRuntimeGuard::shared_secret();
		if ( 32 > strlen( $secret ) ) {
			throw new PipelineException( 'Diploma receiver authentication is not configured.' ); }
		$timestamp   = (string) time();
		$nonce       = 'task05-' . bin2hex( random_bytes( 16 ) );
		$idempotency = 'task05-' . $action . '-' . bin2hex( random_bytes( 16 ) );
		$signature   = $this->signer->sign( 'POST', $route, $timestamp, $nonce, $idempotency, $body, $secret );
		$url         = $this->url( $route );
		$response    = wp_safe_remote_post(
			$url,
			array(
				'timeout'            => 30,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'body'               => $body,
				'headers'            => array(
					'Content-Type'           => 'application/json',
					'X-ATAL-Timestamp'       => $timestamp,
					'X-ATAL-Nonce'           => $nonce,
					'X-ATAL-Idempotency-Key' => $idempotency,
					'X-ATAL-Signature'       => $signature,
				),
			)
		);
		return $this->response( $response );
	}
	private function assert_health(): void {
		$data = $this->response(
			wp_safe_remote_get(
				$this->url( self::HEALTH ),
				array(
					'timeout'            => 10,
					'redirection'        => 0,
					'reject_unsafe_urls' => true,
				)
			)
		);
		if ( true !== ( $data['hostname_valid'] ?? false ) || true !== ( $data['search_indexing_disabled'] ?? false ) || true !== ( $data['aioseo_detected'] ?? false ) || '4.9.8' !== ( $data['aioseo_version'] ?? null ) || false !== ( $data['old_atal_connector_active'] ?? true ) ) {
			throw new PipelineException( 'Diploma staging failed the Task 05 health preflight.' ); } }
	private function url( string $route ): string {
		$url  = self::BASE . $route;
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( self::HOST !== $host ) {
			throw new PipelineException( 'Task 05 blocked a non-staging outbound host.' );
		} $this->hosts[] = self::HOST;
		return $url; }
	/**
	 * @param array<string,mixed>|WP_Error $response WordPress HTTP response.
	 * @return array<string,mixed>
	 */
	private function response( array|WP_Error $response ): array {
		if ( $response instanceof WP_Error ) {
			throw new PipelineException( 'The Diploma Task 05 request failed safely.' );
		} $status = wp_remote_retrieve_response_code( $response );
		$decoded  = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			throw new PipelineException( 'The Diploma Task 05 response is malformed.' );
		} if ( 200 > $status || 299 < $status ) {
			$code = is_string( $decoded['code'] ?? null ) ? $decoded['code'] : 'receiver_error';
			throw new PipelineException( 'Diploma Task 05 was rejected: ' . $code );
		} $result = array();
		foreach ( $decoded as $key => $item ) {
			if ( ! is_string( $key ) ) {
				throw new PipelineException( 'The Diploma Task 05 response has an invalid key.' );
			} $result[ $key ] = $item;
		} return $result; }
}
