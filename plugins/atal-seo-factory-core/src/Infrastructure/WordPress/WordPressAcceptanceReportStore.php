<?php
/**
 * WordPress Task 02 acceptance report store.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress;

use Atal\SeoFactory\Application\Acceptance\AcceptanceReportStoreInterface;
use Atal\SeoFactory\Config\Identifiers;

/**
 * Retains only the latest sanitized report as a non-autoloaded option.
 */
final class WordPressAcceptanceReportStore implements AcceptanceReportStoreInterface {

	public function save( array $report ): void {
		update_option( Identifiers::OPTION_ACCEPTANCE_REPORT, $report, false );
	}

	public function latest(): ?array {
		$value = get_option( Identifiers::OPTION_ACCEPTANCE_REPORT, null );

		if ( ! is_array( $value ) ) {
			return null;
		}

		/** @var array<string,mixed> $value */
		return $value;
	}
}
