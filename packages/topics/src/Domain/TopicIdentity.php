<?php
/**
 * Deterministic topic identity.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Domain;

use InvalidArgumentException;

/**
 * Identifies one topic without relying on a random value.
 */
final class TopicIdentity {

	/**
	 * Normalized primary keyword.
	 *
	 * @var string
	 */
	private readonly string $primary_keyword;

	/**
	 * Create an immutable identity.
	 *
	 * @param string $target_site     Canonical target site.
	 * @param string $course_key      Canonical course key.
	 * @param string $intent          Approved intent key.
	 * @param string $primary_keyword Primary keyword.
	 * @param int    $year            Topic year.
	 *
	 * @throws InvalidArgumentException When an identity value is invalid.
	 */
	public function __construct(
		private readonly string $target_site,
		private readonly string $course_key,
		private readonly string $intent,
		string $primary_keyword,
		private readonly int $year
	) {
		if ( ! in_array( $target_site, array( 'atal_institute', 'atal_diploma' ), true ) ) {
			throw new InvalidArgumentException( 'Unknown target site.' );
		}

		if ( 1 !== preg_match( '/^[a-z0-9_]+$/', $course_key ) || 1 !== preg_match( '/^[a-z0-9_]+$/', $intent ) ) {
			throw new InvalidArgumentException( 'Course key and intent must use canonical identifiers.' );
		}

		$this->primary_keyword = self::normalize_text( $primary_keyword );
		if ( '' === $this->primary_keyword ) {
			throw new InvalidArgumentException( 'Primary keyword is required.' );
		}

		if ( $year < 2020 || $year > 2100 ) {
			throw new InvalidArgumentException( 'Topic year is outside the supported range.' );
		}
	}

	/** Return the deterministic topic key. */
	public function key(): string {
		$canonical = implode( "\n", array( $this->target_site, $this->course_key, $this->intent, $this->primary_keyword, (string) $this->year ) );

		return 'topic_' . substr( hash( 'sha256', $canonical ), 0, 40 );
	}

	/** Return the canonical target site. */
	public function target_site(): string {
		return $this->target_site;
	}

	/** Return the canonical course key. */
	public function course_key(): string {
		return $this->course_key;
	}

	/** Return the approved intent key. */
	public function intent(): string {
		return $this->intent;
	}

	/** Return the normalized primary keyword. */
	public function primary_keyword(): string {
		return $this->primary_keyword;
	}

	/** Return the topic year. */
	public function year(): int {
		return $this->year;
	}

	/**
	 * Export the identity.
	 *
	 * @return array{target_site:string,course_key:string,intent:string,primary_keyword:string,year:int,topic_key:string}
	 */
	public function to_array(): array {
		return array(
			'target_site'     => $this->target_site,
			'course_key'      => $this->course_key,
			'intent'          => $this->intent,
			'primary_keyword' => $this->primary_keyword,
			'year'            => $this->year,
			'topic_key'       => $this->key(),
		);
	}

	/**
	 * Normalize a human-readable identity value.
	 *
	 * @param string $value Raw value.
	 */
	public static function normalize_text( string $value ): string {
		$normalized = preg_replace( '/\s+/u', ' ', trim( strtolower( $value ) ) );

		return is_string( $normalized ) ? $normalized : '';
	}
}
