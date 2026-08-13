<?php
/**
 * Canonical import persistence failure.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Import;

use RuntimeException;
use Throwable;

/**
 * Identifies the exact transaction step that was rolled back.
 */
final class ImportPersistenceException extends RuntimeException {

	public function __construct( private readonly string $failed_step, Throwable $previous ) {
		parent::__construct( 'Canonical import rolled back at step: ' . $failed_step, 0, $previous );
	}

	public function failed_step(): string {
		return $this->failed_step;
	}
}
