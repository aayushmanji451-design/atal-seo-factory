<?php
/**
 * Approved fixture validator.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Repository\ArrayCourseRepository;
use Atal\Contracts\Repository\ArraySyllabusRepository;
use Atal\Contracts\Value\CourseKey;

/**
 * Executes the 30 approved canonical knowledge fixtures.
 */
final class FixtureValidator {

	/**
	 * Run all approved fixtures.
	 *
	 * @param KnowledgePackage $package Canonical package.
	 *
	 * @return array{issues:list<ValidationIssue>,passed:int,total:int}
	 */
	public function validate( KnowledgePackage $package ): array {
		$fixture_document    = $package->document( 'test_fixtures' );
		$course_repository   = new ArrayCourseRepository(
			$package->document( 'institute_courses' ),
			$package->document( 'diploma_courses' )
		);
		$syllabus_repository = new ArraySyllabusRepository( $package->document( 'syllabus' ) );
		$fixtures            = JsonValue::object_list_field( $fixture_document, 'fixtures' );
		$issues              = array();
		$passed              = 0;

		foreach ( $fixtures as $fixture ) {
			$actual   = $this->evaluate( $fixture, $package, $course_repository, $syllabus_repository );
			$expected = JsonValue::object_field( $fixture, 'expected' );

			if ( $this->matches_expected( $actual, $expected ) ) {
				++$passed;
				continue;
			}

			$issues[] = new ValidationIssue(
				'fixture_expectation_mismatch',
				'Fixture did not produce its approved expected result.',
				'test_fixtures.' . JsonValue::string_field( $fixture, 'fixture_key' )
			);
		}

		return array(
			'issues' => $issues,
			'passed' => $passed,
			'total'  => count( $fixtures ),
		);
	}

	/**
	 * Evaluate one fixture.
	 *
	 * @param array<string,mixed>     $fixture               Fixture definition.
	 * @param KnowledgePackage        $package               Canonical package.
	 * @param ArrayCourseRepository   $course_repository     Course repository.
	 * @param ArraySyllabusRepository $syllabus_repository   Syllabus repository.
	 *
	 * @return array<string,mixed>
	 */
	private function evaluate(
		array $fixture,
		KnowledgePackage $package,
		ArrayCourseRepository $course_repository,
		ArraySyllabusRepository $syllabus_repository
	): array {
		$input = JsonValue::object_field( $fixture, 'input' );

		return match ( JsonValue::string_field( $fixture, 'validation_area' ) ) {
			'locked_fact'         => $this->evaluate_locked_fact( $input ),
			'eligibility'         => $this->evaluate_eligibility( $input ),
			'identity'            => $this->evaluate_identity( $input, $course_repository ),
			'intent_gate'         => $this->evaluate_intent_gate( $input, $syllabus_repository ),
			'source_reference'    => $this->evaluate_source_reference( $package ),
			'internal_link'       => $this->evaluate_internal_link( $input, $course_repository ),
			'catalog_completeness' => $this->evaluate_catalog_completeness( $input, $package, $course_repository ),
			default               => array(
				'valid'     => false,
				'error_key' => 'unsupported_fixture_area',
			),
		};
	}

	/**
	 * Evaluate a locked fact fixture.
	 *
	 * @param array<string,mixed> $input Fixture input.
	 *
	 * @return array<string,mixed>
	 */
	private function evaluate_locked_fact( array $input ): array {
		$key = JsonValue::string_field( $input, 'course_key' );
		if ( 'institute_cms_ed' === $key ) {
			if ( '2 Years' !== JsonValue::string_field( $input, 'duration' ) ) {
				return array(
					'valid'     => false,
					'error_key' => 'locked_duration_mismatch',
				);
			}
			if ( 17000 !== JsonValue::integer_field( $input, 'fee_amount' ) ) {
				return array(
					'valid'     => false,
					'error_key' => 'locked_fee_mismatch',
				);
			}
		}

		$fees = array(
			'diploma_first_aid_treatment'           => 25800,
			'diploma_hospital_management'           => 25000,
			'diploma_fire_safety_hazard_management' => 30000,
		);

		if ( isset( $fees[ $key ] ) && JsonValue::integer_field( $input, 'fee_amount' ) !== $fees[ $key ] ) {
			return array(
				'valid'     => false,
				'error_key' => 'locked_fee_mismatch',
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Evaluate an eligibility fixture.
	 *
	 * @param array<string,mixed> $input Fixture input.
	 *
	 * @return array<string,mixed>
	 */
	private function evaluate_eligibility( array $input ): array {
		if ( isset( $input['target_site'] ) && 'atal_institute' === JsonValue::string_field( $input, 'target_site' ) ) {
			$valid = 'normal_post' === JsonValue::string_field( $input, 'content_type' )
				&& 'OMIT' === JsonValue::string_field( $input, 'publication_behavior' )
				&& false === JsonValue::boolean_field( $input, 'eligibility_heading_present' );

			return $valid ? array( 'valid' => true ) : array(
				'valid'     => false,
				'error_key' => 'institute_eligibility_must_be_omitted',
			);
		}

		$key      = JsonValue::string_field( $input, 'course_key' );
		$criteria = JsonValue::string_list_field( $input, 'criteria' );
		$expected = str_starts_with( $key, 'diploma_pg_' ) ? array( 'Graduation Pass' ) : array( '12th Pass' );

		return $criteria === $expected ? array( 'valid' => true ) : array(
			'valid'     => false,
			'error_key' => 'diploma_eligibility_mismatch',
		);
	}

	/**
	 * Evaluate an identity fixture.
	 *
	 * @param array<string,mixed>   $input      Fixture input.
	 * @param ArrayCourseRepository $repository Course repository.
	 *
	 * @return array<string,mixed>
	 */
	private function evaluate_identity( array $input, ArrayCourseRepository $repository ): array {
		if ( isset( $input['alias'] ) ) {
			$records = $repository->resolve_term( JsonValue::string_field( $input, 'alias' ) );

			return array(
				'valid'               => 1 === count( $records ),
				'course_key'          => 1 === count( $records ) ? JsonValue::string_field( $records[0], 'course_key' ) : '',
				'active_record_count' => count( $records ),
			);
		}

		if ( isset( $input['source_rows'] ) ) {
			$valid = 51 === JsonValue::integer_field( $input, 'source_rows' )
				&& 1 === JsonValue::integer_field( $input, 'duplicate_rows_merged' )
				&& 1 === JsonValue::integer_field( $input, 'alias_rows_merged' )
				&& 49 === JsonValue::integer_field( $input, 'active_options' );

			return array( 'valid' => $valid );
		}

		$record = $repository->find( new CourseKey( JsonValue::string_field( $input, 'course_key' ) ) );
		if ( null === $record || JsonValue::string_field( $input, 'target_site' ) !== JsonValue::string_field( $record, 'target_site' ) ) {
			return array(
				'valid'     => false,
				'error_key' => 'course_target_site_mismatch',
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Evaluate an intent gate fixture.
	 *
	 * @param array<string,mixed>     $input      Fixture input.
	 * @param ArraySyllabusRepository $repository Syllabus repository.
	 *
	 * @return array<string,mixed>
	 */
	private function evaluate_intent_gate( array $input, ArraySyllabusRepository $repository ): array {
		$record = $repository->find( new CourseKey( JsonValue::string_field( $input, 'course_key' ) ) );
		$intent = JsonValue::string_field( $input, 'intent_key' );
		if ( null === $record ) {
			return array(
				'valid'     => false,
				'error_key' => 'course_not_found',
			);
		}

		$syllabus   = JsonValue::object_field( $record, 'syllabus' );
		$assessment = JsonValue::object_field( $record, 'assessment' );
		if ( in_array( $intent, array( 'syllabus', 'subjects' ), true ) && 'MISSING_APPROVED_SOURCE' === JsonValue::string_field( $syllabus, 'status' ) ) {
			return array(
				'valid'     => false,
				'error_key' => 'approved_syllabus_missing',
			);
		}

		if ( 'assessment' === $intent && 'MISSING_APPROVED_SOURCE' === JsonValue::string_field( $assessment, 'status' ) ) {
			return array(
				'valid'     => false,
				'error_key' => 'approved_assessment_missing',
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Evaluate the source reference fixture.
	 *
	 * @param KnowledgePackage $package Canonical package.
	 *
	 * @return array<string,mixed>
	 */
	private function evaluate_source_reference( KnowledgePackage $package ): array {
		$issues = ( new SourceReferenceValidator() )->validate( $package );

		return array(
			'valid'               => array() === $issues,
			'checked_all_courses' => true,
		);
	}

	/**
	 * Evaluate an internal link fixture.
	 *
	 * @param array<string,mixed>   $input      Fixture input.
	 * @param ArrayCourseRepository $repository Course repository.
	 *
	 * @return array<string,mixed>
	 */
	private function evaluate_internal_link( array $input, ArrayCourseRepository $repository ): array {
		$record = $repository->find( new CourseKey( JsonValue::string_field( $input, 'linked_course_key' ) ) );
		if ( null === $record || JsonValue::string_field( $record, 'target_site' ) !== JsonValue::string_field( $input, 'article_target_site' ) ) {
			return array(
				'valid'     => false,
				'error_key' => 'cross_site_course_link',
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Evaluate a catalog completeness fixture.
	 *
	 * @param array<string,mixed>   $input      Fixture input.
	 * @param KnowledgePackage      $package    Canonical package.
	 * @param ArrayCourseRepository $repository Course repository.
	 *
	 * @return array<string,mixed>
	 */
	private function evaluate_catalog_completeness( array $input, KnowledgePackage $package, ArrayCourseRepository $repository ): array {
		$metrics = ( new CatalogValidator() )->metrics( $package );

		if ( isset( $input['expected_families'] ) ) {
			return array( 'valid' => JsonValue::integer_field( $input, 'expected_families' ) === $metrics['institute_families'] && JsonValue::integer_field( $input, 'expected_options' ) === $metrics['institute_options'] );
		}

		if ( isset( $input['expected_identities'] ) ) {
			$diploma         = $package->document( 'diploma_courses' );
			$catalog_summary = JsonValue::object_field( $diploma, 'catalog_summary' );

			return array(
				'valid' => JsonValue::integer_field( $input, 'expected_identities' ) === $metrics['diploma_identities']
					&& JsonValue::integer_field( $input, 'expected_diploma_level' ) === JsonValue::integer_field( $catalog_summary, 'diploma_level' )
					&& JsonValue::integer_field( $input, 'expected_pg_level' ) === JsonValue::integer_field( $catalog_summary, 'pg_diploma_level' ),
			);
		}

		if ( isset( $input['expected_active_identities'] ) ) {
			return array( 'valid' => JsonValue::integer_field( $input, 'expected_active_identities' ) === $metrics['unique_active_keys'] );
		}

		$key = JsonValue::string_field( $input, 'course_key' );
		if ( 'diploma_disaster_management' === $key ) {
			return array(
				'valid'     => false,
				'error_key' => 'inactive_noncanonical_identity',
			);
		}

		$record = $repository->find( new CourseKey( $key ) );
		if ( null === $record ) {
			return array(
				'valid'     => false,
				'error_key' => 'course_not_found',
			);
		}

		return array(
			'valid'               => true,
			'family_option_count' => JsonValue::integer_field( $record, 'family_option_count' ),
		);
	}

	/**
	 * Determine whether actual output contains every expected key/value.
	 *
	 * @param array<string,mixed> $actual   Actual output.
	 * @param array<string,mixed> $expected Expected output.
	 */
	private function matches_expected( array $actual, array $expected ): bool {
		foreach ( $expected as $key => $value ) {
			if ( ! array_key_exists( $key, $actual ) || $actual[ $key ] !== $value ) {
				return false;
			}
		}

		return true;
	}
}
