<?php
/** Validated receiver payload. @package AtalDiplomaReceiver */

declare(strict_types=1);

namespace Atal\DiplomaReceiver\Domain\Receiver;

final class ArticlePayload {
	/** @param array{title:string,description:string,focus_keyphrase:string} $aioseo */
	public function __construct(
		private readonly string $article_key,
		private readonly string $course_key,
		private readonly string $title,
		private readonly string $slug,
		private readonly string $content,
		private readonly string $excerpt,
		private readonly array $aioseo,
		private readonly ?int $featured_image_id
	) {
	}

	public function article_key(): string {
		return $this->article_key; }
	public function course_key(): string {
		return $this->course_key; }
	public function title(): string {
		return $this->title; }
	public function slug(): string {
		return $this->slug; }
	public function content(): string {
		return $this->content; }
	public function excerpt(): string {
		return $this->excerpt; }
	/** @return array{title:string,description:string,focus_keyphrase:string} */
	public function aioseo(): array {
		return $this->aioseo; }
	public function featured_image_id(): ?int {
		return $this->featured_image_id; }
}
