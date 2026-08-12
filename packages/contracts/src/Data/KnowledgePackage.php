<?php
/**
 * Canonical knowledge package loader.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Data;

use JsonException;
use RuntimeException;

/**
 * Immutable in-memory view of all JSON Phase 1 contracts.
 */
final class KnowledgePackage {

	/**
	 * Required JSON document files.
	 *
	 * @var array<string, string>
	 */
	public const FILES = array(
		'institute_courses' => '01-ATAL-INSTITUTE-COURSE-MASTER.json',
		'diploma_courses'   => '02-ATAL-DIPLOMA-COURSE-MASTER.json',
		'syllabus'          => '03-SYLLABUS-MASTER.json',
		'course_urls'       => '04-COURSE-URL-MAP.json',
		'image_assets'      => '05-IMAGE-ASSET-MAP.json',
		'intents'           => '07-SEARCH-INTENT-TAXONOMY.json',
		'internal_links'    => '08-INTERNAL-LINK-MAP.json',
		'blocked_claims'    => '09-BLOCKED-CLAIMS.json',
		'test_fixtures'     => '11-TEST-FIXTURES.json',
	);

	/**
	 * Decoded JSON documents.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private readonly array $documents;

	/**
	 * Create an immutable package.
	 *
	 * @param array<string,array<string,mixed>> $documents Decoded documents.
	 */
	private function __construct( array $documents ) {
		$this->documents = $documents;
	}

	/**
	 * Load all required JSON documents from a master directory.
	 *
	 * @param string $master_directory Master-data directory.
	 *
	 * @throws RuntimeException When a required document is missing or invalid.
	 */
	public static function from_directory( string $master_directory ): self {
		$documents = array();

		foreach ( self::FILES as $name => $filename ) {
			$path = rtrim( $master_directory, '/\\' ) . DIRECTORY_SEPARATOR . $filename;
			if ( ! is_readable( $path ) ) {
				throw new RuntimeException( 'Required master document is missing: ' . $path );
			}

			$documents[ $name ] = self::decode_file( $path );
		}

		return new self( $documents );
	}

	/**
	 * Create a package from already decoded documents.
	 *
	 * @param array<string,array<string,mixed>> $documents Decoded documents.
	 *
	 * @throws RuntimeException When a required document is missing.
	 */
	public static function from_documents( array $documents ): self {
		foreach ( array_keys( self::FILES ) as $name ) {
			if ( ! isset( $documents[ $name ] ) ) {
				throw new RuntimeException( 'Required knowledge document is missing: ' . $name );
			}
		}

		return new self( $documents );
	}

	/**
	 * Return one decoded document.
	 *
	 * @param string $name Document name.
	 *
	 * @return array<string, mixed>
	 * @throws RuntimeException When the requested document is unknown.
	 */
	public function document( string $name ): array {
		if ( ! isset( $this->documents[ $name ] ) ) {
			throw new RuntimeException( 'Unknown knowledge document: ' . $name );
		}

		return $this->documents[ $name ];
	}

	/**
	 * Return all decoded JSON documents.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function documents(): array {
		return $this->documents;
	}

	/**
	 * Decode a required JSON object.
	 *
	 * @param string $path JSON file path.
	 *
	 * @return array<string, mixed>
	 * @throws RuntimeException When the document cannot be decoded as a JSON object.
	 */
	private static function decode_file( string $path ): array {
		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			throw new RuntimeException( 'Unable to read master document: ' . $path );
		}

		try {
			$decoded = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			throw new RuntimeException( 'Invalid JSON in ' . $path . ': ' . $exception->getMessage(), 0, $exception );
		}

		if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			throw new RuntimeException( 'Master document must contain a JSON object: ' . $path );
		}

		/** @var array<string,mixed> $decoded */
		return $decoded;
	}
}
