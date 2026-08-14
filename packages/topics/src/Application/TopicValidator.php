<?php
/**
 * Canonical topic quality gates.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Application;

use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\Topics\Contract\TopicRegistryInterface;
use Atal\Topics\Domain\PublishedTopic;
use Atal\Topics\Domain\QualityState;
use Atal\Topics\Domain\TopicIdentity;
use Atal\Topics\Domain\TopicProposal;
use Atal\Topics\Domain\ValidationFinding;
use Atal\Topics\Domain\ValidationReport;

/**
 * Applies local, explainable gates using the approved Task 01 package.
 */
final class TopicValidator {

	/**
	 * Create the deterministic validator.
	 *
	 * @param CanonicalTopicPolicy   $policy              Canonical policy index.
	 * @param KnowledgeValidator     $knowledge_validator Task 01 validator.
	 * @param TopicRegistryInterface $registry            Published topic registry.
	 * @param Similarity             $similarity          Local similarity service.
	 * @param string                 $master_directory    Master-data directory.
	 * @param string                 $schema_directory    Schema directory.
	 */
	public function __construct(
		private readonly CanonicalTopicPolicy $policy,
		private readonly KnowledgeValidator $knowledge_validator,
		private readonly TopicRegistryInterface $registry,
		private readonly Similarity $similarity,
		private readonly string $master_directory,
		private readonly string $schema_directory
	) {
	}

	/**
	 * Validate one non-publishing topic proposal.
	 *
	 * @param TopicProposal $proposal Topic proposal.
	 */
	public function validate( TopicProposal $proposal ): ValidationReport {
		$findings = array();
		$identity = $proposal->identity();
		$course   = $this->policy->course( $identity->course_key() );
		$valid    = $this->knowledge_validator->validate( KnowledgePackage::from_directory( $this->master_directory ), $this->schema_directory );
		$this->finding(
			$findings,
			'topic.knowledge.valid',
			$valid->is_valid() ? QualityState::PASS : QualityState::REJECTED,
			'knowledge_package',
			'valid Task 01 canonical package',
			$valid->is_valid() ? 'valid' : 'invalid',
			'All topic decisions must derive from validated canonical contracts.',
			'Repair the canonical package before validating topics.'
		);

		$course_state = null === $course ? QualityState::REJECTED : QualityState::PASS;
		$this->finding( $findings, 'topic.course.exists', $course_state, 'course_key', 'active canonical course', $identity->course_key(), 'Aliases cannot create competing active records.', 'Use an active canonical course_key.' );
		if ( null === $course ) {
			return new ValidationReport( $findings );
		}

		$canonical_site = is_string( $course['target_site'] ?? null ) ? $course['target_site'] : '';
		$this->finding( $findings, 'topic.site.identity', $canonical_site === $identity->target_site() ? QualityState::PASS : QualityState::REJECTED, 'target_site', $canonical_site, $identity->target_site(), 'Institute and Diploma identities must never mix.', 'Use the course owner target_site.' );
		$this->finding( $findings, 'topic.intent.exists', $this->policy->intent_exists( $identity->intent() ) ? QualityState::PASS : QualityState::REJECTED, 'intent', 'approved search intent', $identity->intent(), 'Only approved taxonomy intents are accepted.', 'Select an intent from 07-SEARCH-INTENT-TAXONOMY.json.' );

		$block_reason = $this->policy->blocked_intent_reason( $identity->course_key(), $identity->intent() );
		$this->finding( $findings, 'topic.missing_data.scope', null === $block_reason ? QualityState::PASS : QualityState::REJECTED, 'intent', 'intent allowed by available canonical data', $identity->intent(), $block_reason ?? 'No syllabus-specific block applies.', 'Choose a non-blocked intent; do not invent the missing syllabus or assessment.' );

		$this->validate_facts( $proposal, $course, $findings );
		$this->validate_eligibility( $proposal, $course, $findings );
		$this->validate_claims( $proposal, $findings );
		$this->validate_alignment( $proposal, $findings );
		$this->validate_links( $proposal, $findings );
		$this->validate_structure( $proposal, $findings );
		$this->validate_registry( $proposal, $findings );

		return new ValidationReport( $findings );
	}

	/**
	 * Validate canonical fee and duration values.
	 *
	 * @param TopicProposal       $proposal Topic proposal.
	 * @param array<string,mixed> $course   Canonical course.
	 * @param array               $findings Finding accumulator.
	 * @phpstan-param list<ValidationFinding> $findings
	 */
	private function validate_facts( TopicProposal $proposal, array $course, array &$findings ): void {
		$facts    = $proposal->facts();
		$required = $this->policy->required_facts( $proposal->identity()->intent() );
		foreach ( array( 'duration', 'fee' ) as $fact ) {
			$fact_data = $course[ $fact ] ?? null;
			$canonical = is_array( $fact_data ) && is_string( $fact_data['display'] ?? null ) ? $fact_data['display'] : null;
			$actual    = $facts[ $fact ] ?? null;
			if ( in_array( $fact, $required, true ) && ! is_string( $actual ) ) {
				$this->finding( $findings, 'topic.fact.' . $fact, QualityState::REJECTED, 'facts.' . $fact, is_string( $canonical ) ? $canonical : 'canonical value', 'missing', 'This intent requires the canonical fact.', 'Add the exact canonical display value and source-backed fact.' );
				continue;
			}
			if ( is_string( $actual ) ) {
				$this->finding( $findings, 'topic.fact.' . $fact, $actual === $canonical ? QualityState::PASS : QualityState::REJECTED, 'facts.' . $fact, is_string( $canonical ) ? $canonical : '', $actual, 'Fact values must exactly match the canonical course master.', 'Replace with the canonical display value.' );
			}
		}
	}

	/**
	 * Validate site-specific eligibility rules.
	 *
	 * @param TopicProposal       $proposal Topic proposal.
	 * @param array<string,mixed> $course   Canonical course.
	 * @param array               $findings Finding accumulator.
	 * @phpstan-param list<ValidationFinding> $findings
	 */
	private function validate_eligibility( TopicProposal $proposal, array $course, array &$findings ): void {
		$facts       = $proposal->facts();
		$eligibility = $facts['eligibility'] ?? '';
		$content     = TopicIdentity::normalize_text( $proposal->searchable_content() . ' ' . $eligibility );
		$mentions    = 1 === preg_match( '/\b(eligibility|eligible|qualification|10th pass|12th pass|graduate pass)\b/u', $content );
		$site        = $proposal->identity()->target_site();
		if ( 'atal_institute' === $site ) {
			$this->finding( $findings, 'topic.institute.eligibility_omit', $mentions ? QualityState::REJECTED : QualityState::PASS, 'content', 'eligibility omitted', $mentions ? 'eligibility wording detected' : 'omitted', 'Normal ATAL Institute promotional content must omit eligibility.', 'Remove eligibility wording; do not add a disclaimer.' );
			return;
		}

		if ( ! $mentions ) {
			return;
		}

		$allowed_intents  = array( 'course_overview', 'eligibility', 'admission' );
		$intent_allowed   = in_array( $proposal->identity()->intent(), $allowed_intents, true );
		$eligibility_data = $course['eligibility'] ?? null;
		$criteria         = is_array( $eligibility_data ) && is_array( $eligibility_data['criteria'] ?? null ) ? $eligibility_data['criteria'] : array();
		$canonical        = implode( ', ', array_filter( $criteria, 'is_string' ) );
		$actual           = $facts['eligibility'] ?? '';
		$matches          = '' !== $canonical && $canonical === $actual;
		$this->finding( $findings, 'topic.diploma.eligibility_scope', $intent_allowed && $matches ? QualityState::PASS : QualityState::REJECTED, 'facts.eligibility', $canonical . ' in an eligibility-bearing intent', $actual, 'Diploma eligibility is course-specific and may appear only in an intended context.', 'Use the exact course criterion in course_overview, eligibility, or admission content.' );
	}

	/**
	 * Reject canonical blocked claims.
	 *
	 * @param TopicProposal $proposal Topic proposal.
	 * @param array         $findings Finding accumulator.
	 * @phpstan-param list<ValidationFinding> $findings
	 */
	private function validate_claims( TopicProposal $proposal, array &$findings ): void {
		$content = TopicIdentity::normalize_text( $proposal->searchable_content() );
		$matched = array();
		foreach ( $this->policy->blocked_claim_examples( $proposal->identity()->target_site() ) as $example ) {
			if ( str_contains( $content, TopicIdentity::normalize_text( $example ) ) ) {
				$matched[] = $example;
			}
		}
		$this->finding( $findings, 'topic.claims.blocked', array() === $matched ? QualityState::PASS : QualityState::REJECTED, 'content', 'no blocked or unsupported claim', implode( ', ', $matched ), 'Unsupported doctor, licence, clinic, government, approval, or job-guarantee claims are prohibited.', 'Remove the claim without introducing negative disclaimer wording.' );
	}

	/**
	 * Validate primary-keyword title and heading alignment.
	 *
	 * @param TopicProposal $proposal Topic proposal.
	 * @param array         $findings Finding accumulator.
	 * @phpstan-param list<ValidationFinding> $findings
	 */
	private function validate_alignment( TopicProposal $proposal, array &$findings ): void {
		$keyword  = $proposal->identity()->primary_keyword();
		$title    = TopicIdentity::normalize_text( $proposal->title() );
		$headings = TopicIdentity::normalize_text( implode( ' ', $proposal->headings() ) );
		$aligned  = str_contains( $title, $keyword ) && str_contains( $headings, $keyword );
		$this->finding( $findings, 'topic.keyword.alignment', $aligned ? QualityState::PASS : QualityState::REJECTED, 'title,headings', $keyword, $proposal->title(), 'The primary keyword must align with the title and heading plan.', 'Use the exact approved primary keyword naturally in the title and one heading.' );
	}

	/**
	 * Validate same-site internal links.
	 *
	 * @param TopicProposal $proposal Topic proposal.
	 * @param array         $findings Finding accumulator.
	 * @phpstan-param list<ValidationFinding> $findings
	 */
	private function validate_links( TopicProposal $proposal, array &$findings ): void {
		$site     = $proposal->identity()->target_site();
		$expected = $this->policy->expected_internal_link( $proposal->identity()->course_key(), $site );
		$links    = $proposal->internal_links();
		$present  = is_string( $expected ) && in_array( $expected, $links, true );
		$wrong    = false;
		foreach ( $links as $link ) {
			$host = wp_parse_url( $link, PHP_URL_HOST );
			if ( is_string( $host ) && ( ( 'atal_institute' === $site && 'ataldiploma.com' === $host ) || ( 'atal_diploma' === $site && 'atalinstitute.com' === $host ) ) ) {
				$wrong = true;
			}
		}
		$status = $present && ! $wrong ? QualityState::PASS : QualityState::REJECTED;
		$this->finding( $findings, 'topic.internal_link', $status, 'internal_links', is_string( $expected ) ? $expected : 'canonical same-site link', implode( ', ', $links ), 'Every topic needs its mapped internal link and must not cross site identities.', 'Add the mapped same-site URL and remove cross-site URLs.' );
	}

	/**
	 * Validate conclusion position and repeated filler.
	 *
	 * @param TopicProposal $proposal Topic proposal.
	 * @param array         $findings Finding accumulator.
	 * @phpstan-param list<ValidationFinding> $findings
	 */
	private function validate_structure( TopicProposal $proposal, array &$findings ): void {
		$headings = $proposal->headings();
		$last     = array() === $headings ? '' : (string) end( $headings );
		$is_last  = 1 === preg_match( '/\bconclusion\b/i', $last );
		$this->finding( $findings, 'topic.conclusion.last', $is_last ? QualityState::PASS : QualityState::REJECTED, 'headings', 'Conclusion as final section', $last, 'Conclusion must remain the last section.', 'Move the Conclusion heading and its content to the end.' );

		$normalized = array_map( static fn ( string $paragraph ): string => TopicIdentity::normalize_text( $paragraph ), $proposal->paragraphs() );
		$counts     = array_count_values( array_filter( $normalized ) );
		$repeated   = array_keys( array_filter( $counts, static fn ( int $count ): bool => $count > 1 ) );
		$this->finding( $findings, 'topic.generic_filler', array() === $repeated ? QualityState::PASS : QualityState::REJECTED, 'paragraphs', 'distinct useful paragraphs', implode( ' | ', $repeated ), 'Repeated filler is deterministic duplicate content.', 'Replace repeated paragraphs with course-specific, canonical information.' );

		$word_count = str_word_count( implode( ' ', $proposal->paragraphs() ) );
		$this->finding( $findings, 'topic.content.depth', $word_count < 20 ? QualityState::NEEDS_REVIEW : QualityState::PASS, 'paragraphs', 'at least 20 words in the preview evidence', (string) $word_count, 'Very thin preview content needs owner review even when no hard rule is violated.', 'Add useful course-specific material backed by canonical facts.' );
	}

	/**
	 * Validate registry uniqueness and semantic similarity.
	 *
	 * @param TopicProposal $proposal Topic proposal.
	 * @param array         $findings Finding accumulator.
	 * @phpstan-param list<ValidationFinding> $findings
	 */
	private function validate_registry( TopicProposal $proposal, array &$findings ): void {
		foreach ( $this->registry->all() as $published ) {
			$other    = $published->proposal();
			$identity = $other->identity();
			if ( $identity->key() === $proposal->identity()->key() ) {
				continue;
			}
			if ( $identity->target_site() !== $proposal->identity()->target_site() ) {
				continue;
			}

			$published_path = wp_parse_url( $published->published_url(), PHP_URL_PATH );
			$published_slug = is_string( $published_path ) ? trim( $published_path, '/' ) : '';
			if ( $other->slug() === $proposal->slug() || ( '' !== $published_slug && $published_slug === $proposal->slug() ) ) {
				$this->finding( $findings, 'topic.url.collision', QualityState::REJECTED, 'slug', 'unique site URL/slug', $proposal->slug(), 'The URL registry already owns this slug.', 'Choose a distinct canonical slug.' );
			}
			if ( $identity->primary_keyword() === $proposal->identity()->primary_keyword() && $identity->year() === $proposal->identity()->year() ) {
				$this->finding( $findings, 'topic.keyword.cannibalization', QualityState::REJECTED, 'primary_keyword', 'unique same-site keyword/year', $identity->primary_keyword(), 'Another registered topic targets the same keyword and year.', 'Consolidate into the registered topic or select a distinct approved intent.' );
			}
			$this->similarity_finding( $findings, 'topic.similarity.title', 'title', $proposal->title(), $other->title(), 0.70 );
			$this->similarity_finding( $findings, 'topic.similarity.heading', 'headings', implode( ' ', $proposal->headings() ), implode( ' ', $other->headings() ), 0.75 );
			$this->similarity_finding( $findings, 'topic.similarity.paragraph', 'paragraphs', implode( ' ', $proposal->paragraphs() ), implode( ' ', $other->paragraphs() ), 0.78 );
			$this->similarity_finding( $findings, 'topic.similarity.faq', 'faqs', implode( ' ', $proposal->faqs() ), implode( ' ', $other->faqs() ), 0.78 );
			$this->similarity_finding( $findings, 'topic.similarity.whole', 'content', $proposal->searchable_content(), $other->searchable_content(), 0.72 );
		}
	}

	/**
	 * Add a similarity rejection when a threshold is reached.
	 *
	 * @param array  $findings Finding accumulator.
	 * @param string $rule     Rule identifier.
	 * @param string $field    Affected field.
	 * @param string $left     Proposed text.
	 * @param string $right    Registered text.
	 * @param float  $threshold Rejection threshold.
	 * @phpstan-param list<ValidationFinding> $findings
	 */
	private function similarity_finding( array &$findings, string $rule, string $field, string $left, string $right, float $threshold ): void {
		$score = $this->similarity->score( $left, $right );
		if ( $score < $threshold ) {
			return;
		}
		$this->finding( $findings, $rule, QualityState::REJECTED, $field, 'similarity below ' . (string) $threshold, number_format( $score, 3, '.', '' ), 'Lightly rewritten duplicate content fails the semantic similarity gate.', 'Consolidate the topic or rewrite it around a distinct approved search intent.' );
	}

	/**
	 * Add one machine-readable finding.
	 *
	 * @param array  $findings       Finding accumulator.
	 * @param string $rule           Rule identifier.
	 * @param string $status         Quality state.
	 * @param string $field          Affected field.
	 * @param string $expected       Expected value.
	 * @param string $actual         Actual value.
	 * @param string $explanation    Human-readable evidence.
	 * @param string $safe_correction Safe correction.
	 * @phpstan-param list<ValidationFinding> $findings
	 */
	private function finding( array &$findings, string $rule, string $status, string $field, string $expected, string $actual, string $explanation, string $safe_correction ): void {
		$findings[] = new ValidationFinding( $rule, $status, $field, $expected, $actual, $explanation, $safe_correction );
	}
}
