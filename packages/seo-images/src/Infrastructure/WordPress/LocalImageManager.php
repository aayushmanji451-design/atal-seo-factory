<?php
/** Deterministic zero-API WordPress WebP renderer and attachment manager. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Infrastructure\WordPress;

use Atal\SeoImages\Contract\ImageManagerInterface;
use Atal\SeoImages\Domain\ImageResult;
use Atal\SeoImages\Domain\ImageSpecification;
use Atal\SeoImages\Exception\PipelineException;
use WP_Error;

final class LocalImageManager implements ImageManagerInterface {
	public const RENDERER_VERSION = 'task05-local-webp-v1';
	public const FINGERPRINT_META = '_atal_task05_render_fingerprint';
	public const OUTPUT_HASH_META = '_atal_task05_output_hash';
	public const OWNER_META       = '_atal_task05_article_key';
	public const COURSE_META      = '_atal_task05_course_key';
	public const SITE_META        = '_atal_task05_target_site';
	public const TEMPLATE_META    = '_atal_task05_template';
	public const SOURCE_META      = '_atal_task05_source_asset';
	public const RENDERER_META    = '_atal_task05_renderer_version';

	public function ensure( ImageSpecification $specification ): ImageResult {
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'numberposts'      => 2,
				'fields'           => 'ids',
				'meta_key'         => self::FINGERPRINT_META,
				'meta_value'       => $specification->fingerprint(),
				'suppress_filters' => true,
			)
		);
		if ( 1 < count( $ids ) ) {
			throw new PipelineException( 'Duplicate Task 05 image fingerprints already exist.' ); }
		if ( isset( $ids[0] ) ) {
			try {
				return $this->result( (int) $ids[0], $specification, true, false ); } catch ( PipelineException ) {
				if ( $specification->fixture()->article_key() !== get_post_meta( (int) $ids[0], self::OWNER_META, true ) || false === wp_delete_attachment( (int) $ids[0], true ) ) {
					throw new PipelineException( 'A corrupt Task 05 attachment could not be recovered safely.' ); }
				}
		}

		$temp = wp_tempnam( $specification->filename() );
		if ( ! is_string( $temp ) || '' === $temp ) {
			throw new PipelineException( 'WordPress could not allocate a temporary image file.' ); }
		try {
			$this->render_webp( $specification, $temp );
			$bytes = file_get_contents( $temp );
			if ( false === $bytes || 512 > strlen( $bytes ) || 1572864 < strlen( $bytes ) ) {
				throw new PipelineException( 'The rendered WebP file size is invalid.' ); }
			$upload = wp_upload_dir();
			if ( false !== $upload['error'] ) {
				throw new PipelineException( 'The WordPress upload directory is unavailable.' ); }
			$path = $upload['path'];
			if ( '' === $path || file_exists( rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR . $specification->filename() ) ) {
				throw new PipelineException( 'The deterministic image filename is already occupied without a valid owned attachment.' ); }
			$uploaded = wp_upload_bits( $specification->filename(), null, $bytes );
			if ( false !== $uploaded['error'] || $specification->filename() !== basename( $uploaded['file'] ) ) {
				throw new PipelineException( 'WordPress could not store the deterministic WebP filename.' ); }
			$attachment = wp_insert_attachment(
				array(
					'post_mime_type' => ImageSpecification::MIME,
					'post_title'     => $specification->asset()->course_name() . ' course information',
					'post_content'   => '',
					'post_excerpt'   => '',
					'post_status'    => 'inherit',
				),
				$uploaded['file'],
				$specification->fixture()->post_id(),
				true
			);
			if ( $attachment instanceof WP_Error || 1 > $attachment ) {
				wp_delete_file( $uploaded['file'] );
				throw new PipelineException( 'The generated image attachment could not be created.' ); }
			$this->update_attachment( $attachment, $uploaded['file'], $specification );
			return $this->result( $attachment, $specification, false, true );
		} finally {
			wp_delete_file( $temp ); }
	}

	public function verify( ImageResult $result ): void {
		if ( 'attachment' !== get_post_type( $result->attachment_id() ) || ! wp_attachment_is_image( $result->attachment_id() ) || ImageSpecification::MIME !== get_post_mime_type( $result->attachment_id() ) ) {
			throw new PipelineException( 'The Task 05 attachment is missing or is not a WebP image.' ); }
		$path = get_attached_file( $result->attachment_id() );
		if ( ! is_string( $path ) || ! is_readable( $path ) || $result->output_hash() !== hash_file( 'sha256', $path ) ) {
			throw new PipelineException( 'The Task 05 attachment hash verification failed.' ); }
		$size = getimagesize( $path );
		if ( ! is_array( $size ) || ImageSpecification::WIDTH !== $size[0] || ImageSpecification::HEIGHT !== $size[1] || ImageSpecification::MIME !== $size['mime'] ) {
			throw new PipelineException( 'The Task 05 image is not a decodable 1200 by 630 WebP.' ); }
		if ( $result->alt_text() !== get_post_meta( $result->attachment_id(), '_wp_attachment_image_alt', true ) || $result->fingerprint() !== get_post_meta( $result->attachment_id(), self::FINGERPRINT_META, true ) ) {
			throw new PipelineException( 'The Task 05 attachment metadata verification failed.' ); }
	}

	public function delete_if_orphan( ImageResult $result, array $protected_ids ): bool {
		if ( in_array( $result->attachment_id(), $protected_ids, true ) ) {
			return false; }
		$owner = get_post_meta( $result->attachment_id(), self::OWNER_META, true );
		if ( $result->fingerprint() !== get_post_meta( $result->attachment_id(), self::FINGERPRINT_META, true ) || ! is_string( $owner ) || ! str_starts_with( $owner, 'article_task04_' ) ) {
			throw new PipelineException( 'Rollback refused a non-owned media attachment.' ); }
		$uses = get_posts(
			array(
				'post_type'        => 'any',
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => '_thumbnail_id',
				'meta_value'       => $result->attachment_id(),
				'suppress_filters' => true,
			)
		);
		if ( array() !== $uses ) {
			return false; }
		return false !== wp_delete_attachment( $result->attachment_id(), true );
	}

	private function render_webp( ImageSpecification $specification, string $path ): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagewebp' ) ) {
			throw new PipelineException( 'The local GD WebP renderer is unavailable.' ); }
		$canvas = imagecreatetruecolor( ImageSpecification::WIDTH, ImageSpecification::HEIGHT );
		if ( false === $canvas ) {
			throw new PipelineException( 'The local image canvas could not be created.' ); }
		$is_institute = 'atal_institute' === $specification->fixture()->target_site();
		$background   = $this->color( $canvas, $is_institute ? 12 : 15, $is_institute ? 75 : 38, $is_institute ? 108 : 74 );
		$accent       = $this->color( $canvas, $is_institute ? 33 : 238, $is_institute ? 181 : 143, $is_institute ? 171 : 32 );
		$panel        = $this->color( $canvas, 247, 250, 252 );
		$ink          = $this->color( $canvas, 15, 36, 52 );
		$white        = $this->color( $canvas, 255, 255, 255 );
		imagefilledrectangle( $canvas, 0, 0, 1200, 630, $background );
		imagefilledrectangle( $canvas, 70, 72, 1130, 558, $panel );
		imagefilledrectangle( $canvas, 70, 72, 92, 558, $accent );
		imagefilledellipse( $canvas, 965, 205, 190, 190, $accent );
		imagefilledrectangle( $canvas, 905, 170, 1025, 240, $white );
		imagefilledrectangle( $canvas, 930, 145, 1000, 265, $white );
		imagestring( $canvas, 5, 130, 125, $specification->asset()->site_name(), $ink );
		imagestring( $canvas, 3, 130, 165, $is_institute ? 'HEALTHCARE EDUCATION' : 'DIPLOMA EDUCATION', $accent );
		$lines = explode( "\n", wordwrap( $specification->asset()->course_name(), 34, "\n", true ) );
		$y     = 260;
		foreach ( array_slice( $lines, 0, 3 ) as $line ) {
			imagestring( $canvas, 5, 130, $y, $line, $ink );
			$y += 42; }
		imagestring( $canvas, 4, 130, 465, 'COURSE INFORMATION', $background );
		imagestring( $canvas, 2, 130, 515, 'Development staging image - local renderer', $ink );
		if ( ! imagewebp( $canvas, $path, 82 ) ) {
			imagedestroy( $canvas );
			throw new PipelineException( 'The local renderer could not encode WebP.' ); }
		imagedestroy( $canvas );
	}

	private function update_attachment( int $attachment_id, string $path, ImageSpecification $specification ): void {
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) && defined( 'ABSPATH' ) ) {
			$root = constant( 'ABSPATH' );
			if ( is_string( $root ) ) {
				require_once $root . 'wp-admin/includes/image.php'; }
		}
		$metadata = wp_generate_attachment_metadata( $attachment_id, $path );
		if ( false === wp_update_attachment_metadata( $attachment_id, $metadata ) ) {
			throw new PipelineException( 'WordPress could not generate attachment metadata.' ); }
		$values = array(
			'_wp_attachment_image_alt' => $specification->alt_text(),
			self::FINGERPRINT_META     => $specification->fingerprint(),
			self::OUTPUT_HASH_META     => $this->file_hash( $path ),
			self::OWNER_META           => $specification->fixture()->article_key(),
			self::COURSE_META          => $specification->fixture()->course_key(),
			self::SITE_META            => $specification->fixture()->target_site(),
			self::TEMPLATE_META        => $specification->asset()->template_key(),
			self::SOURCE_META          => $specification->asset()->asset_key(),
			self::RENDERER_META        => self::RENDERER_VERSION,
		);
		foreach ( $values as $key => $value ) {
			if ( false === update_post_meta( $attachment_id, $key, $value ) ) {
				throw new PipelineException( 'The generated attachment metadata could not be stored.' ); }
		}
	}

	private function result( int $attachment_id, ImageSpecification $specification, bool $reused, bool $generated_now ): ImageResult {
		$path = get_attached_file( $attachment_id );
		$url  = wp_get_attachment_url( $attachment_id );
		if ( ! is_string( $path ) || ! is_readable( $path ) || ! is_string( $url ) || '' === $url ) {
			throw new PipelineException( 'The Task 05 attachment file or URL is unavailable.' ); }
		$mime = get_post_mime_type( $attachment_id );
		if ( ! is_string( $mime ) ) {
			throw new PipelineException( 'The Task 05 attachment MIME type is unavailable.' ); }
		$result = new ImageResult( $attachment_id, $url, basename( $path ), $mime, ImageSpecification::WIDTH, ImageSpecification::HEIGHT, $specification->alt_text(), $this->file_hash( $path ), $specification->fingerprint(), self::RENDERER_VERSION, $reused, $generated_now );
		$this->verify( $result );
		return $result;
	}
	/**
	 * @param int<0,255> $red Red component.
	 * @param int<0,255> $green Green component.
	 * @param int<0,255> $blue Blue component.
	 */
	private function color( \GdImage $canvas, int $red, int $green, int $blue ): int {
		$color = imagecolorallocate( $canvas, $red, $green, $blue );
		if ( false === $color ) {
			throw new PipelineException( 'The local image palette could not be created.' );
		} return $color; }
	private function file_hash( string $path ): string {
		$hash = hash_file( 'sha256', $path );
		if ( false === $hash ) {
			throw new PipelineException( 'The generated image hash could not be calculated.' );
		} return $hash; }
}
