<?php
/** Deterministic canonical payload tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Canary;

use Atal\Contracts\Value\TargetSite;
use Atal\SeoFactory\Application\Canary\CanaryContentValidator;
use Atal\SeoFactory\Application\Canary\CanaryException;
use Atal\SeoFactory\Application\Canary\CanonicalCanaryArticleBuilder;

final class CanaryPayloadTest extends CanaryTestCase {
	public function test_manual_import_rejects_batches_and_missing_image(): void {
		$this->expectException( CanaryException::class );
		$this->importer()->import( '[]', TargetSite::INSTITUTE );
	}

	public function test_manual_import_rejects_wrong_target(): void {
		$this->expectException( CanaryException::class );
		$this->importer()->import( $this->json( TargetSite::DIPLOMA ), TargetSite::INSTITUTE );
	}

	public function test_institute_payload_is_locked_and_omits_eligibility(): void {
		$request = $this->importer()->import( $this->json( TargetSite::INSTITUTE ), TargetSite::INSTITUTE );
		$article = $this->builder()->build( $request );
		self::assertSame( CanonicalCanaryArticleBuilder::INSTITUTE_ARTICLE_KEY, $article->article_key() );
		self::assertSame( 'institute_general_duty_assistant', $article->course_key() );
		self::assertSame( '6 Months', $article->duration() );
		self::assertSame( '₹9,999', $article->fee() );
		self::assertSame( 'img.institute.general_duty_assistant.v1', $article->image_asset_key() );
		self::assertStringNotContainsString( 'eligibility', strtolower( $article->content() ) );
		self::assertSame( $article->title(), $article->h1() );
		self::assertSame( $article->title(), $article->seo_title() );
		self::assertGreaterThanOrEqual( 140, strlen( $article->meta_description() ) );
		self::assertLessThanOrEqual( 160, strlen( $article->meta_description() ) );
		( new CanaryContentValidator() )->validate( $article );
	}

	public function test_diploma_payload_uses_only_overview_facts(): void {
		$request = $this->importer()->import( $this->json( TargetSite::DIPLOMA, 601 ), TargetSite::DIPLOMA );
		$article = $this->builder()->build( $request );
		self::assertSame( CanonicalCanaryArticleBuilder::DIPLOMA_ARTICLE_KEY, $article->article_key() );
		self::assertSame( '1 Year 6 Months', $article->duration() );
		self::assertSame( '₹30,000', $article->fee() );
		self::assertSame( 'img.diploma.basic_health_care.v1', $article->image_asset_key() );
		self::assertStringNotContainsString( 'syllabus', strtolower( $article->content() ) );
		self::assertStringNotContainsString( 'eligibility', strtolower( $article->content() ) );
		$payload = $article->receiver_payload();
		$aioseo  = $payload['aioseo'] ?? null;
		self::assertIsArray( $aioseo );
		self::assertSame( $article->seo_title(), $aioseo['title'] ?? null );
		( new CanaryContentValidator() )->validate( $article );
	}

	public function test_blocked_claim_fails_before_write(): void {
		$article = $this->builder()->build( $this->importer()->import( $this->json( TargetSite::INSTITUTE ), TargetSite::INSTITUTE ) );
		$this->expectException( CanaryException::class );
		( new CanaryContentValidator() )->validate( $this->with_content( $article, $article->content() . '<p>Guaranteed job.</p>' ) );
	}
}
