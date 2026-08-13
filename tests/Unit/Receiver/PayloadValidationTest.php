<?php
/** Strict payload and canonical identity tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Receiver;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Application\Validation\PayloadValidator;
use Atal\DiplomaReceiver\Rest\JsonPayloadDecoder;
use Atal\Tests\Support\Receiver\TestCourseCatalog;
final class PayloadValidationTest extends ReceiverTestCase {
	public function test_valid_payload_maps_to_immutable_article(): void {
		$article = ( new PayloadValidator( new TestCourseCatalog() ) )->validate_article( $this->payload() );
		self::assertSame( 'diploma_basic_health_care', $article->course_key() );
		self::assertSame( 'article_task03_unit_0001', $article->article_key() ); }
	/**
	 * @dataProvider invalid_payloads
	 *
	 * @param mixed $value Replacement value.
	 */
	public function test_invalid_payloads_have_exact_codes( string $field, mixed $value, string $expected ): void {
		$payload           = $this->payload();
		$payload[ $field ] = $value;
		try {
			( new PayloadValidator( new TestCourseCatalog() ) )->validate_article( $payload );
			self::fail( 'Payload should fail.' );
		} catch ( ReceiverException $exception ) {
			self::assertSame( $expected, $exception->error_code() ); } }
	/** @return iterable<string,array{string,mixed,string}> */ public static function invalid_payloads(): iterable {
		yield 'wrong site' => array( 'target_site', 'atal_institute', 'receiver_wrong_site' );
		yield 'unknown course' => array( 'course_key', 'diploma_invented', 'receiver_unknown_course' );
		yield 'publish forbidden' => array( 'status', 'publish', 'receiver_publish_forbidden' );
		yield 'extra field' => array( 'unexpected_field', 'no', 'receiver_invalid_payload' ); }
	public function test_malformed_json_is_rejected_exactly(): void {
		$this->expectException( ReceiverException::class );
		try {
			( new JsonPayloadDecoder() )->decode( '{' );
		} catch ( ReceiverException $exception ) {
			self::assertSame( 'receiver_malformed_json', $exception->error_code() );
			throw $exception; } }
}
