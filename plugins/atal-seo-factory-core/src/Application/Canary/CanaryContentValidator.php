<?php
/** Deterministic Task 04 content gates. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Application\Canary;

use Atal\Contracts\Value\TargetSite;
use Atal\SeoFactory\Domain\Canary\CanaryArticle;

final class CanaryContentValidator {
	/** @var list<string> */
	private const BLOCKED = array(
		'doctor title',
		'become a doctor',
		'independent medical practice',
		'clinical practice authority',
		'practice eligible',
		'open a clinic',
		'clinic licence',
		'clinic permission',
		'medical licence',
		'professional licence',
		'automatic registration',
		'registered practitioner',
		'government approved course',
		'government certified',
		'all india valid',
		'statutory authority',
		'guaranteed job',
		'100% placement',
		'assured appointment',
		'guaranteed admission',
		'diagnose disease',
		'treat patients',
		'cure disease',
		'treatment protocol',
		'prescribe medicines',
		'dispense essential drugs',
		'dosage authority',
	);
	/** @var list<string> */
	private const INSTITUTE_ELIGIBILITY = array( 'eligibility', 'who can apply', 'minimum qualification', 'practice के लिए नहीं', 'clinic नहीं खोल सकते', 'doctor नहीं बन सकते', '<h2>disclaimer' );

	public function validate( CanaryArticle $article ): void {
		$haystack = strtolower( wp_strip_all_tags( implode( ' ', array( $article->title(), $article->h1(), $article->excerpt(), $article->content(), $article->seo_title(), $article->meta_description() ) ) ) );
		foreach ( self::BLOCKED as $blocked ) {
			if ( str_contains( $haystack, strtolower( $blocked ) ) ) {
				throw new CanaryException( 'Canary content contains a blocked claim: ' . $blocked );
			}
		}
		if ( TargetSite::INSTITUTE === $article->target_site() ) {
			$raw = strtolower( implode( ' ', array( $article->title(), $article->excerpt(), $article->content(), $article->meta_description() ) ) );
			foreach ( self::INSTITUTE_ELIGIBILITY as $blocked ) {
				if ( str_contains( $raw, strtolower( $blocked ) ) ) {
					throw new CanaryException( 'Institute normal-post eligibility/disclaimer content must be omitted.' );
				}
			}
		}
		if ( $article->title() !== $article->h1() || $article->title() !== $article->seo_title() ) {
			throw new CanaryException( 'Title, H1, and SEO title must align exactly.' );
		}
		if ( ! str_contains( strtolower( $article->title() ), strtolower( $article->focus_keyword() ) ) ) {
			throw new CanaryException( 'The focus keyword must align with the title.' );
		}
		$meta_length = $this->character_length( $article->meta_description() );
		if ( 140 > $meta_length || 160 < $meta_length ) {
			throw new CanaryException( 'The canary meta description must contain 140 to 160 characters.' );
		}
		if ( ! str_contains( $article->content(), $article->duration() ) || ! str_contains( $article->content(), $article->fee() ) ) {
			throw new CanaryException( 'The canonical duration and fee must appear in the article.' );
		}
		if ( ! str_contains( $article->content(), 'href="' . $article->internal_link() . '"' ) ) {
			throw new CanaryException( 'The approved same-site canonical link is missing.' );
		}
		if ( ! str_ends_with( trim( $article->content() ), '</p>' ) || false === strrpos( $article->content(), '<h2>Conclusion</h2>' ) ) {
			throw new CanaryException( 'Conclusion must be the final content section.' );
		}
		$last_heading = strrpos( $article->content(), '<h2>' );
		$conclusion   = strrpos( $article->content(), '<h2>Conclusion</h2>' );
		if ( $last_heading !== $conclusion ) {
			throw new CanaryException( 'Conclusion must be the final heading.' );
		}
		if ( array() === $article->source_refs() || 1 > $article->featured_image_id() || '' === $article->image_asset_key() ) {
			throw new CanaryException( 'Source references and one mapped existing image are required.' );
		}
	}

	private function character_length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}
		$count = preg_match_all( '/./us', $value, $matches );
		return false === $count ? strlen( $value ) : $count;
	}
}
