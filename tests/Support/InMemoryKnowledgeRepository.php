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

	/** @var array<string,string> */
	private array $courses = array();

	/** @var array<string,string> */
	private array $topics = array();

	private int $writes = 0;

	private ?int $fail_on_write = null;

	public function course_hash( string $course_key ): ?string {
		return $this->courses[ $course_key ] ?? null;
	}

	public function topic_hash( string $topic_key ): ?string {
		return $this->topics[ $topic_key ] ?? null;
	}

	public function upsert_course( CourseRecord $course ): void {
		$this->before_write();
		$this->courses[ $course->course_key() ] = $course->source_hash();
	}

	public function upsert_topic( TopicRecord $topic ): void {
		$this->before_write();
		$this->topics[ $topic->topic_key() ] = $topic->source_hash();
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

	private function before_write(): void {
		++$this->writes;
		if ( $this->fail_on_write === $this->writes ) {
			throw new RuntimeException( 'Injected canonical storage failure.' );
		}
	}
}
