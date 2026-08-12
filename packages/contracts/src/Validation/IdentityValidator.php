<?php
/**
 * Cross-site identity validator.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Repository\ArrayCourseRepository;
use Atal\Contracts\Value\TargetSite;

/**
 * Prevents course, alias, URL, image, syllabus, and link identity mixing.
 */
final class IdentityValidator {

	/**
	 * Validate identity ownership and cross-contract coverage.
	 *
	 * @param KnowledgePackage $package Canonical package.
	 *
	 * @return list<ValidationIssue>
	 */
	public function validate( KnowledgePackage $package ): array {
		$issues             = array();
		$institute_document = $package->document( 'institute_courses' );
		$diploma_document   = $package->document( 'diploma_courses' );
		$repository         = new ArrayCourseRepository( $institute_document, $diploma_document );
		$courses            = $repository->all();
		$sites_by_key       = array();
		$normalized_to_keys = array();

		foreach ( $courses as $course ) {
			$key                  = JsonValue::string_field( $course, 'course_key' );
			$site                 = JsonValue::string_field( $course, 'target_site' );
			$sites_by_key[ $key ] = $site;

			if ( str_starts_with( $key, 'institute_' ) && TargetSite::INSTITUTE !== $site ) {
				$issues[] = new ValidationIssue( 'course_target_site_mismatch', 'Institute-prefixed key belongs to the wrong site.', $key );
			}
			if ( str_starts_with( $key, 'diploma_' ) && TargetSite::DIPLOMA !== $site ) {
				$issues[] = new ValidationIssue( 'course_target_site_mismatch', 'Diploma-prefixed key belongs to the wrong site.', $key );
			}

			$terms = array_merge( array( JsonValue::string_field( $course, 'canonical_name' ) ), JsonValue::string_list_field( $course, 'aliases' ) );
			foreach ( $terms as $term ) {
				$normalized                          = $this->normalize_term( $term );
				$normalized_to_keys[ $normalized ] ??= array();
				$normalized_to_keys[ $normalized ][] = $key;
			}
		}

		foreach ( $normalized_to_keys as $term => $keys ) {
			$keys = array_values( array_unique( $keys ) );
			if ( 1 < count( $keys ) ) {
				$issues[] = new ValidationIssue( 'alias_identity_collision', 'Canonical name or alias resolves to multiple active identities: ' . implode( ', ', $keys ), 'identity_term.' . $term );
			}
		}

		foreach ( array( 'syllabus', 'course_urls', 'image_assets', 'internal_links' ) as $document_name ) {
			$issues = array_merge( $issues, $this->validate_catalog_records( $package->document( $document_name ), $document_name, $sites_by_key ) );
		}

		$internal_links = $package->document( 'internal_links' );
		$link_records   = JsonValue::object_list_field( $internal_links, 'records' );
		foreach ( $link_records as $record ) {
			$source_key  = JsonValue::string_field( $record, 'course_key' );
			$source_site = JsonValue::string_field( $record, 'target_site' );
			foreach ( JsonValue::string_list_field( $record, 'related_course_keys' ) as $related_key ) {
				if ( ! isset( $sites_by_key[ $related_key ] ) || $sites_by_key[ $related_key ] !== $source_site ) {
					$issues[] = new ValidationIssue( 'cross_site_course_link', 'Related course must exist on the same target site.', $source_key . '.related_course_keys' );
				}
			}
		}

		return $issues;
	}

	/**
	 * Validate a 43-record catalog mapping.
	 *
	 * @param array<string,mixed>  $document     Catalog mapping.
	 * @param string               $document_name Document name.
	 * @param array<string,string> $sites_by_key Site ownership map.
	 *
	 * @return list<ValidationIssue>
	 */
	private function validate_catalog_records( array $document, string $document_name, array $sites_by_key ): array {
		$issues    = array();
		$seen_keys = array();
		$records   = JsonValue::object_list_field( $document, 'records' );

		foreach ( $records as $record ) {
			$key         = JsonValue::string_field( $record, 'course_key' );
			$site        = JsonValue::string_field( $record, 'target_site' );
			$seen_keys[] = $key;

			if ( ! isset( $sites_by_key[ $key ] ) || $sites_by_key[ $key ] !== $site ) {
				$issues[] = new ValidationIssue( 'course_target_site_mismatch', 'Catalog record does not match its canonical course identity.', $document_name . '.' . $key );
			}
		}

		$expected_keys = array_keys( $sites_by_key );
		sort( $expected_keys );
		$seen_unique = array_values( array_unique( $seen_keys ) );
		sort( $seen_unique );

		if ( $expected_keys !== $seen_unique || count( $seen_keys ) !== count( $seen_unique ) ) {
			$issues[] = new ValidationIssue( 'cross_contract_catalog_coverage', 'Catalog mapping must contain every active course exactly once.', $document_name . '.records' );
		}

		return $issues;
	}

	/**
	 * Normalize an identity term.
	 *
	 * @param string $term Canonical name or alias.
	 */
	private function normalize_term( string $term ): string {
		$normalized = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', strtolower( trim( $term ) ) );

		return null === $normalized ? '' : trim( $normalized );
	}
}
