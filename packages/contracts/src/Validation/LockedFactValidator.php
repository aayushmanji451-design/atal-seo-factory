<?php
/**
 * Locked fact validator.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Value\Eligibility;
use Atal\Contracts\Value\TargetSite;

/**
 * Enforces repository-locked Institute and Diploma facts.
 */
final class LockedFactValidator {

	/**
	 * Diploma fees that differ from the general applicable fee.
	 *
	 * @var array<string,int>
	 */
	private const DIPLOMA_FEE_EXCEPTIONS = array(
		'diploma_first_aid_treatment' => 25800,
		'diploma_hospital_management' => 25000,
	);

	/**
	 * Validate all locked facts.
	 *
	 * @param KnowledgePackage $package Canonical package.
	 *
	 * @return list<ValidationIssue>
	 */
	public function validate( KnowledgePackage $package ): array {
		$issues             = array();
		$institute_document = $package->document( 'institute_courses' );
		$diploma_document   = $package->document( 'diploma_courses' );
		$institute_courses  = $this->courses_by_key( $institute_document );
		$diploma_courses    = $this->courses_by_key( $diploma_document );

		if ( TargetSite::INSTITUTE !== JsonValue::string_field( $institute_document, 'target_site' ) ) {
			$issues[] = new ValidationIssue( 'institute_target_site_mismatch', 'Institute master target_site must be atal_institute.', 'institute_courses.target_site' );
		}

		$institute_identity = JsonValue::object_field( $institute_document, 'site_identity' );
		if ( Eligibility::OMIT !== JsonValue::string_field( $institute_identity, 'normal_post_eligibility_behavior' ) ) {
			$issues[] = new ValidationIssue( 'institute_eligibility_behavior_mismatch', 'Institute normal-post eligibility behavior must be OMIT.', 'institute_courses.site_identity' );
		}

		foreach ( $institute_courses as $course_key => $course ) {
			$eligibility = JsonValue::object_field( $course, 'eligibility' );
			if ( Eligibility::OMIT !== JsonValue::string_field( $eligibility, 'publication_behavior' ) || array() !== JsonValue::string_list_field( $eligibility, 'criteria' ) ) {
				$issues[] = new ValidationIssue( 'institute_eligibility_must_be_omitted', 'Every Institute family must omit eligibility in normal posts.', $course_key . '.eligibility' );
			}
		}

		$cms = $institute_courses['institute_cms_ed'] ?? null;
		if ( null === $cms ) {
			$issues[] = new ValidationIssue( 'cms_ed_missing', 'CMS & ED is missing from the Institute catalog.', 'institute_courses.courses' );
		} else {
			$duration = JsonValue::object_field( $cms, 'duration' );
			$fee      = JsonValue::object_field( $cms, 'fee' );
			if ( '2 Years' !== JsonValue::string_field( $duration, 'display' ) || 24 !== JsonValue::integer_field( $duration, 'normalized_months' ) ) {
				$issues[] = new ValidationIssue( 'locked_duration_mismatch', 'CMS & ED duration must be exactly 2 Years (24 months).', 'institute_cms_ed.duration' );
			}

			if ( 17000 !== JsonValue::integer_field( $fee, 'amount' ) || '₹17,000' !== JsonValue::string_field( $fee, 'display' ) ) {
				$issues[] = new ValidationIssue( 'locked_fee_mismatch', 'CMS & ED fee must be exactly ₹17,000.', 'institute_cms_ed.fee' );
			}
		}

		if ( TargetSite::DIPLOMA !== JsonValue::string_field( $diploma_document, 'target_site' ) ) {
			$issues[] = new ValidationIssue( 'diploma_target_site_mismatch', 'Diploma master target_site must be atal_diploma.', 'diploma_courses.target_site' );
		}

		$diploma_identity = JsonValue::object_field( $diploma_document, 'site_identity' );
		if ( 'COURSE_BY_COURSE' !== JsonValue::string_field( $diploma_identity, 'eligibility_behavior' ) ) {
			$issues[] = new ValidationIssue( 'diploma_eligibility_behavior_mismatch', 'Diploma eligibility must be stored course-by-course.', 'diploma_courses.site_identity' );
		}

		foreach ( $diploma_courses as $course_key => $course ) {
			$expected_fee = self::DIPLOMA_FEE_EXCEPTIONS[ $course_key ] ?? 30000;
			$fee          = JsonValue::object_field( $course, 'fee' );
			if ( JsonValue::integer_field( $fee, 'amount' ) !== $expected_fee ) {
				$issues[] = new ValidationIssue( 'locked_fee_mismatch', 'Diploma fee does not match its locked course-specific or general value.', $course_key . '.fee' );
			}

			$expected_criteria = 'PG_DIPLOMA' === JsonValue::string_field( $course, 'level' ) ? array( 'Graduation Pass' ) : array( '12th Pass' );
			$eligibility       = JsonValue::object_field( $course, 'eligibility' );
			if ( Eligibility::SHOW !== JsonValue::string_field( $eligibility, 'publication_behavior' ) || JsonValue::string_list_field( $eligibility, 'criteria' ) !== $expected_criteria ) {
				$issues[] = new ValidationIssue( 'diploma_eligibility_mismatch', 'Diploma eligibility does not match its course level.', $course_key . '.eligibility' );
			}
		}

		return $issues;
	}

	/**
	 * Index courses by key.
	 *
	 * @param array<string,mixed> $document Course master.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function courses_by_key( array $document ): array {
		$result  = array();
		$courses = JsonValue::object_list_field( $document, 'courses' );

		foreach ( $courses as $course ) {
			$result[ JsonValue::string_field( $course, 'course_key' ) ] = $course;
		}

		return $result;
	}
}
