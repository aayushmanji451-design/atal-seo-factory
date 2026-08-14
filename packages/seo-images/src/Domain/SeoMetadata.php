<?php
/** Native SEO values for one controlled draft. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Domain;

final class SeoMetadata {
	public function __construct( private readonly string $title, private readonly string $description, private readonly string $focus_keyword, private readonly string $og_title, private readonly string $og_description, private readonly string $og_image_url, private readonly int $og_image_id, private readonly ?string $canonical_url = null ) {}
	public function title(): string {
		return $this->title; }
	public function description(): string {
		return $this->description; }
	public function focus_keyword(): string {
		return $this->focus_keyword; }
	public function og_title(): string {
		return $this->og_title; }
	public function og_description(): string {
		return $this->og_description; }
	public function og_image_url(): string {
		return $this->og_image_url; }
	public function og_image_id(): int {
		return $this->og_image_id; }
	public function canonical_url(): ?string {
		return $this->canonical_url; }
	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'title'          => $this->title,
			'description'    => $this->description,
			'focus_keyword'  => $this->focus_keyword,
			'og_title'       => $this->og_title,
			'og_description' => $this->og_description,
			'og_image_url'   => $this->og_image_url,
			'og_image_id'    => $this->og_image_id,
			'canonical_url'  => $this->canonical_url,
		); }
	/** @param array<string,mixed> $data */
	public static function from_array( array $data ): self {
		return new self( self::string( $data, 'title' ), self::string( $data, 'description' ), self::string( $data, 'focus_keyword' ), self::string( $data, 'og_title' ), self::string( $data, 'og_description' ), self::string( $data, 'og_image_url' ), self::integer( $data, 'og_image_id' ), is_string( $data['canonical_url'] ?? null ) ? $data['canonical_url'] : null );
	}
	/** @param array<string,mixed> $data */ private static function string( array $data, string $key ): string {
		return is_string( $data[ $key ] ?? null ) ? $data[ $key ] : ''; }
	/** @param array<string,mixed> $data */ private static function integer( array $data, string $key ): int {
		return is_numeric( $data[ $key ] ?? null ) ? (int) $data[ $key ] : 0; }
}
