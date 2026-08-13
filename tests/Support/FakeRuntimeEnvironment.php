<?php
/**
 * Runtime environment test double.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Support;

use Atal\SeoFactory\Application\Health\RuntimeEnvironmentInterface;

/**
 * Supplies deterministic staging health values.
 */
final class FakeRuntimeEnvironment implements RuntimeEnvironmentInterface {

	public function __construct(
		private readonly string $wordpress_memory = '40M',
		private readonly string $php_memory = '2048M',
		private readonly string $wordpress_max_memory = '2048M',
		private readonly int $current_usage = 20971520,
		private readonly int $peak_usage = 25165824,
		private readonly bool $admin_can_raise = true
	) {
	}

	public function site_url(): string {
		return 'https://liveup2.atalinstitute.com/';
	}

	public function environment_type(): string {
		return 'production';
	}

	public function wordpress_version(): string {
		return '7.0.3';
	}

	public function php_version(): string {
		return '8.3.30';
	}

	public function wordpress_memory_limit(): string {
		return $this->wordpress_memory;
	}

	public function php_memory_limit(): string {
		return $this->php_memory;
	}

	public function wordpress_max_memory_limit(): string {
		return $this->wordpress_max_memory;
	}

	public function current_memory_usage(): int {
		return $this->current_usage;
	}

	public function peak_memory_usage(): int {
		return $this->peak_usage;
	}

	public function wordpress_admin_can_raise_memory(): bool {
		return $this->admin_can_raise;
	}

	public function raise_wordpress_admin_memory(): bool {
		return $this->admin_can_raise;
	}
}
