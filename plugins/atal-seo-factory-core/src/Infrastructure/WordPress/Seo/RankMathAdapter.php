<?php
/** Native Rank Math post metadata adapter. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Infrastructure\WordPress\Seo;

use Atal\SeoImages\Contract\SeoAdapterInterface;
use Atal\SeoImages\Domain\SeoMetadata;
use Atal\SeoImages\Exception\PipelineException;

final class RankMathAdapter implements SeoAdapterInterface {
	private const KEYS = array(
		'title'          => 'rank_math_title',
		'description'    => 'rank_math_description',
		'focus_keyword'  => 'rank_math_focus_keyword',
		'og_title'       => 'rank_math_facebook_title',
		'og_description' => 'rank_math_facebook_description',
		'og_image_url'   => 'rank_math_facebook_image',
		'og_image_id'    => 'rank_math_facebook_image_id',
		'canonical_url'  => 'rank_math_canonical_url',
	);
	public function name(): string {
		return 'rank_math'; }
	public function detected(): bool {
		$active = get_option( 'active_plugins', array() );
		return defined( 'RANK_MATH_VERSION' ) || function_exists( 'rank_math' ) || ( is_array( $active ) && in_array( 'seo-by-rank-math/rank-math.php', $active, true ) ); }
	public function version(): ?string {
		$value = defined( 'RANK_MATH_VERSION' ) ? constant( 'RANK_MATH_VERSION' ) : null;
		if ( is_string( $value ) && '' !== $value ) {
			return $value; }
		if ( defined( 'WP_PLUGIN_DIR' ) && function_exists( 'get_plugin_data' ) ) {
			$root = constant( 'WP_PLUGIN_DIR' );
			if ( is_string( $root ) ) {
				$data    = get_plugin_data( $root . '/seo-by-rank-math/rank-math.php', false, false );
				$version = $data['Version'] ?? null;
				return is_string( $version ) && '' !== $version ? $version : null; }
		}
		return null;
	}
	public function snapshot( int $post_id ): array {
		$fields = array();
		foreach ( self::KEYS as $name => $key ) {
			$fields[ $name ] = array(
				'exists' => metadata_exists( 'post', $post_id, $key ),
				'value'  => get_post_meta( $post_id, $key, true ),
			);
		} return array( 'fields' => $fields ); }
	public function apply_and_verify( int $post_id, SeoMetadata $metadata ): array {
		if ( ! $this->detected() ) {
			throw new PipelineException( 'Rank Math is inactive.' ); }
		$values = $metadata->to_array();
		foreach ( self::KEYS as $name => $key ) {
			if ( 'canonical_url' === $name && null === $metadata->canonical_url() ) {
				continue;
			} $value = 'og_image_id' === $name ? (string) $metadata->og_image_id() : $values[ $name ];
			$this->write( $post_id, $key, $value ); }
		return $this->verify( $post_id, $metadata );
	}
	public function verify( int $post_id, SeoMetadata $metadata ): array {
		$expected = $metadata->to_array();
		foreach ( self::KEYS as $name => $key ) {
			if ( 'canonical_url' === $name && null === $metadata->canonical_url() ) {
				continue;
			} $actual = get_post_meta( $post_id, $key, true );
			$wanted   = 'og_image_id' === $name ? (string) $metadata->og_image_id() : $expected[ $name ];
			if ( $this->string_value( $wanted ) !== $this->string_value( $actual ) ) {
				throw new PipelineException( 'Rank Math native metadata verification failed for ' . $name . '.' ); }
		}
		return array(
			'status'           => 'PASS',
			'adapter'          => $this->name(),
			'version'          => $this->version(),
			'native_ui_fields' => array_values( self::KEYS ),
			'title'            => $metadata->title(),
			'description'      => $metadata->description(),
			'focus_keyword'    => $metadata->focus_keyword(),
			'og_image_url'     => $metadata->og_image_url(),
		);
	}
	public function restore( int $post_id, array $snapshot ): void {
		$fields = $snapshot['fields'] ?? null;
		if ( ! is_array( $fields ) || array_is_list( $fields ) ) {
			throw new PipelineException( 'The Rank Math rollback snapshot is malformed.' ); }
		foreach ( self::KEYS as $name => $key ) {
			$field = $fields[ $name ] ?? null;
			if ( ! is_array( $field ) || array_is_list( $field ) ) {
				throw new PipelineException( 'The Rank Math rollback field is malformed.' );
			} if ( true === ( $field['exists'] ?? false ) ) {
				$this->write( $post_id, $key, $field['value'] ?? '' );
			} else {
				$deleted = delete_post_meta( $post_id, $key );
				if ( ! $deleted && metadata_exists( 'post', $post_id, $key ) ) {
					throw new PipelineException( 'Rank Math rollback could not remove a Task 05 field.' ); }
			}
		}
	}
	private function write( int $post_id, string $key, mixed $value ): void {
		$current = get_post_meta( $post_id, $key, true );
		if ( $current === $value || $this->string_value( $current ) === $this->string_value( $value ) ) {
			return;
		} if ( false === update_post_meta( $post_id, $key, $value ) ) {
			throw new PipelineException( 'Rank Math rejected a native metadata write.' ); } }
	private function string_value( mixed $value ): string {
		return is_string( $value ) || is_int( $value ) || is_float( $value ) ? (string) $value : ''; }
}
