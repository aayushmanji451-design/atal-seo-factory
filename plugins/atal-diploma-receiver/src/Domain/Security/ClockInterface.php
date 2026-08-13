<?php
/** Clock boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Security;

interface ClockInterface {
	public function now(): int;
}
