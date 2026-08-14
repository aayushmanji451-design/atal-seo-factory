<?php
/** Native SEO and local image pipeline contract tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\SeoImages;

use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\Contracts\Data\KnowledgePackage;
use Atal\SeoImages\Application\AcceptanceCoordinator;
use Atal\SeoImages\Application\CanonicalAssetResolver;
use Atal\SeoImages\Domain\AcceptanceFixture;
use Atal\SeoImages\Domain\ImageSpecification;
use Atal\Tests\Support\SeoImages\FakeAuditLogger;
use Atal\Tests\Support\SeoImages\FakeFixtureRepository;
use Atal\Tests\Support\SeoImages\FakeImageManager;
use Atal\Tests\Support\SeoImages\FakeRuntimeGuard;
use Atal\Tests\Support\SeoImages\FakeSeoAdapter;
use Atal\Tests\Support\SeoImages\FakeStateStore;
use PHPUnit\Framework\TestCase;

final class Task05PipelineTest extends TestCase {
	private AcceptanceFixture $fixture;
	private FakeFixtureRepository $posts;
	private FakeSeoAdapter $seo;
	private FakeImageManager $images;
	private FakeStateStore $states;
	private FakeAuditLogger $audit;
	private AcceptanceCoordinator $coordinator;

	protected function setUp(): void {
		$root              = dirname( __DIR__, 3 );
		$this->fixture     = new AcceptanceFixture( 'atal_institute', 'liveup2.atalinstitute.com', 41, 'article_task04_atal_institute_general_duty_assistant_course_overview_v1', 'institute_general_duty_assistant', 'course_overview', 'General Duty Assistant Course: Duration and Fees | ATAL Institute', 'Explore the General Duty Assistant (GDA) course at ATAL Institute, with verified duration, fee, learning focus, and approved course information.', 'General Duty Assistant course' );
		$this->posts       = new FakeFixtureRepository( 37 );
		$this->seo         = new FakeSeoAdapter();
		$this->images      = new FakeImageManager();
		$this->states      = new FakeStateStore();
		$this->audit       = new FakeAuditLogger();
		$this->coordinator = new AcceptanceCoordinator( $this->fixture, new CanonicalAssetResolver( $root . '/data/master', $root . '/data/schemas', KnowledgeValidator::create_default() ), new FakeRuntimeGuard(), $this->posts, $this->seo, $this->images, $this->states, $this->audit, '0.5.0-dev', array( 37 ) );
	}

	public function test_first_run_generates_exact_safe_webp_and_native_metadata(): void {
		$report = $this->coordinator->run();
		$image  = $report['image'];
		self::assertIsArray( $image );
		self::assertSame( 'PASS', $report['status'] );
		self::assertSame( 'fake_native', $report['seo_adapter'] );
		self::assertSame( 144, $report['meta_description_length'] );
		self::assertSame( 'atal-institute-general-duty-assistant-gda-course-information.webp', $image['filename'] );
		self::assertSame( 'image/webp', $image['mime_type'] );
		self::assertSame( 1200, $image['width'] );
		self::assertSame( 630, $image['height'] );
		self::assertSame( 'General Duty Assistant (GDA) course information at ATAL Institute', $image['alt_text'] );
		self::assertIsString( $image['output_hash'] );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $image['output_hash'] );
		self::assertSame( 'img.institute.general_duty_assistant.v1', $report['source_asset_or_fallback_identifier'] );
		self::assertTrue( $report['safe_fallback_used'] );
		self::assertSame( 'PASS', $report['featured_image_result'] );
		self::assertSame( 'PASS', $report['open_graph_image_result'] );
		self::assertSame( 0, $report['paid_api_request_count'] );
		self::assertSame( 0, $report['live_domain_request_count'] );
	}

	public function test_second_run_reuses_attachment_and_metadata_without_duplicates(): void {
		$first        = $this->coordinator->run();
		$second       = $this->coordinator->run();
		$first_image  = $first['image'];
		$second_image = $second['image'];
		self::assertIsArray( $first_image );
		self::assertIsArray( $second_image );
		self::assertSame( $first_image['attachment_id'], $second_image['attachment_id'] );
		self::assertSame( $first_image['render_fingerprint'], $second_image['render_fingerprint'] );
		self::assertSame( 'PASS', $second['idempotency_result'] );
		self::assertTrue( $second_image['reused'] );
		self::assertSame( 1, $this->images->generated );
		self::assertSame( 1, $this->seo->writes );
	}

	public function test_verify_rollback_and_reapply_preserve_only_controlled_state(): void {
		$first = $this->coordinator->run();
		self::assertSame( 'PASS', $this->coordinator->verify()['status'] );
		$rollback = $this->coordinator->rollback();
		self::assertSame( 'PASS', $rollback['rollback_result'] );
		self::assertTrue( $rollback['generated_media_removed'] );
		self::assertSame( 37, $this->posts->featured );
		self::assertSame( 'before', $this->seo->current['title'] );
		$final       = $this->coordinator->run();
		$first_image = $first['image'];
		$final_image = $final['image'];
		self::assertIsArray( $first_image );
		self::assertIsArray( $final_image );
		self::assertNotSame( $first_image['attachment_id'], $final_image['attachment_id'] );
		self::assertSame( 'PASS', $final['rollback_result'] );
		self::assertSame( 0, $final['unrelated_content_change_count'] );
	}

	public function test_corrupt_or_missing_generated_attachment_is_regenerated_safely(): void {
		$first       = $this->coordinator->run();
		$first_image = $first['image'];
		self::assertIsArray( $first_image );
		self::assertIsString( $first_image['render_fingerprint'] );
		$this->images->corrupt( $first_image['render_fingerprint'] );
		$recovered       = $this->coordinator->run();
		$recovered_image = $recovered['image'];
		self::assertIsArray( $recovered_image );
		self::assertNotSame( $first_image['attachment_id'], $recovered_image['attachment_id'] );
		self::assertSame( $first_image['render_fingerprint'], $recovered_image['render_fingerprint'] );
		self::assertSame( 2, $this->images->generated );
	}

	public function test_site_templates_filenames_and_fingerprints_never_mix(): void {
		$root            = dirname( __DIR__, 3 );
		$resolver        = new CanonicalAssetResolver( $root . '/data/master', $root . '/data/schemas', KnowledgeValidator::create_default() );
		$institute       = new ImageSpecification( $this->fixture, $resolver->resolve( $this->fixture ) );
		$diploma_fixture = new AcceptanceFixture( 'atal_diploma', 'diplomanext.ataldiploma.com', 5704, 'article_task04_atal_diploma_basic_health_care_course_overview_v1', 'diploma_basic_health_care', 'course_overview', 'Diploma in Basic Health Care: Duration and Fees | Atal Diploma', 'Explore the Diploma in Basic Health Care at Atal Diploma, with verified duration, fee, eligibility, learning focus, and approved course information.', 'Diploma in Basic Health Care' );
		$diploma         = new ImageSpecification( $diploma_fixture, $resolver->resolve( $diploma_fixture ) );
		self::assertStringStartsWith( 'atal-institute-', $institute->filename() );
		self::assertStringStartsWith( 'atal-diploma-', $diploma->filename() );
		self::assertNotSame( $institute->asset()->template_key(), $diploma->asset()->template_key() );
		self::assertNotSame( $institute->fingerprint(), $diploma->fingerprint() );
		self::assertSame( 148, strlen( $diploma_fixture->meta_description() ) );
	}

	public function test_approved_asset_policy_excludes_private_or_authority_visuals(): void {
		$data   = KnowledgePackage::from_directory( dirname( __DIR__, 3 ) . '/data/master' )->document( 'image_assets' );
		$policy = $data['asset_policy'];
		self::assertIsArray( $policy );
		self::assertFalse( $policy['allow_student_or_patient_identity'] );
		self::assertFalse( $policy['allow_authority_logos_or_seals'] );
		self::assertFalse( $policy['allow_clinical_procedure_depiction'] );
		$encoded = strtolower( (string) json_encode( $data['records'] ) );
		foreach ( array( 'aadhaar', 'marksheet', 'student id', 'payment screenshot', 'job guarantee' ) as $forbidden ) {
			self::assertStringNotContainsString( $forbidden, $encoded ); }
	}
}
