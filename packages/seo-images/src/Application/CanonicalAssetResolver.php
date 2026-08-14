<?php
/** Task 01-backed image and course resolver. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Application;

use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\SeoImages\Domain\AcceptanceFixture;
use Atal\SeoImages\Domain\ResolvedAsset;
use Atal\SeoImages\Exception\PipelineException;

final class CanonicalAssetResolver {
	public function __construct( private readonly string $master_directory, private readonly string $schema_directory, private readonly KnowledgeValidator $validator ) {}

	public function resolve( AcceptanceFixture $fixture ): ResolvedAsset {
		$package = KnowledgePackage::from_directory( $this->master_directory );
		$report  = $this->validator->validate( $package, $this->schema_directory );
		if ( ! $report->is_valid() ) {
			throw new PipelineException( 'Task 05 refused invalid canonical knowledge.' );
		}
		$document_name = 'atal_institute' === $fixture->target_site() ? 'institute_courses' : 'diploma_courses';
		$course_doc    = $package->document( $document_name );
		$course        = $this->find_record( $course_doc['courses'] ?? null, 'course_key', $fixture->course_key() );
		if ( null === $course || $fixture->target_site() !== ( $course['target_site'] ?? null ) ) {
			throw new PipelineException( 'The controlled Task 05 course identity is absent from the validated catalog.' );
		}
		$course_name = $this->required_string( $course, 'canonical_name' );
		$site        = $course_doc['site_identity'] ?? null;
		if ( ! is_array( $site ) || array_is_list( $site ) ) {
			throw new PipelineException( 'The canonical site identity is malformed.' );
		}
		$site_name = $this->required_string( JsonValue::object( $site, 'site_identity' ), 'canonical_name' );
		$assets    = $package->document( 'image_assets' );
		$record    = $this->find_record( $assets['records'] ?? null, 'course_key', $fixture->course_key() );
		if ( null === $record || $fixture->target_site() !== ( $record['target_site'] ?? null ) || 'APPROVED_GENERATION_SPEC' !== ( $record['status'] ?? null ) ) {
			throw new PipelineException( 'No safe canonical image generation record exists for the controlled fixture.' );
		}

		return new ResolvedAsset( $course_name, $site_name, $this->required_string( $record, 'asset_key' ), $this->required_string( $record, 'template_key' ), $this->required_string( $record, 'safe_subject' ), true );
	}

	/** @return array<string,mixed>|null */
	private function find_record( mixed $records, string $key, string $value ): ?array {
		if ( ! is_array( $records ) || ! array_is_list( $records ) ) {
			return null; }
		foreach ( $records as $record ) {
			if ( is_array( $record ) && ! array_is_list( $record ) && ( $record[ $key ] ?? null ) === $value ) {
				/** @var array<string,mixed> $record */ return $record;
			}
		}
		return null;
	}
	/** @param array<string,mixed> $record */
	private function required_string( array $record, string $key ): string {
		$value = $record[ $key ] ?? null;
		if ( ! is_string( $value ) || '' === $value ) {
			throw new PipelineException( 'Canonical image data is incomplete.' ); }
		return $value;
	}
}
