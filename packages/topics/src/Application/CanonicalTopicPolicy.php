<?php
/**
 * Canonical topic policy index.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Application;

use Atal\Contracts\Data\KnowledgePackage;

/**
 * Reads Task 01 contracts instead of restating catalog business rules.
 */
final class CanonicalTopicPolicy {

	/**
	 * Canonical courses keyed by course key.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $courses = array();

	/**
	 * Syllabus records keyed by course key.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $syllabus = array();

	/**
	 * Search intents keyed by intent key.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $intents = array();

	/**
	 * Canonical course URLs.
	 *
	 * @var array<string,string>
	 */
	private array $course_urls = array();

	/**
	 * Internal-link records.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $link_records = array();

	/**
	 * Utility links by target site and key.
	 *
	 * @var array<string,array<string,string>>
	 */
	private array $utility_links = array();

	/**
	 * Canonical blocked claims.
	 *
	 * @var list<array<string,mixed>>
	 */
	private array $claims;

	/**
	 * Build the canonical policy index.
	 *
	 * @param KnowledgePackage $knowledge Validated package.
	 */
	public function __construct( private readonly KnowledgePackage $knowledge ) {
		$this->index();
	}

	/**
	 * Find one canonical course.
	 *
	 * @param string $course_key Canonical course key.
	 *
	 * @return array<string,mixed>|null
	 */
	public function course( string $course_key ): ?array {
		return $this->courses[ $course_key ] ?? null;
	}

	/**
	 * Determine whether an intent is approved.
	 *
	 * @param string $intent Intent key.
	 */
	public function intent_exists( string $intent ): bool {
		return isset( $this->intents[ $intent ] );
	}

	/**
	 * Return required canonical facts for an intent.
	 *
	 * @param string $intent Intent key.
	 *
	 * @return list<string>
	 */
	public function required_facts( string $intent ): array {
		$value = $this->intents[ $intent ]['required_facts'] ?? array();

		return $this->string_list( $value );
	}

	/**
	 * Explain a syllabus-specific block.
	 *
	 * @param string $course_key Canonical course key.
	 * @param string $intent     Intent key.
	 */
	public function blocked_intent_reason( string $course_key, string $intent ): ?string {
		$record  = $this->syllabus[ $course_key ] ?? array();
		$blocked = $this->string_list( $record['blocked_intents'] ?? array() );
		if ( ! in_array( $intent, $blocked, true ) ) {
			return null;
		}

		return sprintf( 'The approved %s data is missing for %s; only this syllabus-specific intent is blocked.', $intent, $course_key );
	}

	/**
	 * Resolve the mapped same-site internal link.
	 *
	 * @param string $course_key  Canonical course key.
	 * @param string $target_site Canonical target site.
	 */
	public function expected_internal_link( string $course_key, string $target_site ): ?string {
		$record = $this->link_records[ $course_key ] ?? array();
		$key    = $record['primary_link_key'] ?? null;
		if ( 'course_url_map' === $key ) {
			return $this->course_urls[ $course_key ] ?? null;
		}

		return is_string( $key ) ? ( $this->utility_links[ $target_site ][ $key ] ?? null ) : null;
	}

	/**
	 * Return blocked-claim examples for one site.
	 *
	 * @param string $target_site Canonical target site.
	 *
	 * @return list<string>
	 */
	public function blocked_claim_examples( string $target_site ): array {
		$examples = array();
		foreach ( $this->claims as $claim ) {
			$sites = $this->string_list( $claim['target_sites'] ?? array() );
			if ( ! in_array( $target_site, $sites, true ) ) {
				continue;
			}
			$examples = array_merge( $examples, $this->string_list( $claim['examples'] ?? array() ) );
		}

		return array_values( array_unique( $examples ) );
	}

	/**
	 * Return courses for one site in approved catalog order.
	 *
	 * @param string $target_site Canonical target site.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function courses_for_site( string $target_site ): array {
		return array_values(
			array_filter(
				$this->courses,
				static fn ( array $course ): bool => ( $course['target_site'] ?? null ) === $target_site
			)
		);
	}

	/** Build all read-only indexes. */
	private function index(): void {
		foreach ( array( 'institute_courses', 'diploma_courses' ) as $document_name ) {
			$document = $this->knowledge->document( $document_name );
			foreach ( $this->object_list( $document['courses'] ?? array() ) as $course ) {
				$key = $course['course_key'] ?? null;
				if ( is_string( $key ) ) {
					$this->courses[ $key ] = $course;
				}
			}
		}

		$syllabus = $this->knowledge->document( 'syllabus' );
		foreach ( $this->object_list( $syllabus['records'] ?? array() ) as $record ) {
			$key = $record['course_key'] ?? null;
			if ( is_string( $key ) ) {
				$this->syllabus[ $key ] = $record;
			}
		}

		$intents = $this->knowledge->document( 'intents' );
		foreach ( $this->object_list( $intents['intents'] ?? array() ) as $intent ) {
			$key = $intent['intent_key'] ?? null;
			if ( is_string( $key ) ) {
				$this->intents[ $key ] = $intent;
			}
		}

		$urls = $this->knowledge->document( 'course_urls' );
		foreach ( $this->object_list( $urls['records'] ?? array() ) as $record ) {
			$key = $record['course_key'] ?? null;
			$url = $record['canonical_url'] ?? null;
			if ( is_string( $key ) && is_string( $url ) ) {
				$this->course_urls[ $key ] = $url;
			}
		}

		$links = $this->knowledge->document( 'internal_links' );
		foreach ( $this->object_list( $links['records'] ?? array() ) as $record ) {
			$key = $record['course_key'] ?? null;
			if ( is_string( $key ) ) {
				$this->link_records[ $key ] = $record;
			}
		}
		$utility = $links['utility_links'] ?? array();
		if ( is_array( $utility ) ) {
			foreach ( $utility as $site => $records ) {
				if ( ! is_string( $site ) ) {
					continue;
				}
				$this->utility_links[ $site ] = array();
				foreach ( $this->object_list( $records ) as $record ) {
					$key = $record['link_key'] ?? null;
					$url = $record['url'] ?? null;
					if ( is_string( $key ) && is_string( $url ) ) {
						$this->utility_links[ $site ][ $key ] = $url;
					}
				}
			}
		}

		$claims       = $this->knowledge->document( 'blocked_claims' );
		$this->claims = $this->object_list( $claims['claims'] ?? array() );
	}

	/**
	 * Filter an object list.
	 *
	 * @param mixed $value Candidate value.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function object_list( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$output = array();
		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$record = array();
			foreach ( $item as $key => $field ) {
				if ( is_string( $key ) ) {
					$record[ $key ] = $field;
				}
			}
			$output[] = $record;
		}

		return $output;
	}

	/**
	 * Filter a string list.
	 *
	 * @param mixed $value Candidate value.
	 *
	 * @return list<string>
	 */
	private function string_list( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( $value, 'is_string' ) );
	}
}
