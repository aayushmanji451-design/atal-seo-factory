<?php
/**
 * Validated knowledge-to-storage record factory.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Import;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;
use Atal\SeoFactory\Domain\Knowledge\CourseRecord;
use Atal\SeoFactory\Domain\Knowledge\RecordSet;
use Atal\SeoFactory\Domain\Knowledge\TopicRecord;
use JsonException;

/**
 * Maps already validated canonical data without adding business facts.
 */
final class KnowledgeRecordFactory {

	/**
	 * @throws JsonException When a validated payload cannot be encoded.
	 */
	public function create( KnowledgePackage $package ): RecordSet {
		$courses = $this->courses( $package );
		$topics  = $this->topics( $package );
		$hashes  = array();

		foreach ( $courses as $course ) {
			$hashes[] = $course->source_hash();
		}
		foreach ( $topics as $topic ) {
			$hashes[] = $topic->source_hash();
		}

		return new RecordSet( $courses, $topics, hash( 'sha256', implode( ':', $hashes ) ) );
	}

	/**
	 * @return list<CourseRecord>
	 * @throws JsonException When a validated payload cannot be encoded.
	 */
	private function courses( KnowledgePackage $package ): array {
		$result = array();

		foreach ( array( 'institute_courses', 'diploma_courses' ) as $document_name ) {
			$document         = $package->document( $document_name );
			$contract_version = JsonValue::string_field( $document, 'contract_version' );

			foreach ( JsonValue::object_list_field( $document, 'courses' ) as $course ) {
				$payload  = $this->encode( $course );
				$result[] = new CourseRecord(
					JsonValue::string_field( $course, 'course_key' ),
					JsonValue::string_field( $course, 'target_site' ),
					JsonValue::string_field( $course, 'canonical_name' ),
					$payload,
					hash( 'sha256', $payload ),
					$contract_version
				);
			}
		}

		return $result;
	}

	/**
	 * @return list<TopicRecord>
	 * @throws JsonException When a validated payload cannot be encoded.
	 */
	private function topics( KnowledgePackage $package ): array {
		$document         = $package->document( 'syllabus' );
		$contract_version = JsonValue::string_field( $document, 'contract_version' );
		$result           = array();

		foreach ( JsonValue::object_list_field( $document, 'records' ) as $record ) {
			$syllabus = JsonValue::object_field( $record, 'syllabus' );
			if ( ! array_key_exists( 'topics', $syllabus ) ) {
				continue;
			}

			$course_key  = JsonValue::string_field( $record, 'course_key' );
			$target_site = JsonValue::string_field( $record, 'target_site' );
			foreach ( JsonValue::string_list_field( $syllabus, 'topics' ) as $title ) {
				$topic_key = 'topic_' . substr( hash( 'sha256', $course_key . "\0" . $title ), 0, 32 );
				$payload   = $this->encode(
					array(
						'topic_key'   => $topic_key,
						'course_key'  => $course_key,
						'target_site' => $target_site,
						'title'       => $title,
					)
				);
				$result[]  = new TopicRecord( $topic_key, $course_key, $target_site, $title, $payload, hash( 'sha256', $payload ), $contract_version );
			}
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $payload Validated canonical payload.
	 *
	 * @throws JsonException When the payload cannot be encoded.
	 */
	private function encode( array $payload ): string {
		return json_encode( $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
