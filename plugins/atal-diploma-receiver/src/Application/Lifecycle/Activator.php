<?php
/** Safe receiver activation. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Lifecycle;

use Atal\DiplomaReceiver\Application\Migration\MigrationRunner;
use Atal\DiplomaReceiver\Application\Migration\ReceiverStateStoreInterface;
use Atal\DiplomaReceiver\Domain\Receiver\CourseCatalogInterface;
final class Activator {
	public function __construct( private readonly MigrationRunner $migrations, private readonly ReceiverStateStoreInterface $state, private readonly CourseCatalogInterface $catalog, private readonly string $version ) {}
	public function activate(): void {
		$this->catalog->assert_valid();
		$this->migrations->migrate_to_latest();
		$this->state->ensure_secret();
		$this->state->record_plugin_version( $this->version ); }
}
