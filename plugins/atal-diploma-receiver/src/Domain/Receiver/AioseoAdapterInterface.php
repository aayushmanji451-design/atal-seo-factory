<?php
/** AIOSEO environment boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Receiver;

interface AioseoAdapterInterface {
	public function detected(): bool;
	public function version(): ?string;
}
