<?php
/**
 * Genuine missing-data validator.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;

/**
 * Keeps the six approved syllabus/assessment blocks open without disabling courses.
 */
final class MissingDataValidator {

	/**
	 * Six approved open block keys.
	 *
	 * @var list<string>
	 */
	private const APPROVED_BLOCK_KEYS = array(
		'missing_institute_cms_ed_syllabus',
		'missing_institute_cch_syllabus_assessment',
		'missing_institute_bems_syllabus_assessment',
		'missing_diploma_industrial_safety_syllabus_assessment',
		'missing_pg_industrial_safety_syllabus_assessment',
		'missing_pg_disaster_management_syllabus',
	);

	/**
	 * Syllabus-specific blocked intent keys.
	 *
	 * @var list<string>
	 */
	private const SYLLABUS_INTENTS = array( 'syllabus', 'subjects' );

	/**
	 * Assessment-specific blocked intent keys.
	 *
	 * @var list<string>
	 */
	private const ASSESSMENT_INTENTS = array( 'assessment' );

	/**
	 * Validate the open block allowlist and intent scoping.
	 *
	 * @param KnowledgePackage $package Canonical package.
	 *
	 * @return list<ValidationIssue>
	 */
	public function validate( KnowledgePackage $package ): array {
		$issues     = array();
		$document   = $package->document( 'syllabus' );
		$blocks     = JsonValue::object_list_field( $document, 'open_missing_data_blocks' );
		$records    = JsonValue::object_list_field( $document, 'records' );
		$record_map = array();

		foreach ( $records as $record ) {
			$record_map[ JsonValue::string_field( $record, 'course_key' ) ] = $record;
		}

		$block_keys = array();
		foreach ( $blocks as $block ) {
			$block_keys[] = JsonValue::string_field( $block, 'block_key' );
		}
		sort( $block_keys );
		$expected_keys = self::APPROVED_BLOCK_KEYS;
		sort( $expected_keys );

		if ( $block_keys !== $expected_keys ) {
			$issues[] = new ValidationIssue( 'open_missing_block_allowlist_mismatch', 'Exactly the six approved genuine missing-data blocks must remain open.', 'syllabus.open_missing_data_blocks' );
		}

		foreach ( $blocks as $block ) {
			$course_key = JsonValue::string_field( $block, 'course_key' );
			if ( ! isset( $record_map[ $course_key ] ) ) {
				$issues[] = new ValidationIssue( 'missing_block_course_unknown', 'Open block must reference a canonical syllabus record.', $course_key );
				continue;
			}

			$record = $record_map[ $course_key ];
			if ( JsonValue::boolean_field( $record, 'course_master_blocked' ) ) {
				$issues[] = new ValidationIssue( 'missing_syllabus_blocked_course_master', 'Missing syllabus or assessment cannot block the course master.', $course_key );
			}

			$allowed    = array();
			$syllabus   = JsonValue::object_field( $record, 'syllabus' );
			$assessment = JsonValue::object_field( $record, 'assessment' );
			if ( 'MISSING_APPROVED_SOURCE' === JsonValue::string_field( $syllabus, 'status' ) ) {
				$allowed = array_merge( $allowed, self::SYLLABUS_INTENTS );
			}
			if ( 'MISSING_APPROVED_SOURCE' === JsonValue::string_field( $assessment, 'status' ) ) {
				$allowed = array_merge( $allowed, self::ASSESSMENT_INTENTS );
			}

			$blocked = JsonValue::string_list_field( $record, 'blocked_intents' );
			foreach ( $blocked as $intent ) {
				if ( ! in_array( $intent, $allowed, true ) ) {
					$issues[] = new ValidationIssue( 'missing_data_overblocks_intent', 'Missing data may block only syllabus- or assessment-specific intents.', $course_key . '.blocked_intents' );
				}
			}

			if ( 'MISSING_APPROVED_SOURCE' === JsonValue::string_field( $syllabus, 'status' ) && ! in_array( 'syllabus', $blocked, true ) ) {
				$issues[] = new ValidationIssue( 'missing_syllabus_intent_not_blocked', 'Missing approved syllabus must block the syllabus intent.', $course_key . '.blocked_intents' );
			}

			if ( 'MISSING_APPROVED_SOURCE' === JsonValue::string_field( $assessment, 'status' ) && ! in_array( 'assessment', $blocked, true ) ) {
				$issues[] = new ValidationIssue( 'missing_assessment_intent_not_blocked', 'Missing approved assessment must block the assessment intent.', $course_key . '.blocked_intents' );
			}
		}

		return $issues;
	}

	/**
	 * Return genuine unresolved blocks.
	 *
	 * @param KnowledgePackage $package Canonical package.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function report( KnowledgePackage $package ): array {
		$document = $package->document( 'syllabus' );

		return JsonValue::object_list_field( $document, 'open_missing_data_blocks' );
	}
}
