<?php
/** Canonically resolved Task 05 image specification. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Domain;

final class ResolvedAsset {
	public function __construct(
		private readonly string $course_name,
		private readonly string $site_name,
		private readonly string $asset_key,
		private readonly string $template_key,
		private readonly string $safe_subject,
		private readonly bool $fallback_used
	) {}

	public function course_name(): string {
		return $this->course_name; }
	public function site_name(): string {
		return $this->site_name; }
	public function asset_key(): string {
		return $this->asset_key; }
	public function template_key(): string {
		return $this->template_key; }
	public function safe_subject(): string {
		return $this->safe_subject; }
	public function fallback_used(): bool {
		return $this->fallback_used; }
}
