<?php
/** Shared Task 03 receiver fixture. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Receiver;

use Atal\DiplomaReceiver\Application\Security\HmacAuthenticator;
use Atal\DiplomaReceiver\Domain\Security\RequestEnvelope;
use Atal\Tests\Support\Receiver\FixedClock;
use Atal\Tests\Support\Receiver\StaticSecret;
use PHPUnit\Framework\TestCase;
abstract class ReceiverTestCase extends TestCase {
	protected FixedClock $clock;
	protected HmacAuthenticator $authenticator;
	protected function setUp(): void {
		parent::setUp();
		$this->clock         = new FixedClock();
		$this->authenticator = new HmacAuthenticator( new StaticSecret(), $this->clock ); }
	/** @return array<string,mixed> */
	protected function payload(): array {
		return array(
			'schema_version'    => '1.0',
			'target_site'       => 'atal_diploma',
			'article_key'       => 'article_task03_unit_0001',
			'course_key'        => 'diploma_basic_health_care',
			'title'             => 'Task 03 receiver unit fixture',
			'slug'              => 'task-03-receiver-unit-fixture',
			'content'           => 'This development fixture is long enough for the strict receiver payload contract.',
			'excerpt'           => 'Task 03 receiver fixture excerpt.',
			'status'            => 'draft',
			'aioseo'            => array(
				'title'           => 'Task 03 receiver unit fixture',
				'description'     => str_repeat( 'A', 150 ),
				'focus_keyphrase' => 'receiver fixture',
			),
			'featured_image_id' => null,
		); }
	/** @param array<string,mixed> $payload */ protected function body( array $payload ): string {
		$body = json_encode( $payload, JSON_UNESCAPED_SLASHES );
		self::assertIsString( $body );
		return $body; }
	protected function signed( string $body, string $nonce = 'nonce_task03_unit_123456', string $idempotency = 'idem_task03_unit_123456', string $route = '/atal-diploma-receiver/v1/articles', ?int $timestamp = null ): RequestEnvelope {
		$time     = (string) ( $timestamp ?? $this->clock->timestamp );
		$unsigned = new RequestEnvelope( 'POST', $route, $body, $time, $nonce, $idempotency, '' );
		return new RequestEnvelope( 'POST', $route, $body, $time, $nonce, $idempotency, $this->authenticator->sign( $unsigned ) ); }
}
