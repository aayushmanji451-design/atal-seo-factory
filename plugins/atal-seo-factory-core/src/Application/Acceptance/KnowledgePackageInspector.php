<?php
/**
 * Canonical package acceptance metrics.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;

/**
 * Counts only approved Task 01 identities and nested Institute options.
 */
final class KnowledgePackageInspector {

	/**
	 * @return array{active_total:int,institute_families:int,diploma_identities:int,institute_options:int}
	 */
	public function summary( KnowledgePackage $package ): array {
		$institute         = JsonValue::object_list_field( $package->document( 'institute_courses' ), 'courses' );
		$diploma           = JsonValue::object_list_field( $package->document( 'diploma_courses' ), 'courses' );
		$institute_options = 0;
		$active_total      = 0;

		foreach ( $institute as $course ) {
			if ( 'ACTIVE_CANONICAL' === JsonValue::string_field( $course, 'course_status' ) ) {
				++$active_total;
			}
			$institute_options += count( JsonValue::object_list_field( $course, 'options' ) );
		}

		foreach ( $diploma as $course ) {
			if ( 'ACTIVE_CANONICAL' === JsonValue::string_field( $course, 'course_status' ) ) {
				++$active_total;
			}
		}

		return array(
			'active_total'       => $active_total,
			'institute_families' => count( $institute ),
			'diploma_identities' => count( $diploma ),
			'institute_options'  => $institute_options,
		);
	}
}
