<?php
/**
 * Task 02 acceptance safety monitor contract.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

/**
 * Observes forbidden side effects without performing them.
 */
interface SafetyMonitorInterface {

	public function start(): void;

	public function stop(): SafetyObservation;
}
