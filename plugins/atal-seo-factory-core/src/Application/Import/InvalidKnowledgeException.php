<?php
/**
 * Invalid canonical knowledge exception.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Import;

use Atal\Contracts\Validation\ValidationReport;
use RuntimeException;

/**
 * Stops dry-run and import before any write when Task 01 validation fails.
 */
final class InvalidKnowledgeException extends RuntimeException {

	public function __construct( private readonly ValidationReport $report ) {
		parent::__construct( 'Canonical knowledge validation failed.' );
	}

	public function report(): ValidationReport {
		return $this->report;
	}
}
