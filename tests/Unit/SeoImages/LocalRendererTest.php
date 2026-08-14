<?php
/** Real local GD/WebP renderer tests when the runtime extension is available. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\SeoImages;

use Atal\SeoImages\Domain\AcceptanceFixture;
use Atal\SeoImages\Domain\ImageSpecification;
use Atal\SeoImages\Domain\ResolvedAsset;
use Atal\SeoImages\Infrastructure\WordPress\LocalImageManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class LocalRendererTest extends TestCase {
	public function test_local_renderer_creates_decodable_optimized_1200_by_630_webp(): void {
		if ( ! function_exists( 'imagewebp' ) ) {
			self::markTestSkipped( 'GD WebP is not enabled in the default local PHP runtime.' ); }
		$fixture       = new AcceptanceFixture( 'atal_institute', 'liveup2.atalinstitute.com', 41, 'article_task04_atal_institute_general_duty_assistant_course_overview_v1', 'institute_general_duty_assistant', 'course_overview', 'General Duty Assistant Course: Duration and Fees | ATAL Institute', 'Explore the General Duty Assistant (GDA) course at ATAL Institute, with verified duration, fee, learning focus, and approved course information.', 'General Duty Assistant course' );
		$specification = new ImageSpecification( $fixture, new ResolvedAsset( 'General Duty Assistant (GDA)', 'ATAL Institute', 'img.institute.general_duty_assistant.v1', 'atal-institute-knowledge-card-v1', 'Neutral books and educational icons', true ) );
		$path          = tempnam( sys_get_temp_dir(), 'atal-task05-' );
		self::assertIsString( $path );
		try {
			$method = new ReflectionMethod( LocalImageManager::class, 'render_webp' );
			$method->invoke( new LocalImageManager(), $specification, $path );
			$size = getimagesize( $path );
			self::assertIsArray( $size );
			self::assertSame( 1200, $size[0] );
			self::assertSame( 630, $size[1] );
			self::assertSame( 'image/webp', $size['mime'] );
			self::assertGreaterThan( 512, filesize( $path ) );
			self::assertLessThan( 1572864, filesize( $path ) );
		} finally {
			wp_delete_file( $path ); }
	}

	public function test_wordpress_metadata_already_persisted_during_generation_is_accepted(): void {
		$metadata = array(
			'width'  => 1200,
			'height' => 630,
			'file'   => 'image.webp',
		);
		\AtalWordPressStubState::$attachment_metadata[14559] = $metadata;

		$method = new ReflectionMethod( LocalImageManager::class, 'store_attachment_metadata' );
		$method->invoke( new LocalImageManager(), 14559, $metadata );

		self::assertSame( $metadata, \AtalWordPressStubState::$attachment_metadata[14559] );
	}

	public function test_identical_unreferenced_orphan_is_removed_before_regeneration(): void {
		$path = tempnam( sys_get_temp_dir(), 'atal-task05-orphan-' );
		self::assertIsString( $path );
		$bytes = '';

		$method = new ReflectionMethod( LocalImageManager::class, 'remove_identical_orphan' );
		$method->invoke( new LocalImageManager(), $path, '2026/08/task05.webp', $bytes );

		self::assertFileDoesNotExist( $path );
	}
}
