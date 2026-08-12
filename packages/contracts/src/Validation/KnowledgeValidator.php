<?php
/**
 * Canonical knowledge validator orchestrator.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

use Atal\Contracts\Data\KnowledgePackage;

/**
 * Runs schemas, locked facts, identities, source references, fixtures, and missing-data rules.
 */
final class KnowledgeValidator {

	/**
	 * Create the validator orchestrator.
	 *
	 * @param SchemaCatalogValidator   $schema_validator    Schema catalog validator.
	 * @param CatalogValidator         $catalog_validator   Catalog validator.
	 * @param LockedFactValidator      $fact_validator      Locked fact validator.
	 * @param IdentityValidator        $identity_validator  Identity validator.
	 * @param SourceReferenceValidator $source_validator    Source reference validator.
	 * @param MissingDataValidator     $missing_validator   Missing-data validator.
	 * @param FixtureValidator         $fixture_validator   Approved fixture validator.
	 */
	public function __construct(
		private readonly SchemaCatalogValidator $schema_validator,
		private readonly CatalogValidator $catalog_validator,
		private readonly LockedFactValidator $fact_validator,
		private readonly IdentityValidator $identity_validator,
		private readonly SourceReferenceValidator $source_validator,
		private readonly MissingDataValidator $missing_validator,
		private readonly FixtureValidator $fixture_validator
	) {
	}

	/**
	 * Build the default validator set.
	 */
	public static function create_default(): self {
		return new self(
			new SchemaCatalogValidator( new JsonSchemaValidator() ),
			new CatalogValidator(),
			new LockedFactValidator(),
			new IdentityValidator(),
			new SourceReferenceValidator(),
			new MissingDataValidator(),
			new FixtureValidator()
		);
	}

	/**
	 * Validate the complete package.
	 *
	 * @param KnowledgePackage $package          Canonical package.
	 * @param string           $schema_directory Schema directory.
	 */
	public function validate( KnowledgePackage $package, string $schema_directory ): ValidationReport {
		$schema_result   = $this->schema_validator->validate( $package, $schema_directory );
		$fixture_result  = $this->fixture_validator->validate( $package );
		$catalog_metrics = $this->catalog_validator->metrics( $package );
		$source_metrics  = $this->source_validator->metrics( $package );
		$catalog_issues  = $this->catalog_validator->validate( $package );
		$fact_issues     = $this->fact_validator->validate( $package );
		$identity_issues = $this->identity_validator->validate( $package );
		$source_issues   = $this->source_validator->validate( $package );
		$missing_issues  = $this->missing_validator->validate( $package );
		$issues          = array_merge(
			$schema_result['issues'],
			$catalog_issues,
			$fact_issues,
			$identity_issues,
			$source_issues,
			$missing_issues,
			$fixture_result['issues']
		);

		$metrics = array_merge(
			$catalog_metrics,
			$source_metrics,
			array(
				'schema_documents_passed' => $schema_result['passed'],
				'schema_documents_total'  => $schema_result['total'],
				'schema_files'            => $schema_result['schemas'],
				'fixtures_passed'         => $fixture_result['passed'],
				'fixtures_total'          => $fixture_result['total'],
				'open_missing_blocks'     => count( $this->missing_validator->report( $package ) ),
				'catalog_valid'           => array() === $catalog_issues,
				'locked_facts_valid'      => array() === $fact_issues,
				'cross_site_valid'        => array() === $identity_issues,
				'source_references_valid' => array() === $source_issues,
				'missing_scope_valid'     => array() === $missing_issues,
			)
		);

		return new ValidationReport( $issues, $metrics, $this->missing_validator->report( $package ) );
	}
}
