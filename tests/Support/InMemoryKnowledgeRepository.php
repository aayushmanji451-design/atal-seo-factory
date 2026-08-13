<?php
/**
 * In-memory canonical knowledge repository.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Support;

use Atal\SeoFactory\Domain\Knowledge\CourseRecord;
use Atal\SeoFactory\Domain\Knowledge\TopicRecord;
use Atal\SeoFactory\Domain\Storage\KnowledgeRepositoryInterface;
use RuntimeException;

/**
 * Captures canonical writes and can inject a persistence failure.
 */
final class InMemoryKnowledgeRepository implements KnowledgeRepositoryInterface {

	/** @var array<string,CourseRecord> */
	private array $courses = array();

	/** @var array<string,TopicRecord> */
	private array $topics = array();

	private int $writes = 0;

	private ?int $fail_on_write = null;

	public function course_hash( string $course_key ): ?string {
		return isset( $this->courses[ $course_key ] ) ? $this->courses[ $course_key ]->source_hash() : null;
	}

	public function topic_hash( string $topic_key ): ?string {
		return isset( $this->topics[ $topic_key ] ) ? $this->topics[ $topic_key ]->source_hash() : null;
	}

	public function upsert_course( CourseRecord $course ): void {
		$this->before_write();
		$this->courses[ $course->course_key() ] = $course;
	}

	public function upsert_topic( TopicRecord $topic ): void {
		$this->before_write();
		$this->topics[ $topic->topic_key() ] = $topic;
	}

	public function fail_on_write( int $write_number ): void {
		$this->fail_on_write = $write_number;
	}

	public function writes(): int {
		return $this->writes;
	}

	public function course_count(): int {
		return count( $this->courses );
	}

	public function topic_count(): int {
		return count( $this->topics );
	}

	/**
	 * @return list<CourseRecord>
	 */
	public function courses(): array {
		return array_values( $this->courses );
	}

	/**
	 * @return array{courses:array<string,CourseRecord>,topics:array<string,TopicRecord>}
	 */
	public function snapshot(): array {
		return array(
			'courses' => $this->courses,
			'topics'  => $this->topics,
		);
	}

	/**
	 * @param array{courses:array<string,CourseRecord>,topics:array<string,TopicRecord>} $snapshot Transaction-start state.
	 */
	public function restore( array $snapshot ): void {
		$this->courses = $snapshot['courses'];
		$this->topics  = $snapshot['topics'];
	}

	private function before_write(): void {
		++$this->writes;
		if ( $this->fail_on_write === $this->writes ) {
			throw new RuntimeException( 'Injected canonical storage failure.' );
		}
	}
}
