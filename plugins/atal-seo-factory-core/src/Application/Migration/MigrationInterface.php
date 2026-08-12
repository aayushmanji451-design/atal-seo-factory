<?php
/**
 * Versioned database migration contract.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Migration;

/**
 * Defines one forward and reversible schema version.
 */
interface MigrationInterface {

	public function version(): int;

	public function up(): void;

	public function down(): void;
}
