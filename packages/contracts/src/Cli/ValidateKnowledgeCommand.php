<?php
/**
 * Canonical knowledge CLI command.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Cli;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Validation\KnowledgeValidator;
use Throwable;

/**
 * Loads and validates the complete canonical knowledge package.
 */
final class ValidateKnowledgeCommand {

	/**
	 * Execute the command.
	 *
	 * @param string $project_root Repository root.
	 */
	public function run( string $project_root ): int {
		try {
			$package = KnowledgePackage::from_directory( $project_root . '/data/master' );
			$report  = KnowledgeValidator::create_default()->validate( $package, $project_root . '/data/schemas' );
		} catch ( Throwable $throwable ) {
			fwrite( STDERR, 'KNOWLEDGE_VALIDATION: FAIL' . PHP_EOL );
			fwrite( STDERR, $throwable->getMessage() . PHP_EOL );

			return 1;
		}

		foreach ( $report->issues() as $issue ) {
			fwrite( STDERR, 'FAIL: ' . $issue->format() . PHP_EOL );
		}

		$metrics = $report->metrics();
		$this->line( 'SCHEMA_FILES', $metrics['schema_files'] . '/8' );
		$this->line( 'SCHEMA_DOCUMENTS', $metrics['schema_documents_passed'] . '/' . $metrics['schema_documents_total'] );
		$this->line( 'INSTITUTE_ACTIVE_FAMILIES', $metrics['institute_families'] . '/29' );
		$this->line( 'INSTITUTE_OPTIONS', $metrics['institute_options'] . '/49' );
		$this->line( 'DIPLOMA_ACTIVE_IDENTITIES', $metrics['diploma_identities'] . '/14' );
		$this->line( 'UNIQUE_ACTIVE_COURSE_KEYS', $metrics['unique_active_keys'] . '/43' );
		$this->line( 'SOURCE_REFERENCED_COURSES', $metrics['course_facts_checked'] . '/43' );
		$this->line( 'SOURCE_REFERENCED_INSTITUTE_OPTIONS', $metrics['institute_options_checked'] . '/49' );
		$this->line( 'OPEN_MISSING_DATA_BLOCKS', $metrics['open_missing_blocks'] . '/6' );
		$this->line( 'APPROVED_FIXTURES', $metrics['fixtures_passed'] . '/' . $metrics['fixtures_total'] );
		$this->line( 'CATALOG_COMPLETENESS', true === $metrics['catalog_valid'] ? 'PASS' : 'FAIL' );
		$this->line( 'LOCKED_FACTS', true === $metrics['locked_facts_valid'] ? 'PASS' : 'FAIL' );
		$this->line( 'CROSS_SITE_IDENTITY', true === $metrics['cross_site_valid'] ? 'PASS' : 'FAIL' );
		$this->line( 'SOURCE_REFERENCE_VALIDATION', true === $metrics['source_references_valid'] ? 'PASS' : 'FAIL' );
		$this->line( 'MISSING_SYLLABUS_SCOPE', true === $metrics['missing_scope_valid'] ? 'PASS' : 'FAIL' );

		foreach ( $report->missing_data_blocks() as $block ) {
			$missing = JsonValue::string_list_field( $block, 'missing' );
			fwrite( STDOUT, 'OPEN: ' . JsonValue::string_field( $block, 'course_key' ) . ' — ' . implode( '; ', $missing ) . PHP_EOL );
		}

		fwrite( STDOUT, 'KNOWLEDGE_VALIDATION: ' . ( $report->is_valid() ? 'PASS' : 'FAIL' ) . PHP_EOL );

		return $report->is_valid() ? 0 : 1;
	}

	/**
	 * Write one metric.
	 *
	 * @param string $name  Metric name.
	 * @param string $value Metric value.
	 */
	private function line( string $name, string $value ): void {
		fwrite( STDOUT, $name . ': ' . $value . PHP_EOL );
	}
}
