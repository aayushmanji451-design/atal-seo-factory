<?php
/**
 * Topic validation input.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Domain;

use InvalidArgumentException;

/**
 * Immutable, non-publishing topic and content proposal.
 */
final class TopicProposal {

	/**
	 * Build a proposal from a bounded admin or test payload.
	 *
	 * @param array $data Payload.
	 * @phpstan-param array<mixed,mixed> $data
	 *
	 * @throws InvalidArgumentException When a required value is missing or invalid.
	 */
	public static function from_array( array $data ): self {
		$site     = $data['target_site'] ?? null;
		$course   = $data['course_key'] ?? null;
		$intent   = $data['intent'] ?? null;
		$keyword  = $data['primary_keyword'] ?? null;
		$year     = $data['year'] ?? null;
		$title    = $data['title'] ?? null;
		$slug     = $data['slug'] ?? null;
		$headings = self::string_list( $data['headings'] ?? array() );
		$sections = self::string_list( $data['paragraphs'] ?? array() );
		$faqs     = self::string_list( $data['faqs'] ?? array() );
		$links    = self::string_list( $data['internal_links'] ?? array() );
		$facts    = self::string_map( $data['facts'] ?? array() );

		if ( ! is_string( $site ) || ! is_string( $course ) || ! is_string( $intent ) || ! is_string( $keyword ) || ! is_numeric( $year ) || ! is_string( $title ) || ! is_string( $slug ) ) {
			throw new InvalidArgumentException( 'Topic payload is missing a required scalar field.' );
		}

		return new self( new TopicIdentity( $site, $course, $intent, $keyword, (int) $year ), $title, $slug, $headings, $sections, $faqs, $links, $facts );
	}

	/**
	 * Create an immutable proposal.
	 *
	 * @param TopicIdentity        $identity       Deterministic identity.
	 * @param string               $title          Proposed title.
	 * @param string               $slug           Proposed slug.
	 * @param array                $headings       Ordered headings.
	 * @param array                $paragraphs     Ordered paragraphs.
	 * @param array                $faqs           Flattened question-and-answer text.
	 * @param array                $internal_links Proposed internal URLs.
	 * @param array<string,string> $facts          Explicit canonical facts.
	 * @phpstan-param list<string> $headings
	 * @phpstan-param list<string> $paragraphs
	 * @phpstan-param list<string> $faqs
	 * @phpstan-param list<string> $internal_links
	 *
	 * @throws InvalidArgumentException When a title or slug is empty.
	 */
	public function __construct(
		private readonly TopicIdentity $identity,
		private readonly string $title,
		private readonly string $slug,
		private readonly array $headings,
		private readonly array $paragraphs,
		private readonly array $faqs,
		private readonly array $internal_links,
		private readonly array $facts
	) {
		if ( '' === trim( $title ) || '' === trim( $slug ) ) {
			throw new InvalidArgumentException( 'A topic title and slug are required.' );
		}
	}

	/** Return the deterministic identity. */
	public function identity(): TopicIdentity {
		return $this->identity;
	}

	/** Return the proposed title. */
	public function title(): string {
		return $this->title;
	}

	/** Return the normalized slug. */
	public function slug(): string {
		return strtolower( trim( $this->slug, " \t\n\r\0\x0B/" ) );
	}

	/**
	 * Return ordered headings.
	 *
	 * @return list<string>
	 */
	public function headings(): array {
		return $this->headings;
	}

	/**
	 * Return ordered paragraphs.
	 *
	 * @return list<string>
	 */
	public function paragraphs(): array {
		return $this->paragraphs;
	}

	/**
	 * Return flattened FAQ text.
	 *
	 * @return list<string>
	 */
	public function faqs(): array {
		return $this->faqs;
	}

	/**
	 * Return proposed internal links.
	 *
	 * @return list<string>
	 */
	public function internal_links(): array {
		return $this->internal_links;
	}

	/**
	 * Return explicit facts.
	 *
	 * @return array<string,string>
	 */
	public function facts(): array {
		return $this->facts;
	}

	/** Return all content that is safe to inspect locally. */
	public function searchable_content(): string {
		return implode( "\n", array_merge( array( $this->title ), $this->headings, $this->paragraphs, $this->faqs ) );
	}

	/**
	 * Export the proposal.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array_merge(
			$this->identity->to_array(),
			array(
				'title'          => $this->title,
				'slug'           => $this->slug(),
				'headings'       => $this->headings,
				'paragraphs'     => $this->paragraphs,
				'faqs'           => $this->faqs,
				'internal_links' => $this->internal_links,
				'facts'          => $this->facts,
			)
		);
	}

	/**
	 * Filter one string list.
	 *
	 * @param mixed $value Candidate value.
	 *
	 * @return list<string>
	 */
	private static function string_list( mixed $value ): array {
		return is_array( $value ) ? array_values( array_filter( $value, 'is_string' ) ) : array();
	}

	/**
	 * Filter one string map.
	 *
	 * @param mixed $value Candidate value.
	 *
	 * @return array<string,string>
	 */
	private static function string_map( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$output = array();
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && is_string( $item ) ) {
				$output[ $key ] = $item;
			}
		}

		return $output;
	}
}
