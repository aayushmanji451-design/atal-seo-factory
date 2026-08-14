<?php
/** Task 05 native SEO adapter contract tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\SeoImages;

use Atal\DiplomaReceiver\Infrastructure\WordPress\AioseoEnvironmentAdapter;
use Atal\SeoFactory\Infrastructure\WordPress\Seo\RankMathAdapter;
use Atal\SeoImages\Domain\SeoMetadata;
use AtalWordPressStubState;
use PHPUnit\Framework\TestCase;

final class Task05AioseoModelStub {
	/** @var array<int,array<string,mixed>> */ public static array $records = array();
	/** @param array<string,mixed> $data */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Mirrors AIOSEO 4.9.8.
	public static function savePost( int $post_id, array $data ): void {
		self::$records[ $post_id ] = array_replace( self::$records[ $post_id ] ?? array(), $data ); }
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Mirrors AIOSEO 4.9.8.
	public static function getPost( int $post_id ): object {
		return (object) ( self::$records[ $post_id ] ?? array() ); }
}

final class NativeSeoAdapterTest extends TestCase {
	protected function setUp(): void {
		AtalWordPressStubState::$options   = array( 'active_plugins' => array( 'seo-by-rank-math/rank-math.php' ) );
		AtalWordPressStubState::$post_meta = array();
		AtalWordPressStubState::$calls     = array();
		Task05AioseoModelStub::$records    = array();
	}

	public function test_rank_math_native_fields_preserve_unrelated_data_and_restore_exactly(): void {
		$adapter                               = new RankMathAdapter();
		AtalWordPressStubState::$post_meta[41] = array(
			'rank_math_title'            => 'Previous title',
			'rank_math_description'      => 'Previous description',
			'rank_math_custom_unrelated' => 'preserve me',
		);
		$before                                = $adapter->snapshot( 41 );
		$metadata                              = $this->metadata( 7001, 'https://liveup2.atalinstitute.com/wp-content/uploads/atal-institute-gda.webp' );
		$result                                = $adapter->apply_and_verify( 41, $metadata );
		$current                               = $adapter->snapshot( 41 );
		$current_fields                        = $current['fields'];
		self::assertIsArray( $current_fields );
		self::assertSame( 'PASS', $result['status'] );
		self::assertSame( $metadata->description(), $this->snapshot_value( $current_fields, 'description' ) );
		self::assertSame( $metadata->og_image_url(), $this->snapshot_value( $current_fields, 'og_image_url' ) );
		self::assertSame( '7001', $this->snapshot_value( $current_fields, 'og_image_id' ) );
		self::assertSame( 'preserve me', AtalWordPressStubState::$post_meta[41]['rank_math_custom_unrelated'] );
		$adapter->apply_and_verify( 41, $metadata );
		$adapter->restore( 41, $before );
		$restored        = $adapter->snapshot( 41 );
		$restored_fields = $restored['fields'];
		self::assertIsArray( $restored_fields );
		self::assertSame( 'Previous title', $this->snapshot_value( $restored_fields, 'title' ) );
		self::assertSame( 'Previous description', $this->snapshot_value( $restored_fields, 'description' ) );
		self::assertFalse( $this->snapshot_exists( $restored_fields, 'og_image_url' ) );
		self::assertSame( 'preserve me', AtalWordPressStubState::$post_meta[41]['rank_math_custom_unrelated'] );
	}

	public function test_aioseo_498_model_fields_preserve_additional_keyphrases_and_restore(): void {
		if ( ! defined( 'AIOSEO_VERSION' ) ) {
			define( 'AIOSEO_VERSION', '4.9.8' ); }
		$adapter                              = new AioseoEnvironmentAdapter( Task05AioseoModelStub::class );
		Task05AioseoModelStub::$records[5704] = array(
			'title'               => 'Previous title',
			'description'         => 'Previous description',
			'keyphrases'          => array(
				'focus'      => array( 'keyphrase' => 'before' ),
				'additional' => array( array( 'keyphrase' => 'keep this' ) ),
			),
			'og_title'            => 'Previous OG',
			'og_description'      => 'Previous OG description',
			'og_image_type'       => 'featured',
			'og_image_custom_url' => null,
			'canonical_url'       => 'https://example.test/keep-canonical',
		);
		$before                               = $adapter->snapshot( 5704 );
		$metadata                             = $this->metadata( 8001, 'https://diplomanext.ataldiploma.com/wp-content/uploads/atal-diploma-bhc.webp' );
		$result                               = $adapter->apply_and_verify( 5704, $metadata );
		self::assertSame( 'PASS', $result['status'] );
		$current = $adapter->snapshot( 5704 );
		self::assertSame( 'custom', $current['og_image_type'] );
		self::assertSame( $metadata->og_image_url(), $current['og_image_custom_url'] );
		$keyphrases = $current['keyphrases'];
		self::assertIsArray( $keyphrases );
		self::assertSame( array( array( 'keyphrase' => 'keep this' ) ), $keyphrases['additional'] );
		self::assertSame( 'https://example.test/keep-canonical', $current['canonical_url'] );
		$adapter->restore( 5704, $before );
		self::assertSame( $before, $adapter->snapshot( 5704 ) );
	}

	private function metadata( int $image_id, string $image_url ): SeoMetadata {
		return new SeoMetadata( 'General Duty Assistant Course: Duration and Fees | ATAL Institute', 'Explore the General Duty Assistant (GDA) course at ATAL Institute, with verified duration, fee, learning focus, and approved course information.', 'General Duty Assistant course', 'General Duty Assistant Course: Duration and Fees | ATAL Institute', 'Explore the General Duty Assistant (GDA) course at ATAL Institute, with verified duration, fee, learning focus, and approved course information.', $image_url, $image_id, null );
	}

	/** @param array<mixed> $fields Snapshot fields. */
	private function snapshot_value( array $fields, string $name ): mixed {
		$field = $fields[ $name ] ?? null;
		self::assertIsArray( $field );
		return $field['value'] ?? null;
	}

	/** @param array<mixed> $fields Snapshot fields. */
	private function snapshot_exists( array $fields, string $name ): bool {
		$field = $fields[ $name ] ?? null;
		self::assertIsArray( $field );
		return true === ( $field['exists'] ?? false );
	}
}
