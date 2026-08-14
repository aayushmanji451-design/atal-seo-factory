<?php
/**
 * Canonical quality-gate tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Topics;

use Atal\Topics\Domain\PublishedTopic;
use Atal\Topics\Domain\QualityState;

/**
 * Covers canonical facts, site identity, missing data, content, and duplicates.
 */
final class TopicValidatorTest extends TopicTestCase {

	public function test_valid_institute_overview_passes_with_explainable_findings(): void {
		$report = $this->validator->validate( $this->proposal( 'institute_general_duty_assistant' ) );

		self::assertSame( QualityState::PASS, $report->state() );
		self::assertNotEmpty( $report->findings() );
		foreach ( $report->to_array()['findings'] as $finding ) {
			self::assertSame( array( 'rule_id', 'status', 'field', 'expected', 'actual', 'explanation', 'safe_correction' ), array_keys( $finding ) );
		}
	}

	public function test_cross_site_identity_and_noncanonical_fact_are_rejected(): void {
		$proposal = $this->proposal(
			'institute_general_duty_assistant',
			'course_overview',
			array(
				'target_site' => 'atal_diploma',
				'facts'       => array(
					'duration' => '99 Years',
					'fee'      => '₹1',
				),
			)
		);

		self::assertSame( QualityState::REJECTED, $this->validator->validate( $proposal )->state() );
	}

	public function test_institute_eligibility_leakage_is_rejected(): void {
		$proposal = $this->proposal(
			'institute_general_duty_assistant',
			'course_overview',
			array( 'paragraphs' => array( 'Eligibility is 12th Pass.', 'The approved course facts are listed.' ) )
		);

		$report = $this->validator->validate( $proposal )->to_array();
		self::assertSame( QualityState::REJECTED, $report['state'] );
		self::assertContains( 'topic.institute.eligibility_omit', array_column( $report['findings'], 'rule_id' ) );
	}

	public function test_diploma_eligibility_is_course_specific_and_intent_scoped(): void {
		$allowed = $this->proposal(
			'diploma_basic_health_care',
			'course_overview',
			array(
				'facts' => array(
					'duration'    => '1 Year 6 Months',
					'fee'         => '₹30,000',
					'eligibility' => '12th Pass',
				),
			)
		);
		self::assertSame( QualityState::PASS, $this->validator->validate( $allowed )->state() );

		$wrong = $this->proposal(
			'diploma_basic_health_care',
			'fees',
			array(
				'paragraphs' => array( 'Eligibility is 10th Pass.', 'The fee follows the approved master.' ),
				'facts'      => array(
					'fee'         => '₹30,000',
					'eligibility' => '10th Pass',
				),
			)
		);
		self::assertSame( QualityState::REJECTED, $this->validator->validate( $wrong )->state() );
	}

	public function test_missing_syllabus_blocks_only_the_syllabus_intent(): void {
		$overview = $this->proposal( 'institute_cms_ed', 'course_overview' );
		$syllabus = $this->proposal( 'institute_cms_ed', 'syllabus', array( 'facts' => array() ) );

		self::assertSame( QualityState::PASS, $this->validator->validate( $overview )->state() );
		self::assertSame( QualityState::REJECTED, $this->validator->validate( $syllabus )->state() );
	}

	public function test_blocked_claim_repeated_filler_missing_link_and_bad_conclusion_fail(): void {
		$proposal = $this->proposal(
			'institute_general_duty_assistant',
			'course_overview',
			array(
				'headings'       => array( 'Conclusion', 'More Details' ),
				'paragraphs'     => array( 'Become a doctor with guaranteed job.', 'Become a doctor with guaranteed job.' ),
				'internal_links' => array(),
			)
		);
		$report   = $this->validator->validate( $proposal )->to_array();

		self::assertSame( QualityState::REJECTED, $report['state'] );
		$rules = array_column( $report['findings'], 'rule_id' );
		self::assertContains( 'topic.claims.blocked', $rules );
		self::assertContains( 'topic.generic_filler', $rules );
		self::assertContains( 'topic.internal_link', $rules );
		self::assertContains( 'topic.conclusion.last', $rules );
	}

	public function test_thin_but_otherwise_safe_content_needs_review(): void {
		$proposal = $this->proposal(
			'institute_general_duty_assistant',
			'course_overview',
			array( 'paragraphs' => array( 'Canonical course details are provided for review.' ) )
		);

		self::assertSame( QualityState::NEEDS_REVIEW, $this->validator->validate( $proposal )->state() );
	}

	public function test_keyword_slug_and_semantic_duplicates_are_rejected(): void {
		$existing = $this->proposal( 'institute_general_duty_assistant' );
		$this->registry->save( new PublishedTopic( $existing, 'https://atalinstitute.com/gda-guide/' ) );
		$duplicate = $this->proposal(
			'institute_nursing_patient_care',
			'course_overview',
			array(
				'primary_keyword' => $existing->identity()->primary_keyword(),
				'slug'            => $existing->slug(),
				'title'           => $existing->title() . ' updated',
				'headings'        => $existing->headings(),
				'paragraphs'      => $existing->paragraphs(),
				'faqs'            => $existing->faqs(),
			)
		);
		$report    = $this->validator->validate( $duplicate )->to_array();

		self::assertSame( QualityState::REJECTED, $report['state'] );
		$rules = array_column( $report['findings'], 'rule_id' );
		self::assertContains( 'topic.url.collision', $rules );
		self::assertContains( 'topic.keyword.cannibalization', $rules );
		self::assertContains( 'topic.similarity.whole', $rules );
	}
}
