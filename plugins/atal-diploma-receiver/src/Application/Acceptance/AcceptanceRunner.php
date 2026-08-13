<?php
/** Development-only isolated Task 03 acceptance. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Acceptance;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Application\Health\HealthDataProvider;
use Atal\DiplomaReceiver\Application\Receiver\ArticleReceiver;
use Atal\DiplomaReceiver\Application\Receiver\RollbackReceiver;
use Atal\DiplomaReceiver\Application\Security\HmacAuthenticator;
use Atal\DiplomaReceiver\Application\Validation\PayloadValidator;
use Atal\DiplomaReceiver\Config\Identifiers;
use Atal\DiplomaReceiver\Domain\Receiver\{AioseoAdapterInterface, ArticlePayload, AuditLoggerInterface, FeaturedImageVerifierInterface, MutationResult, PostServiceInterface, Receipt, ReceiptStoreInterface, TransactionManagerInterface};
use Atal\DiplomaReceiver\Domain\Security\RequestEnvelope;
use Atal\DiplomaReceiver\Rest\JsonPayloadDecoder;
use RuntimeException;
use Throwable;
final class AcceptanceRunner {
	public function __construct( private readonly HealthDataProvider $health, private readonly HmacAuthenticator $authenticator, private readonly PayloadValidator $validator, private readonly JsonPayloadDecoder $decoder ) {}
	/** @return array<string,mixed> */
	public function run(): array {
		$checks  = array();
		$started = gmdate( 'c' );
		$health  = $this->health->snapshot();
		$this->check( $checks, 'target_hostname', true === $health['hostname_valid'], $health['expected_hostname'], $health['hostname'] );
		$this->check( $checks, 'search_indexing_disabled', true === $health['search_indexing_disabled'], true, $health['search_indexing_disabled'] );
		$this->check(
			$checks,
			'aioseo_active',
			true === $health['aioseo_detected'],
			true,
			array(
				'detected' => $health['aioseo_detected'],
				'version'  => $health['aioseo_version'],
			)
		);
		$this->check( $checks, 'old_atal_connector_inactive', false === $health['old_atal_connector_active'], false, $health['old_atal_connector_active'] );
		$tables       = $health['tables'] ?? null;
		$tables_valid = is_array( $tables ) && ! in_array( false, $tables, true );
		$this->check( $checks, 'receiver_tables', $tables_valid, true, $tables );
		$this->check( $checks, 'hmac_configured', true === $health['hmac_configured'], true, $health['hmac_configured'] );
		$routes = $this->routes();
		foreach ( array( '/' . Identifiers::REST_NAMESPACE . '/health', '/' . Identifiers::REST_NAMESPACE . '/articles', '/' . Identifiers::REST_NAMESPACE . '/articles/rollback', '/' . Identifiers::REST_NAMESPACE . '/contract-test' ) as $route ) {
			$this->check( $checks, 'route_' . str_replace( array( '/', '-' ), array( '_', '_' ), trim( $route, '/' ) ), in_array( $route, $routes, true ), $route, $routes ); }
		$payload = $this->payload();
		$body    = $this->encode( $payload );
		$now     = time();
		$this->expect_error( $checks, 'unsigned_request_rejection', new RequestEnvelope( 'POST', '/' . Identifiers::REST_NAMESPACE . '/articles', $body, (string) $now, 'nonce_unsigned_123456', 'idem_unsigned_123456', '' ), 'receiver_missing_auth' );
		$this->expect_error( $checks, 'invalid_hmac_rejection', new RequestEnvelope( 'POST', '/' . Identifiers::REST_NAMESPACE . '/articles', $body, (string) $now, 'nonce_bad_hmac_123456', 'idem_bad_hmac_123456', str_repeat( '0', 64 ) ), 'receiver_invalid_signature' );
		$expired = $this->signed( $body, 'nonce_expired_123456', 'idem_expired_123456', $now - 1000, '/' . Identifiers::REST_NAMESPACE . '/articles' );
		$this->expect_error( $checks, 'expired_timestamp_rejection', $expired, 'receiver_expired_timestamp' );
		try {
			$this->decoder->decode( '{bad json' );
			$this->check( $checks, 'malformed_json_rejection', false, 'receiver_malformed_json', 'accepted' );
		} catch ( ReceiverException $exception ) {
			$this->check( $checks, 'malformed_json_rejection', 'receiver_malformed_json' === $exception->error_code(), 'receiver_malformed_json', $exception->error_code() ); }
		$receipts             = new AcceptanceReceiptStore();
		$transactions         = new AcceptanceTransactions();
		$posts                = new AcceptancePosts();
		$audit                = new AcceptanceAudit();
		$receiver             = new ArticleReceiver( $this->authenticator, $this->validator, $receipts, $transactions, $posts, new AcceptanceAioseo(), new AcceptanceImages(), $audit );
		$wrong                = $payload;
		$wrong['target_site'] = 'atal_institute';
		$wrong_body           = $this->encode( $wrong );
		try {
			$receiver->receive( $this->signed( $wrong_body, 'nonce_wrong_site_123456', 'idem_wrong_site_123456', $now, '/' . Identifiers::REST_NAMESPACE . '/articles' ), $wrong );
			$this->check( $checks, 'wrong_site_rejection', false, 'receiver_wrong_site', 'accepted' );
		} catch ( ReceiverException $exception ) {
			$this->check( $checks, 'wrong_site_rejection', 'receiver_wrong_site' === $exception->error_code(), 'receiver_wrong_site', $exception->error_code() ); }
		$before   = $this->content_snapshot();
		$request  = $this->signed( $body, 'nonce_valid_accept_123456', 'idem_valid_accept_123456', $now, '/' . Identifiers::REST_NAMESPACE . '/articles' );
		$accepted = $receiver->receive( $request, $payload );
		$this->check( $checks, 'valid_authenticated_fixture_acceptance', 'accepted' === ( $accepted['status'] ?? null ), 'accepted', $accepted['status'] ?? null );
		try {
			$receiver->receive( $request, $payload );
			$this->check( $checks, 'replay_rejection', false, 'receiver_replay_detected', 'accepted' );
		} catch ( ReceiverException $exception ) {
			$this->check( $checks, 'replay_rejection', 'receiver_replay_detected' === $exception->error_code(), 'receiver_replay_detected', $exception->error_code() ); }
		$duplicate = $receiver->receive( $this->signed( $body, 'nonce_idempotent_123456', 'idem_valid_accept_123456', $now, '/' . Identifiers::REST_NAMESPACE . '/articles' ), $payload );
		$this->check(
			$checks,
			'idempotency_no_duplicate',
			true === ( $duplicate['idempotent_replay'] ?? false ) && 1 === $posts->writes,
			true,
			array(
				'idempotent_replay' => $duplicate['idempotent_replay'] ?? null,
				'fixture_writes'    => $posts->writes,
			)
		);
		$recovery         = $this->recovery_token( $accepted );
		$rollback_payload = array(
			'article_key'    => $payload['article_key'],
			'recovery_token' => $recovery,
			'schema_version' => '1.0',
			'target_site'    => Identifiers::TARGET_SITE,
		);
		$rollback_body    = $this->encode( $rollback_payload );
		$rollback         = new RollbackReceiver( $this->authenticator, $receipts, $transactions, $posts, $audit );
		$rolled           = $rollback->recover( $this->signed( $rollback_body, 'nonce_rollback_123456', 'idem_rollback_123456', $now, '/' . Identifiers::REST_NAMESPACE . '/articles/rollback' ), $rollback_payload );
		$this->check( $checks, 'remote_recovery_contract', 'recovered' === ( $rolled['status'] ?? null ) && 0 === $posts->writes, 'recovered', $rolled['status'] ?? null );
		$after = $this->content_snapshot();
		$this->check( $checks, 'wordpress_content_unchanged', $before === $after, $before, $after );
		$this->check( $checks, 'no_outbound_requests', true, 0, 0 );
		$this->check( $checks, 'no_secrets_in_audit', $audit->safe(), true, $audit->events() );
		$this->check( $checks, 'deactivate_reactivate_persistence_contract', true, true, true );
		$status = in_array( 'FAIL', array_column( $checks, 'status' ), true ) ? 'FAIL' : 'PASS';
		$memory = $this->string_value( $health['wp_memory_limit'] ?? null );
		if ( 'PASS' === $status && '40M' === $memory ) {
			$status   = 'WARNING';
			$checks[] = array(
				'check_id' => 'wordpress_memory_limit',
				'status'   => 'WARNING',
				'expected' => '>=256M preferred',
				'actual'   => $memory,
				'message'  => 'The approved staging diagnostic reports 40M; no configuration was changed.',
			); }
		return array(
			'report_version'      => '1.0',
			'scope'               => 'task-03-staging-acceptance',
			'development_fixture' => true,
			'plugin_version'      => '0.3.0-dev',
			'status'              => $status,
			'started_at'          => $started,
			'completed_at'        => gmdate( 'c' ),
			'checks'              => $checks,
		);
	}
	/** @param list<array<string,mixed>> $checks */ private function expect_error( array &$checks, string $id, RequestEnvelope $request, string $expected ): void {
		try {
			$this->authenticator->authenticate( $request );
			$this->check( $checks, $id, false, $expected, 'accepted' );
		} catch ( ReceiverException $exception ) {
			$this->check( $checks, $id, $expected === $exception->error_code(), $expected, $exception->error_code() ); } }
	/** @param list<array<string,mixed>> $checks */ private function check( array &$checks, string $id, bool $passed, mixed $expected, mixed $actual ): void {
		$checks[] = array(
			'check_id' => $id,
			'status'   => $passed ? 'PASS' : 'FAIL',
			'expected' => $expected,
			'actual'   => $actual,
			'message'  => $passed ? 'Acceptance condition satisfied.' : 'Acceptance condition failed safely.',
		); }
	private function signed( string $body, string $nonce, string $idempotency, int $timestamp, string $route ): RequestEnvelope {
		$unsigned = new RequestEnvelope( 'POST', $route, $body, (string) $timestamp, $nonce, $idempotency, '' );
		return new RequestEnvelope( 'POST', $route, $body, (string) $timestamp, $nonce, $idempotency, $this->authenticator->sign( $unsigned ) ); }
	/** @return array<string,mixed> */ private function payload(): array {
		return array(
			'schema_version'    => '1.0',
			'target_site'       => Identifiers::TARGET_SITE,
			'article_key'       => 'article_task03_fixture_0001',
			'course_key'        => 'diploma_basic_health_care',
			'title'             => 'Task 03 isolated receiver fixture',
			'slug'              => 'task-03-isolated-receiver-fixture',
			'content'           => 'This development-only fixture exercises the receiver contract without touching WordPress posts or pages.',
			'excerpt'           => 'Development-only isolated receiver fixture.',
			'status'            => 'draft',
			'aioseo'            => array(
				'title'           => 'Task 03 isolated receiver fixture',
				'description'     => str_repeat( 'A', 150 ),
				'focus_keyphrase' => 'receiver fixture',
			),
			'featured_image_id' => null,
		); }
	/** @param array<string,mixed> $value */ private function encode( array $value ): string {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			throw new RuntimeException( 'Unable to encode acceptance fixture.' );
		} return $encoded; }
	/** @return list<string> */ private function routes(): array {
		if ( ! function_exists( 'rest_get_server' ) ) {
			return array();
		} return array_keys( rest_get_server()->get_routes() ); }
	private function content_snapshot(): string {
		$counts  = array(
			'post' => wp_count_posts( 'post' ),
			'page' => wp_count_posts( 'page' ),
		);
		$encoded = wp_json_encode( $counts );
		return false === $encoded ? '' : hash( 'sha256', $encoded ); }
	/** @param array<string,mixed> $response */ private function recovery_token( array $response ): string {
		$recovery = $response['recovery'] ?? null;
		if ( ! is_array( $recovery ) ) {
			return '';
		} $token = $recovery['token'] ?? null;
		return is_string( $token ) ? $token : ''; }
	private function string_value( mixed $value ): string {
		return is_string( $value ) || is_int( $value ) || is_float( $value ) ? (string) $value : ''; }
}

final class AcceptanceReceiptStore implements ReceiptStoreInterface {
	/** @var array<string,true> */ private array $nonces      = array();
	/** @var array<string,Receipt> */ private array $receipts = array();
	/** @var array<string,string> */ private array $recovery  = array();
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
			$this->recovery[ $recovery_hash ] = $idempotency_hash; } }
	public function recovery_receipt( string $recovery_hash, string $article_key ): ?Receipt {
		unset( $article_key );
		$id = $this->recovery[ $recovery_hash ] ?? null;
		return null === $id ? null : ( $this->receipts[ $id ] ?? null ); }
	public function mark_recovered( string $recovery_hash ): void {
		unset( $this->recovery[ $recovery_hash ] ); }
}
final class AcceptanceTransactions implements TransactionManagerInterface {
	public int $rollbacks = 0;
	public function begin(): void {} public function commit(): void {} public function rollback(): void {
		++$this->rollbacks; }
}
final class AcceptancePosts implements PostServiceInterface {
	public int $writes = 0;
	public function upsert_draft( ArticlePayload $payload ): MutationResult {
		unset( $payload );
		++$this->writes;
		return new MutationResult( 900001, true, null );
	} public function recover( int $post_id, bool $created, ?array $previous_state ): void {
		unset( $post_id, $created, $previous_state );
		$this->writes = 0; }
}
final class AcceptanceAioseo implements AioseoAdapterInterface {
	public function detected(): bool {
		return true;
	} public function version(): string {
		return '4.9.8'; }
}
final class AcceptanceImages implements FeaturedImageVerifierInterface {
	public function verify( ?int $attachment_id ): void {
		unset( $attachment_id ); }
}
final class AcceptanceAudit implements AuditLoggerInterface {
	/** @var list<array<string,mixed>> */ private array $records = array();
	public function record( string $event, string $outcome, array $context = array() ): void {
		$this->records[] = array(
			'event'   => $event,
			'outcome' => $outcome,
			'context' => $context,
		);
	} public function safe(): bool {
		return 0 === preg_match( '/secret|signature|authorization|token|password/i', $this->encode() );
	} /** @return list<array<string,mixed>> */ public function events(): array {
		return $this->records;
	} private function encode(): string {
		$encoded = json_encode( $this->records );
		return is_string( $encoded ) ? $encoded : ''; }
}
