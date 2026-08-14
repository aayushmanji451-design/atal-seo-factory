<?php
/** Strict one-article canary request. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Domain\Canary;

final class CanaryRequest {
	public function __construct( private readonly string $target_site, private readonly string $course_key, private readonly string $intent_key, private readonly ?string $option_key, private readonly int $featured_image_id ) {}
	public function target_site(): string {
		return $this->target_site; }
	public function course_key(): string {
		return $this->course_key; }
	public function intent_key(): string {
		return $this->intent_key; }
	public function option_key(): ?string {
		return $this->option_key; }
	public function featured_image_id(): int {
		return $this->featured_image_id; }
}
