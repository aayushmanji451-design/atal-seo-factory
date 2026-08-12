<?php
/**
 * Schema catalog validator.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

use Atal\Contracts\Data\KnowledgePackage;
use JsonException;
use RuntimeException;

/**
 * Applies each repository JSON Schema to its canonical master document.
 */
final class SchemaCatalogValidator {

	/**
	 * Schema-to-document mapping.
	 *
	 * @var array<string, list<string>>
	 */
	private const SCHEMA_MAP = array(
		'course.schema.json'        => array( 'institute_courses', 'diploma_courses' ),
		'syllabus.schema.json'      => array( 'syllabus' ),
		'course-url.schema.json'    => array( 'course_urls' ),
		'image-asset.schema.json'   => array( 'image_assets' ),
		'search-intent.schema.json' => array( 'intents' ),
		'internal-link.schema.json' => array( 'internal_links' ),
		'blocked-claim.schema.json' => array( 'blocked_claims' ),
		'test-fixture.schema.json'  => array( 'test_fixtures' ),
	);

	/**
	 * Create a schema catalog validator.
	 *
	 * @param JsonSchemaValidator $schema_validator JSON Schema engine.
	 */
	public function __construct( private readonly JsonSchemaValidator $schema_validator ) {
	}

	/**
	 * Validate every mapped master document.
	 *
	 * @param KnowledgePackage $package          Canonical package.
	 * @param string           $schema_directory Schema directory.
	 *
	 * @return array{issues:list<ValidationIssue>,passed:int,total:int,schemas:int}
	 */
	public function validate( KnowledgePackage $package, string $schema_directory ): array {
		$issues = array();
		$passed = 0;
		$total  = 0;

		foreach ( self::SCHEMA_MAP as $schema_file => $document_names ) {
			$schema = $this->load_schema( rtrim( $schema_directory, '/\\' ) . DIRECTORY_SEPARATOR . $schema_file );

			foreach ( $document_names as $document_name ) {
				++$total;
				$document_issues = $this->schema_validator->validate(
					$package->document( $document_name ),
					$schema,
					$document_name
				);

				if ( array() === $document_issues ) {
					++$passed;
				} else {
					$issues = array_merge( $issues, $document_issues );
				}
			}
		}

		return array(
			'issues'  => $issues,
			'passed'  => $passed,
			'total'   => $total,
			'schemas' => count( self::SCHEMA_MAP ),
		);
	}

	/**
	 * Load one JSON Schema.
	 *
	 * @param string $path Schema path.
	 *
	 * @return array<string,mixed>
	 * @throws RuntimeException When the schema cannot be decoded as a JSON object.
	 */
	private function load_schema( string $path ): array {
		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			throw new RuntimeException( 'Unable to read JSON Schema: ' . $path );
		}

		try {
			$decoded = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			throw new RuntimeException( 'Invalid JSON Schema ' . $path . ': ' . $exception->getMessage(), 0, $exception );
		}

		if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			throw new RuntimeException( 'JSON Schema must contain an object: ' . $path );
		}

		/** @var array<string,mixed> $decoded */
		return $decoded;
	}
}
