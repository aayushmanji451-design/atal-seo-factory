<?php
/**
 * Catalog completeness tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Contract;

use Atal\Contracts\Data\CanonicalCatalog;
use Atal\Contracts\Validation\CatalogValidator;
use Atal\Tests\Fixtures\KnowledgePackageFixture;
use PHPUnit\Framework\TestCase;

/**
 * Verifies independent approved cardinalities and active keys.
 */
final class CatalogCompletenessTest extends TestCase {

	/**
	 * Verify the complete active catalog.
	 */
	public function test_complete_catalog_matches_the_independent_allowlist(): void {
		$validator = new CatalogValidator();
		$package   = KnowledgePackageFixture::package();
		$metrics   = $validator->metrics( $package );

		self::assertCount( 29, CanonicalCatalog::institute_keys() );
		self::assertCount( 14, CanonicalCatalog::diploma_keys() );
		self::assertCount( 43, CanonicalCatalog::all_keys() );
		self::assertSame( 29, $metrics['institute_families'] );
		self::assertSame( 49, $metrics['institute_options'] );
		self::assertSame( 14, $metrics['diploma_identities'] );
		self::assertSame( 43, $metrics['unique_active_keys'] );
		self::assertSame( array(), $validator->validate( $package ) );
	}
}
