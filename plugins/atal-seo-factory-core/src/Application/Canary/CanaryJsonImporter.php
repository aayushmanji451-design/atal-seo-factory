<?php
/** Strict one-object Task 04 JSON importer. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Application\Canary;

use Atal\Contracts\Value\TargetSite;
use Atal\SeoFactory\Domain\Canary\CanaryRequest;
use JsonException;

final class CanaryJsonImporter {
	private const KEYS = array( 'schema_version', 'mode', 'target_site', 'course_key', 'intent_key', 'option_key', 'featured_image_id' );
	/** @var array<string,array{course_key:string,option_key:?string}> */
	private const ALLOWED = array(
		TargetSite::INSTITUTE => array(
			'course_key' => 'institute_general_duty_assistant',
			'option_key' => 'certificate_general_duty_assistant',
		),
		TargetSite::DIPLOMA   => array(
			'course_key' => 'diploma_basic_health_care',
			'option_key' => null,
		),
	);

	public function import( string $json, string $expected_site ): CanaryRequest {
		try {
			$value = json_decode( $json, true, 32, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			throw new CanaryException( 'Task 04 input must be one valid JSON object.', 0, $exception );
		}
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			throw new CanaryException( 'Task 04 imports exactly one JSON article request; arrays and batches are forbidden.' );
		}
		$keys = array_keys( $value );
		sort( $keys );
		$expected_keys = self::KEYS;
		sort( $expected_keys );
		if ( $keys !== $expected_keys ) {
			throw new CanaryException( 'The canary JSON contains unexpected or missing fields.' );
		}
		if ( '1.0' !== ( $value['schema_version'] ?? null ) || 'CANARY_DEVELOPMENT' !== ( $value['mode'] ?? null ) ) {
			throw new CanaryException( 'Only the versioned CANARY_DEVELOPMENT request is accepted.' );
		}
		if ( ! isset( self::ALLOWED[ $expected_site ] ) || ( $value['target_site'] ?? null ) !== $expected_site ) {
			throw new CanaryException( 'The canary target site is not the selected staging flow.' );
		}
		$allowed = self::ALLOWED[ $expected_site ];
		if ( ( $value['course_key'] ?? null ) !== $allowed['course_key'] || 'course_overview' !== ( $value['intent_key'] ?? null ) || ( $value['option_key'] ?? null ) !== $allowed['option_key'] ) {
			throw new CanaryException( 'The request does not match the locked Task 04 course, intent, or option.' );
		}
		$image_id = $value['featured_image_id'] ?? null;
		if ( ! is_int( $image_id ) || 1 > $image_id ) {
			throw new CanaryException( 'featured_image_id must identify one existing image attachment on the target staging site.' );
		}
		return new CanaryRequest( $expected_site, $allowed['course_key'], 'course_overview', $allowed['option_key'], $image_id );
	}

	public function template( string $target_site ): string {
		if ( ! isset( self::ALLOWED[ $target_site ] ) ) {
			throw new CanaryException( 'Unsupported canary template target.' );
		}
		$allowed = self::ALLOWED[ $target_site ];
		return (string) wp_json_encode(
			array(
				'schema_version'    => '1.0',
				'mode'              => 'CANARY_DEVELOPMENT',
				'target_site'       => $target_site,
				'course_key'        => $allowed['course_key'],
				'intent_key'        => 'course_overview',
				'option_key'        => $allowed['option_key'],
				'featured_image_id' => 0,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
	}
}
