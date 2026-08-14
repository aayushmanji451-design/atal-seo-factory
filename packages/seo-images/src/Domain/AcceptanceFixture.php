<?php
/** Immutable controlled Task 05 fixture. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Domain;

use Atal\SeoImages\Exception\PipelineException;

final class AcceptanceFixture {
	public function __construct(
		private readonly string $target_site,
		private readonly string $expected_host,
		private readonly int $post_id,
		private readonly string $article_key,
		private readonly string $course_key,
		private readonly string $intent_key,
		private readonly string $seo_title,
		private readonly string $meta_description,
		private readonly string $focus_keyword
	) {
		$length = strlen( $meta_description );
		if ( 140 > $length || 160 < $length ) {
			throw new PipelineException( 'Task 05 meta descriptions must contain 140 to 160 characters.' );
		}
	}

	public function target_site(): string {
		return $this->target_site; }
	public function expected_host(): string {
		return $this->expected_host; }
	public function post_id(): int {
		return $this->post_id; }
	public function article_key(): string {
		return $this->article_key; }
	public function course_key(): string {
		return $this->course_key; }
	public function intent_key(): string {
		return $this->intent_key; }
	public function seo_title(): string {
		return $this->seo_title; }
	public function meta_description(): string {
		return $this->meta_description; }
	public function focus_keyword(): string {
		return $this->focus_keyword; }
}
