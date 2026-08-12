<?php
/**
 * WordPress canonical knowledge repository.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress;

use Atal\SeoFactory\Domain\Knowledge\CourseRecord;
use Atal\SeoFactory\Domain\Knowledge\TopicRecord;
use Atal\SeoFactory\Domain\Storage\KnowledgeRepositoryInterface;
use Atal\SeoFactory\Domain\Storage\RowStoreInterface;
use Atal\SeoFactory\Infrastructure\Database\TableNames;

/**
 * Persists imported records without creating posts or touching existing content.
 */
final class WpdbKnowledgeRepository implements KnowledgeRepositoryInterface {

	public function __construct(
		private readonly RowStoreInterface $rows,
		private readonly TableNames $tables
	) {
	}

	public function course_hash( string $course_key ): ?string {
		return $this->rows->find_source_hash( $this->tables->courses(), 'course_key', $course_key );
	}

	public function topic_hash( string $topic_key ): ?string {
		return $this->rows->find_source_hash( $this->tables->topics(), 'topic_key', $topic_key );
	}

	public function upsert_course( CourseRecord $course ): void {
		$now  = gmdate( 'Y-m-d H:i:s' );
		$data = array(
			'target_site'      => $course->target_site(),
			'canonical_name'   => $course->canonical_name(),
			'payload_json'     => $course->payload_json(),
			'source_hash'      => $course->source_hash(),
			'contract_version' => $course->contract_version(),
			'updated_at'       => $now,
		);

		if ( null === $this->course_hash( $course->course_key() ) ) {
			$data['created_at'] = $now;
		}

		$this->rows->upsert_row( $this->tables->courses(), 'course_key', $course->course_key(), $data, array_fill( 0, count( $data ), '%s' ) );
	}

	public function upsert_topic( TopicRecord $topic ): void {
		$now  = gmdate( 'Y-m-d H:i:s' );
		$data = array(
			'course_key'       => $topic->course_key(),
			'target_site'      => $topic->target_site(),
			'title'            => $topic->title(),
			'payload_json'     => $topic->payload_json(),
			'source_hash'      => $topic->source_hash(),
			'contract_version' => $topic->contract_version(),
			'updated_at'       => $now,
		);

		if ( null === $this->topic_hash( $topic->topic_key() ) ) {
			$data['created_at'] = $now;
		}

		$this->rows->upsert_row( $this->tables->topics(), 'topic_key', $topic->topic_key(), $data, array_fill( 0, count( $data ), '%s' ) );
	}
}
