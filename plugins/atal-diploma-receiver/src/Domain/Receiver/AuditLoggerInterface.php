<?php
/** Secret-free audit boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Receiver;

interface AuditLoggerInterface {
	/** @param array<string,int|string|bool|null> $context */ public function record( string $event, string $outcome, array $context = array() ): void;
}
