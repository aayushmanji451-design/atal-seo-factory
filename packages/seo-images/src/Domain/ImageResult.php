<?php
/** Verified local image outcome. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Domain;

final class ImageResult {
	public function __construct(
		private readonly int $attachment_id,
		private readonly string $url,
		private readonly string $filename,
		private readonly string $mime,
		private readonly int $width,
		private readonly int $height,
		private readonly string $alt_text,
		private readonly string $output_hash,
		private readonly string $fingerprint,
		private readonly string $renderer_version,
		private readonly bool $reused,
		private readonly bool $generated_now
	) {}

	public function attachment_id(): int {
		return $this->attachment_id; }
	public function url(): string {
		return $this->url; }
	public function filename(): string {
		return $this->filename; }
	public function mime(): string {
		return $this->mime; }
	public function width(): int {
		return $this->width; }
	public function height(): int {
		return $this->height; }
	public function alt_text(): string {
		return $this->alt_text; }
	public function output_hash(): string {
		return $this->output_hash; }
	public function fingerprint(): string {
		return $this->fingerprint; }
	public function renderer_version(): string {
		return $this->renderer_version; }
	public function reused(): bool {
		return $this->reused; }
	public function generated_now(): bool {
		return $this->generated_now; }
	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'attachment_id'      => $this->attachment_id,
			'url'                => $this->url,
			'filename'           => $this->filename,
			'mime_type'          => $this->mime,
			'width'              => $this->width,
			'height'             => $this->height,
			'alt_text'           => $this->alt_text,
			'output_hash'        => $this->output_hash,
			'render_fingerprint' => $this->fingerprint,
			'renderer_version'   => $this->renderer_version,
			'reused'             => $this->reused,
			'generated_now'      => $this->generated_now,
		);
	}
	/** @param array<string,mixed> $data */
	public static function from_array( array $data ): self {
		return new self(
			self::integer( $data, 'attachment_id' ),
			self::string( $data, 'url' ),
			self::string( $data, 'filename' ),
			self::string( $data, 'mime_type' ),
			self::integer( $data, 'width' ),
			self::integer( $data, 'height' ),
			self::string( $data, 'alt_text' ),
			self::string( $data, 'output_hash' ),
			self::string( $data, 'render_fingerprint' ),
			self::string( $data, 'renderer_version' ),
			true === ( $data['reused'] ?? false ),
			true === ( $data['generated_now'] ?? false )
		);
	}
	/** @param array<string,mixed> $data */ private static function string( array $data, string $key ): string {
		return is_string( $data[ $key ] ?? null ) ? $data[ $key ] : ''; }
	/** @param array<string,mixed> $data */ private static function integer( array $data, string $key ): int {
		return is_numeric( $data[ $key ] ?? null ) ? (int) $data[ $key ] : 0; }
}
