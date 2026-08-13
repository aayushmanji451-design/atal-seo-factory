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
		private readonly int $current_memory = 12582912,
		private readonly int $peak_memory = 16777216
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
		return $this->current_memory;
	}

	public function peak_memory_usage(): int {
		return $this->peak_memory;
	}
}
