<?php
/** Secret-free Core canary audit boundary. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Domain\Canary;

interface CanaryAuditLoggerInterface {
	/** @param array<string,int|string|bool|null> $context */ public function record( string $event, string $outcome, array $context = array() ): void;
}
