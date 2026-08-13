<?php
/**
 * In-memory Task 02 safety monitor.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Support;

use Atal\SeoFactory\Application\Acceptance\SafetyMonitorInterface;
use Atal\SeoFactory\Application\Acceptance\SafetyObservation;

/**
 * Returns a deterministic observation for acceptance runner tests.
 */
final class InMemorySafetyMonitor implements SafetyMonitorInterface {

	private int $starts = 0;

	private int $stops = 0;

	public function __construct( private readonly ?SafetyObservation $observation = null ) {
	}

	public function start(): void {
		++$this->starts;
	}

	public function stop(): SafetyObservation {
		++$this->stops;
		return $this->observation ?? new SafetyObservation( 0, 0, 0, 0, 0, 0 );
	}

	public function starts(): int {
		return $this->starts;
	}

	public function stops(): int {
		return $this->stops;
	}
}
