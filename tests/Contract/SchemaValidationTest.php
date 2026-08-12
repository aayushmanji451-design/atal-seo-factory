<?php
/**
 * JSON Schema validation tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Contract;

use Atal\Contracts\Validation\JsonSchemaValidator;
use Atal\Contracts\Validation\SchemaCatalogValidator;
use Atal\Tests\Fixtures\KnowledgePackageFixture;
use PHPUnit\Framework\TestCase;

/**
 * Proves that all eight schemas validate all nine mapped JSON masters.
 */
final class SchemaValidationTest extends TestCase {

	/**
	 * Validate every schema/document mapping.
	 */
	public function test_all_canonical_json_documents_match_their_schemas(): void {
		$result = ( new SchemaCatalogValidator( new JsonSchemaValidator() ) )->validate(
			KnowledgePackageFixture::package(),
			KnowledgePackageFixture::project_root() . '/data/schemas'
		);

		self::assertSame( 8, $result['schemas'] );
		self::assertSame( 9, $result['total'] );
		self::assertSame( 9, $result['passed'], implode( PHP_EOL, array_map( static fn ( $issue ): string => $issue->format(), $result['issues'] ) ) );
		self::assertSame( array(), $result['issues'] );
	}
}
