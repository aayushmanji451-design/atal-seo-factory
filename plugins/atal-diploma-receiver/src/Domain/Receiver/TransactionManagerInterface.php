<?php
/** Transaction boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Receiver;

interface TransactionManagerInterface {
	public function begin(): void;
	public function commit(): void;
	public function rollback(): void;
}
