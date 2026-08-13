<?php
/** Non-destructive receiver deactivation. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Lifecycle;

final class Deactivator {
	public function deactivate(): void {
		/* Persistent receiver state is intentionally retained. */ }
}
