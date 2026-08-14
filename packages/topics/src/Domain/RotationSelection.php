<?php
/**
 * Deterministic rotation result.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Domain;

/**
 * Selected course and explicit skipped-course evidence.
 */
final class RotationSelection {

	/**
	 * Create a rotation result.
	 *
	 * @param string                                       $course_key    Selected course key.
	 * @param int                                          $cursor_before Starting cursor.
	 * @param int                                          $cursor_after  Next cursor.
	 * @param list<array{course_key:string,reason:string}> $skipped       Skipped entries.
	 */
	public function __construct(
		private readonly string $course_key,
		private readonly int $cursor_before,
		private readonly int $cursor_after,
		private readonly array $skipped
	) {
	}

	/** Return the selected course key. */
	public function course_key(): string {
		return $this->course_key;
	}

	/** Return the next cursor. */
	public function cursor_after(): int {
		return $this->cursor_after;
	}

	/**
	 * Export the rotation selection.
	 *
	 * @return array{course_key:string,cursor_before:int,cursor_after:int,skipped:list<array{course_key:string,reason:string}>}
	 */
	public function to_array(): array {
		return array(
			'course_key'    => $this->course_key,
			'cursor_before' => $this->cursor_before,
			'cursor_after'  => $this->cursor_after,
			'skipped'       => $this->skipped,
		);
	}
}
