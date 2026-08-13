<?php
/** Staging runtime guard. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Domain\Canary;

interface CanaryRuntimeGuardInterface {
	public function assert_institute_ready(): void;
	public function assert_diploma_send_ready(): void;
}
