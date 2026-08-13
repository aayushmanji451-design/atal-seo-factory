<?php
/** System UTC clock. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\DiplomaReceiver\Domain\Security\ClockInterface;
final class SystemClock implements ClockInterface {
	public function now(): int {
		return time(); }
}
