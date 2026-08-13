<?php
/** Receiver activation/deactivation safety. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Receiver;

use Atal\DiplomaReceiver\Application\Lifecycle\Activator;
use Atal\DiplomaReceiver\Application\Lifecycle\Deactivator;
use Atal\DiplomaReceiver\Application\Migration\MigrationRunner;
use Atal\Tests\Support\Receiver\TestCourseCatalog;
use PHPUnit\Framework\TestCase;
final class ActivationTest extends TestCase {
	public function test_activation_is_idempotent_and_deactivation_preserves_state(): void {
		$state     = new ReceiverMigrationState();
		$migration = new ReceiverMigration();
		$catalog   = new TestCourseCatalog();
		$activator = new Activator( new MigrationRunner( array( $migration ), $state ), $state, $catalog, '0.3.0-dev' );
		$activator->activate();
		$activator->activate();
		self::assertSame( 1, $migration->ups );
		( new Deactivator() )->deactivate();
		self::assertSame( 1, $state->database_version() ); }
}
