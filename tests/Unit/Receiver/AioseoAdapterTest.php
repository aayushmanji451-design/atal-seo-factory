<?php
/** Native AIOSEO 4.9.8 model-contract tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Receiver;

use Atal\DiplomaReceiver\Infrastructure\WordPress\AioseoEnvironmentAdapter;
use PHPUnit\Framework\TestCase;

final class NativeAioseoModelStub {
	/** @var array<int,array<string,mixed>> */
	public static array $records = array();
	/** @param array<string,mixed> $data */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Mirrors the verified AIOSEO 4.9.8 model contract.
	public static function savePost( int $post_id, array $data ): void {
		self::$records[ $post_id ] = array_replace( self::$records[ $post_id ] ?? array(), $data );
	}
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Mirrors the verified AIOSEO 4.9.8 model contract.
	public static function getPost( int $post_id ): object {
		return (object) ( self::$records[ $post_id ] ?? array(
			'title'       => null,
			'description' => null,
			'keyphrases'  => null,
		) );
	}
}

final class AioseoAdapterTest extends TestCase {
	protected function setUp(): void {
		NativeAioseoModelStub::$records = array();
	}

	public function test_native_write_is_verified_and_previous_state_is_restorable(): void {
		$adapter                            = new AioseoEnvironmentAdapter( NativeAioseoModelStub::class );
		NativeAioseoModelStub::$records[41] = array(
			'title'       => 'Previous title',
			'description' => 'Previous description',
			'keyphrases'  => array(
				'focus'      => array( 'keyphrase' => 'previous phrase' ),
				'additional' => array(),
			),
		);
		$previous                           = $adapter->snapshot( 41 );
		$result                             = $adapter->write_and_verify(
			41,
			array(
				'title'           => 'Diploma in Basic Health Care: duration and fees',
				'description'     => 'A deterministic staging-only description.',
				'focus_keyphrase' => 'Diploma in Basic Health Care',
			)
		);
		self::assertSame( 'accepted', $result['status'] );
		self::assertSame( 'Diploma in Basic Health Care', $result['focus_keyphrase'] );
		$adapter->restore( 41, $previous );
		self::assertSame( $previous, $adapter->snapshot( 41 ) );
	}
}
