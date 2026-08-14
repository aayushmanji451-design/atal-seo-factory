<?php
/** Deterministic local image request. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Domain;

final class ImageSpecification {
	public const WIDTH  = 1200;
	public const HEIGHT = 630;
	public const MIME   = 'image/webp';

	public function __construct( private readonly AcceptanceFixture $fixture, private readonly ResolvedAsset $asset ) {}

	public function fixture(): AcceptanceFixture {
		return $this->fixture; }
	public function asset(): ResolvedAsset {
		return $this->asset; }
	public function alt_text(): string {
		return $this->asset->course_name() . ' course information at ' . $this->asset->site_name(); }
	public function filename(): string {
		$site = 'atal_institute' === $this->fixture->target_site() ? 'atal-institute' : 'atal-diploma';
		$name = strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $this->asset->course_name() ) ?? '' );
		return trim( $site . '-' . trim( $name, '-' ) . '-course-information', '-' ) . '.webp';
	}
	public function fingerprint(): string {
		return hash( 'sha256', implode( "\0", array( 'task05-renderer-v1', $this->fixture->target_site(), $this->fixture->course_key(), $this->fixture->article_key(), $this->fixture->intent_key(), $this->asset->template_key(), $this->asset->asset_key(), $this->alt_text() ) ) );
	}
}
