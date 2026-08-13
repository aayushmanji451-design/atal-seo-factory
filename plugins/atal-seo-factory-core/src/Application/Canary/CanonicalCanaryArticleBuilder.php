<?php
/** Task 01-backed deterministic canary builder. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Application\Canary;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\Contracts\Value\TargetSite;
use Atal\SeoFactory\Domain\Canary\CanaryArticle;
use Atal\SeoFactory\Domain\Canary\CanaryRequest;

final class CanonicalCanaryArticleBuilder {
	public const INSTITUTE_ARTICLE_KEY = 'article_task04_atal_institute_general_duty_assistant_course_overview_v1';
	public const DIPLOMA_ARTICLE_KEY   = 'article_task04_atal_diploma_basic_health_care_course_overview_v1';

	public function __construct( private readonly string $master_directory, private readonly string $schema_directory, private readonly KnowledgeValidator $validator ) {}

	public function build( CanaryRequest $request ): CanaryArticle {
		$package = KnowledgePackage::from_directory( $this->master_directory );
		$report  = $this->validator->validate( $package, $this->schema_directory );
		if ( ! $report->is_valid() ) {
			throw new CanaryException( 'Task 01 canonical knowledge validation failed before canary construction.' );
		}
		if ( 'course_overview' !== $request->intent_key() ) {
			throw new CanaryException( 'Task 04 accepts only the safe course-overview intent.' );
		}
		$course_document = TargetSite::INSTITUTE === $request->target_site() ? 'institute_courses' : 'diploma_courses';
		$course          = $this->record( $package->document( $course_document ), 'courses', $request->course_key() );
		$url             = $this->record( $package->document( 'course_urls' ), 'records', $request->course_key() );
		$image           = $this->record( $package->document( 'image_assets' ), 'records', $request->course_key() );
		$link            = $this->record( $package->document( 'internal_links' ), 'records', $request->course_key() );
		$this->assert_mapping( $request, $course, $url, $image, $link );

		$facts = $this->facts( $request, $course );
		return TargetSite::INSTITUTE === $request->target_site()
			? $this->institute_article( $request, $facts, $url, $image )
			: $this->diploma_article( $request, $facts, $url, $image );
	}

	/**
	 * @param array<string,mixed> $document Canonical document.
	 *
	 * @return array<string,mixed>
	 */
	private function record( array $document, string $field, string $course_key ): array {
		foreach ( JsonValue::object_list_field( $document, $field ) as $record ) {
			if ( JsonValue::string_field( $record, 'course_key' ) === $course_key ) {
				return $record;
			}
		}
		throw new CanaryException( 'A required canonical canary mapping is missing for ' . $course_key );
	}

	/** @param array<string,mixed> ...$records */
	private function assert_mapping( CanaryRequest $request, array ...$records ): void {
		foreach ( $records as $record ) {
			if ( $request->course_key() !== JsonValue::string_field( $record, 'course_key' ) || $request->target_site() !== JsonValue::string_field( $record, 'target_site' ) ) {
				throw new CanaryException( 'Canonical course, link, and image identities must stay on one target site.' );
			}
		}
	}

	/**
	 * @param array<string,mixed> $course Canonical course.
	 *
	 * @return array{duration:string,fee:string,source_refs:list<string>}
	 */
	private function facts( CanaryRequest $request, array $course ): array {
		$fact_source = $course;
		if ( TargetSite::INSTITUTE === $request->target_site() ) {
			$option_key = $request->option_key();
			if ( null === $option_key ) {
				throw new CanaryException( 'The multi-option Institute family requires its approved option key.' );
			}
			$fact_source = array();
			foreach ( JsonValue::object_list_field( $course, 'options' ) as $option ) {
				if ( JsonValue::string_field( $option, 'option_key' ) === $option_key ) {
					$fact_source = $option;
					break;
				}
			}
			if ( array() === $fact_source ) {
				throw new CanaryException( 'The selected Institute option is not canonical.' );
			}
		}
		$duration = JsonValue::object_field( $fact_source, 'duration' );
		$fee      = JsonValue::object_field( $fact_source, 'fee' );
		$refs     = array_values( array_unique( array_merge( JsonValue::string_list_field( $duration, 'source_refs' ), JsonValue::string_list_field( $fee, 'source_refs' ) ) ) );
		return array(
			'duration'    => JsonValue::string_field( $duration, 'display' ),
			'fee'         => JsonValue::string_field( $fee, 'display' ),
			'source_refs' => $refs,
		);
	}

	/**
	 * @param array{duration:string,fee:string,source_refs:list<string>} $facts Canonical facts.
	 * @param array<string,mixed>                                      $url   Canonical URL.
	 * @param array<string,mixed>                                      $image Canonical image.
	 */
	private function institute_article( CanaryRequest $request, array $facts, array $url, array $image ): CanaryArticle {
		$title    = 'General Duty Assistant course: duration and fees';
		$link_url = JsonValue::string_field( $url, 'canonical_url' );
		$content  = '<p>This CANARY/DEVELOPMENT overview uses the approved ATAL Institute course record.</p>'
			. '<h2>Course overview</h2><p>The Certificate in General Duty Assistant (GDA) is listed with a duration of ' . $facts['duration'] . ' and a fee of ' . $facts['fee'] . '.</p>'
			. '<h2>Course details</h2><ul><li>Duration: ' . $facts['duration'] . '</li><li>Fee: ' . $facts['fee'] . '</li></ul>'
			. '<h2>Course information</h2><p>Review the <a href="' . $link_url . '">ATAL Institute course hub</a> for current course information.</p>'
			. '<h2>Conclusion</h2><p>This overview keeps the approved course identity, duration, fee, and ATAL Institute link together.</p>';
		return new CanaryArticle(
			self::INSTITUTE_ARTICLE_KEY,
			$request->course_key(),
			TargetSite::INSTITUTE,
			$request->intent_key(),
			$request->option_key(),
			$title,
			$title,
			'general-duty-assistant-course-duration-fees',
			'Approved General Duty Assistant course duration, fee, and ATAL Institute course information.',
			$content,
			$title,
			'Explore the General Duty Assistant (GDA) Certificate at ATAL Institute, including the approved 6-month duration, Rs. 9,999 fee, and course link.',
			'General Duty Assistant course',
			$facts['duration'],
			$facts['fee'],
			$link_url,
			JsonValue::string_field( $image, 'asset_key' ),
			$request->featured_image_id(),
			$facts['source_refs']
		);
	}

	/**
	 * @param array{duration:string,fee:string,source_refs:list<string>} $facts Canonical facts.
	 * @param array<string,mixed>                                      $url   Canonical URL.
	 * @param array<string,mixed>                                      $image Canonical image.
	 */
	private function diploma_article( CanaryRequest $request, array $facts, array $url, array $image ): CanaryArticle {
		$title    = 'Diploma in Basic Health Care: duration and fees';
		$link_url = JsonValue::string_field( $url, 'canonical_url' );
		$content  = '<p>This CANARY/DEVELOPMENT overview uses the approved Atal Diploma university Diploma record.</p>'
			. '<h2>Course overview</h2><p>The Diploma in Basic Health Care is listed with a duration of ' . $facts['duration'] . ' and a fee of ' . $facts['fee'] . '.</p>'
			. '<h2>Course details</h2><ul><li>Duration: ' . $facts['duration'] . '</li><li>Fee: ' . $facts['fee'] . '</li></ul>'
			. '<h2>Course information</h2><p>Review the <a href="' . $link_url . '">approved Diploma in Basic Health Care page</a> for current course information.</p>'
			. '<h2>Conclusion</h2><p>This overview keeps the approved course identity, duration, fee, and Atal Diploma link together.</p>';
		return new CanaryArticle(
			self::DIPLOMA_ARTICLE_KEY,
			$request->course_key(),
			TargetSite::DIPLOMA,
			$request->intent_key(),
			null,
			$title,
			$title,
			'diploma-in-basic-health-care-duration-fees',
			'Approved Diploma in Basic Health Care duration, fee, and Atal Diploma course information.',
			$content,
			$title,
			'Review the Diploma in Basic Health Care from Atal Diploma, including the approved 18-month duration, Rs. 30,000 fee, and official course details.',
			'Diploma in Basic Health Care',
			$facts['duration'],
			$facts['fee'],
			$link_url,
			JsonValue::string_field( $image, 'asset_key' ),
			$request->featured_image_id(),
			$facts['source_refs']
		);
	}
}
