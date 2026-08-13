<?php
/** Versioned receiver migration. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Migration;

interface MigrationInterface {
	public function version(): int;
	public function up(): void;
	public function down(): void;
}
