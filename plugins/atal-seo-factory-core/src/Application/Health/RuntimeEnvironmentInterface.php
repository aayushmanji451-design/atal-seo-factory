<?php
/**
 * Runtime environment read contract.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Health;

/**
 * Supplies health values without permitting configuration writes.
 */
interface RuntimeEnvironmentInterface {

	public function site_url(): string;

	public function environment_type(): string;

	public function wordpress_version(): string;

	public function php_version(): string;

	public function wordpress_memory_limit(): string;

	public function wordpress_max_memory_limit(): string;

	public function php_memory_limit(): string;

	public function current_memory_usage(): int;

	public function peak_memory_usage(): int;
}
