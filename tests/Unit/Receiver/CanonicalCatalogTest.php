<?php
/** Task 01 reuse coverage. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Receiver;

use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\DiplomaReceiver\Application\Validation\CanonicalDiplomaCatalog;
use PHPUnit\Framework\TestCase;
final class CanonicalCatalogTest extends TestCase {
	public function test_catalog_reuses_task_01_validation_and_contains_only_diploma_keys(): void {
		$root    = dirname( __DIR__, 3 );
		$catalog = new CanonicalDiplomaCatalog( $root . '/data/master', $root . '/data/schemas', KnowledgeValidator::create_default() );
		$catalog->assert_valid();
		self::assertTrue( $catalog->contains( 'diploma_basic_health_care' ) );
		self::assertFalse( $catalog->contains( 'institute_basic_health_care' ) ); }
}
