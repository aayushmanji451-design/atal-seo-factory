<?php
/** Task 01-backed Diploma catalog. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Validation;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Domain\Receiver\CourseCatalogInterface;
use Throwable;
final class CanonicalDiplomaCatalog implements CourseCatalogInterface {
	/** @var array<string,true>|null */ private ?array $keys = null;
	public function __construct( private readonly string $master_directory, private readonly string $schema_directory, private readonly KnowledgeValidator $validator ) {}
	public function assert_valid(): void {
		$this->load(); }
	public function contains( string $course_key ): bool {
		$this->load();
		return isset( $this->keys[ $course_key ] ); }
	private function load(): void {
		if ( null !== $this->keys ) {
			return; }
		try {
			$package = KnowledgePackage::from_directory( $this->master_directory );
			$report  = $this->validator->validate( $package, $this->schema_directory );
			if ( ! $report->is_valid() ) {
				throw new ReceiverException( 'receiver_canonical_knowledge_invalid', 'The bundled canonical knowledge package is invalid.', 503 ); }
			$keys = array();
			foreach ( JsonValue::object_list_field( $package->document( 'diploma_courses' ), 'courses' ) as $course ) {
				if ( 'ACTIVE_CANONICAL' === JsonValue::string_field( $course, 'course_status' ) ) {
					$keys[ JsonValue::string_field( $course, 'course_key' ) ] = true; }
			}
			if ( 14 !== count( $keys ) ) {
				throw new ReceiverException( 'receiver_canonical_knowledge_invalid', 'The canonical Diploma catalog must contain 14 active identities.', 503 ); }
			$this->keys = $keys;
		} catch ( ReceiverException $exception ) {
			throw $exception;
		} catch ( Throwable $throwable ) {
			throw new ReceiverException( 'receiver_canonical_knowledge_invalid', 'The bundled canonical knowledge package could not be verified.', 503 ); }
	}
}
