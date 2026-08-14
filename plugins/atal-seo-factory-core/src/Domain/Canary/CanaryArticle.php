<?php
/** Deterministic Task 04 article. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Domain\Canary;

final class CanaryArticle {
	/** @param list<string> $source_refs Resolved fee/duration source references. */
	public function __construct(
		private readonly string $article_key,
		private readonly string $course_key,
		private readonly string $target_site,
		private readonly string $intent_key,
		private readonly ?string $option_key,
		private readonly string $title,
		private readonly string $h1,
		private readonly string $slug,
		private readonly string $excerpt,
		private readonly string $content,
		private readonly string $seo_title,
		private readonly string $meta_description,
		private readonly string $focus_keyword,
		private readonly string $duration,
		private readonly string $fee,
		private readonly string $internal_link,
		private readonly string $image_asset_key,
		private readonly int $featured_image_id,
		private readonly array $source_refs
	) {}
	public function article_key(): string {
		return $this->article_key; }
	public function course_key(): string {
		return $this->course_key; }
	public function target_site(): string {
		return $this->target_site; }
	public function intent_key(): string {
		return $this->intent_key; }
	public function option_key(): ?string {
		return $this->option_key; }
	public function title(): string {
		return $this->title; }
	public function h1(): string {
		return $this->h1; }
	public function slug(): string {
		return $this->slug; }
	public function excerpt(): string {
		return $this->excerpt; }
	public function content(): string {
		return $this->content; }
	public function seo_title(): string {
		return $this->seo_title; }
	public function meta_description(): string {
		return $this->meta_description; }
	public function focus_keyword(): string {
		return $this->focus_keyword; }
	public function duration(): string {
		return $this->duration; }
	public function fee(): string {
		return $this->fee; }
	public function internal_link(): string {
		return $this->internal_link; }
	public function image_asset_key(): string {
		return $this->image_asset_key; }
	public function featured_image_id(): int {
		return $this->featured_image_id; }
	/** @return list<string> */ public function source_refs(): array {
		return $this->source_refs; }
	/** @return array<string,mixed> */
	public function receiver_payload(): array {
		return array(
			'schema_version'    => '1.0',
			'target_site'       => $this->target_site,
			'article_key'       => $this->article_key,
			'course_key'        => $this->course_key,
			'title'             => $this->title,
			'slug'              => $this->slug,
			'content'           => $this->content,
			'excerpt'           => $this->excerpt,
			'status'            => 'draft',
			'aioseo'            => array(
				'title'           => $this->seo_title,
				'description'     => $this->meta_description,
				'focus_keyphrase' => $this->focus_keyword,
			),
			'featured_image_id' => $this->featured_image_id,
		);
	}
	/** @return array<string,mixed> */
	public function evidence(): array {
		return array(
			'article_key'       => $this->article_key,
			'course_key'        => $this->course_key,
			'target_site'       => $this->target_site,
			'intent_key'        => $this->intent_key,
			'option_key'        => $this->option_key,
			'title'             => $this->title,
			'h1'                => $this->h1,
			'slug'              => $this->slug,
			'duration'          => $this->duration,
			'fee'               => $this->fee,
			'internal_link'     => $this->internal_link,
			'image_asset_key'   => $this->image_asset_key,
			'featured_image_id' => $this->featured_image_id,
			'seo_title'         => $this->seo_title,
			'meta_description'  => $this->meta_description,
			'focus_keyword'     => $this->focus_keyword,
			'source_refs'       => $this->source_refs,
		);
	}
}
