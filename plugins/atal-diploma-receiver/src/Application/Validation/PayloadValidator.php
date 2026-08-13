<?php
/** Strict receiver payload validation. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Validation;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Config\Identifiers;
use Atal\DiplomaReceiver\Domain\Receiver\ArticlePayload;
use Atal\DiplomaReceiver\Domain\Receiver\CourseCatalogInterface;
final class PayloadValidator {
	private const KEYS = array( 'schema_version', 'target_site', 'article_key', 'course_key', 'title', 'slug', 'content', 'excerpt', 'status', 'aioseo', 'featured_image_id' );
	public function __construct( private readonly CourseCatalogInterface $catalog ) {}
	/** @param array<string,mixed> $payload */
	public function validate_article( array $payload ): ArticlePayload {
		$keys = array_keys( $payload );
		sort( $keys );
		$expected = self::KEYS;
		sort( $expected );
		if ( $keys !== $expected ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'The payload must contain exactly the documented fields.', 422, array( 'field' => '$' ) ); }
		$strings = array();
		foreach ( array(
			'schema_version' => array( 1, 16 ),
			'target_site'    => array( 1, 64 ),
			'article_key'    => array( 16, 128 ),
			'course_key'     => array( 8, 191 ),
			'title'          => array( 5, 255 ),
			'slug'           => array( 3, 200 ),
			'content'        => array( 20, 200000 ),
			'excerpt'        => array( 1, 1000 ),
			'status'         => array( 1, 20 ),
		) as $field => $limits ) {
			$strings[ $field ] = $this->expect_string( $payload, $field, $limits[0], $limits[1] ); }
		if ( '1.0' !== $strings['schema_version'] ) {
			throw new ReceiverException( 'receiver_unsupported_schema', 'The payload schema version is not supported.', 422, array( 'field' => 'schema_version' ) ); }
		if ( Identifiers::TARGET_SITE !== $strings['target_site'] ) {
			throw new ReceiverException( 'receiver_wrong_site', 'The payload target site does not match this receiver.', 422, array( 'field' => 'target_site' ) ); }
		if ( 'draft' !== $strings['status'] ) {
			throw new ReceiverException( 'receiver_publish_forbidden', 'Task 03 accepts receiver-owned drafts only.', 422, array( 'field' => 'status' ) ); }
		if ( 1 !== preg_match( '/^article_[a-z0-9_]{8,120}$/', $strings['article_key'] ) ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'The article key format is invalid.', 422, array( 'field' => 'article_key' ) ); }
		if ( 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $strings['slug'] ) ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'The slug format is invalid.', 422, array( 'field' => 'slug' ) ); }
		$course_key = $strings['course_key'];
		if ( ! $this->catalog->contains( $course_key ) ) {
			throw new ReceiverException( 'receiver_unknown_course', 'The course key is not an active canonical Diploma identity.', 422, array( 'field' => 'course_key' ) ); }
		$aioseo = $payload['aioseo'];
		if ( ! is_array( $aioseo ) || array_is_list( $aioseo ) ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'The AIOSEO payload must be an object.', 422, array( 'field' => 'aioseo' ) ); }
		$aioseo_keys = array_keys( $aioseo );
		sort( $aioseo_keys );
		if ( array( 'description', 'focus_keyphrase', 'title' ) !== $aioseo_keys ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'The AIOSEO payload has unexpected or missing fields.', 422, array( 'field' => 'aioseo' ) ); }
		foreach ( array( 'title', 'description', 'focus_keyphrase' ) as $field ) {
			if ( ! is_string( $aioseo[ $field ] ) || '' === trim( $aioseo[ $field ] ) ) {
				throw new ReceiverException( 'receiver_invalid_payload', 'A required AIOSEO field is invalid.', 422, array( 'field' => 'aioseo.' . $field ) ); }
		}
		$description_length = strlen( $aioseo['description'] );
		if ( 140 > $description_length || 160 < $description_length ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'The meta description must be 140 to 160 characters.', 422, array( 'field' => 'aioseo.description' ) ); }
		$image_id = $payload['featured_image_id'];
		if ( null !== $image_id && ( ! is_int( $image_id ) || 1 > $image_id ) ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'The featured image ID must be null or a positive integer.', 422, array( 'field' => 'featured_image_id' ) ); }
		/** @var array{title:string,description:string,focus_keyphrase:string} $aioseo */
		return new ArticlePayload( $strings['article_key'], $course_key, $strings['title'], $strings['slug'], $strings['content'], $strings['excerpt'], $aioseo, $image_id );
	}
	/** @param array<string,mixed> $payload */
	private function expect_string( array $payload, string $field, int $minimum, int $maximum ): string {
		$value = $payload[ $field ] ?? null;
		if ( ! is_string( $value ) || $minimum > strlen( trim( $value ) ) || $maximum < strlen( $value ) ) {
			throw new ReceiverException( 'receiver_invalid_payload', 'A required payload field is invalid.', 422, array( 'field' => $field ) );
		} return $value; }
}
