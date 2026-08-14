<?php
/** Secret-free Task 05 audit boundary. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Contract;

interface AuditLoggerInterface {
	/** @param array<string,int|string|bool> $context */ public function record( string $event, string $outcome, array $context ): void;
}
