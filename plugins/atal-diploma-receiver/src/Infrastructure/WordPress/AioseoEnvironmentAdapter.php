<?php
/** AIOSEO environment adapter. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Domain\Receiver\AioseoAdapterInterface;
use Atal\SeoImages\Contract\SeoAdapterInterface;
use Atal\SeoImages\Domain\SeoMetadata;
use Atal\SeoImages\Exception\PipelineException;
final class AioseoEnvironmentAdapter implements AioseoAdapterInterface, SeoAdapterInterface {
	private const MODEL = 'AIOSEO\\Plugin\\Common\\Models\\Post';
	public function __construct( private readonly string $model_class = self::MODEL ) {}
	public function name(): string {
		return 'aioseo'; }
	public function detected(): bool {
		return class_exists( $this->model_class ) && ( function_exists( 'aioseo' ) || defined( 'AIOSEO_VERSION' ) || ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) ) ); }
	public function version(): ?string {
		$value = defined( 'AIOSEO_VERSION' ) ? constant( 'AIOSEO_VERSION' ) : null;
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		} if ( defined( 'WP_PLUGIN_DIR' ) && function_exists( 'get_plugin_data' ) ) {
			$root = constant( 'WP_PLUGIN_DIR' );
			if ( is_string( $root ) ) {
				$data    = get_plugin_data( $root . '/all-in-one-seo-pack/all_in_one_seo_pack.php', false, false );
				$version = $data['Version'] ?? null;
				return is_string( $version ) && '' !== $version ? $version : null;
			}
		} return null; }

	public function write_and_verify( int $post_id, array $payload ): array {
		$data = array(
			'title'       => sanitize_text_field( $payload['title'] ),
			'description' => sanitize_textarea_field( $payload['description'] ),
			'keyphrases'  => array(
				'focus'      => array(
					'keyphrase' => sanitize_text_field( $payload['focus_keyphrase'] ),
					'score'     => 0,
					'analysis'  => array(),
				),
				'additional' => array(),
			),
		);
		$this->save( $post_id, $data );
		$state       = $this->snapshot( $post_id );
		$title       = $this->nullable_string( $state['title'] ?? null );
		$description = $this->nullable_string( $state['description'] ?? null );
		$keyphrase   = $this->focus_keyphrase( $state['keyphrases'] ?? null );
		if ( $payload['title'] !== $title || $payload['description'] !== $description || $payload['focus_keyphrase'] !== $keyphrase ) {
			throw new ReceiverException( 'receiver_aioseo_write_failed', 'AIOSEO did not verify the exact title, description, and focus keyphrase.', 500 );
		}
		return array(
			'status'          => 'accepted',
			'title'           => $title,
			'description'     => $description,
			'focus_keyphrase' => $keyphrase,
		);
	}

	public function snapshot( int $post_id ): array {
		$model = $this->model( $post_id );
		$data  = get_object_vars( $model );
		return array(
			'title'               => $this->nullable_string( $data['title'] ?? null ),
			'description'         => $this->nullable_string( $data['description'] ?? null ),
			'keyphrases'          => $this->json_object_or_null( $data['keyphrases'] ?? null, 'AIOSEO keyphrases' ),
			'og_title'            => $this->nullable_string( $data['og_title'] ?? null ),
			'og_description'      => $this->nullable_string( $data['og_description'] ?? null ),
			'og_image_type'       => $this->nullable_string( $data['og_image_type'] ?? null ),
			'og_image_custom_url' => $this->nullable_string( $data['og_image_custom_url'] ?? null ),
			'canonical_url'       => $this->nullable_string( $data['canonical_url'] ?? null ),
		);
	}

	public function restore( int $post_id, array $snapshot ): void {
		$this->save(
			$post_id,
			array(
				'title'               => $this->nullable_string( $snapshot['title'] ?? null ),
				'description'         => $this->nullable_string( $snapshot['description'] ?? null ),
				'keyphrases'          => $this->json_object_or_null( $snapshot['keyphrases'] ?? null, 'AIOSEO recovery keyphrases' ),
				'og_title'            => $this->nullable_string( $snapshot['og_title'] ?? null ),
				'og_description'      => $this->nullable_string( $snapshot['og_description'] ?? null ),
				'og_image_type'       => $this->nullable_string( $snapshot['og_image_type'] ?? null ),
				'og_image_custom_url' => $this->nullable_string( $snapshot['og_image_custom_url'] ?? null ),
				'canonical_url'       => $this->nullable_string( $snapshot['canonical_url'] ?? null ),
			)
		);
	}

	public function apply_and_verify( int $post_id, SeoMetadata $metadata ): array {
		if ( ! $this->detected() ) {
			throw new PipelineException( 'AIOSEO is inactive.' ); }
		$current             = $this->snapshot( $post_id );
		$keyphrases          = $this->json_object_or_null( $current['keyphrases'] ?? null, 'AIOSEO keyphrases' ) ?? array();
		$keyphrases['focus'] = array(
			'keyphrase' => $metadata->focus_keyword(),
			'score'     => 0,
			'analysis'  => array(),
		);
		if ( ! isset( $keyphrases['additional'] ) ) {
			$keyphrases['additional'] = array(); }
		$data = array(
			'title'               => $metadata->title(),
			'description'         => $metadata->description(),
			'keyphrases'          => $keyphrases,
			'og_title'            => $metadata->og_title(),
			'og_description'      => $metadata->og_description(),
			'og_image_type'       => 'custom',
			'og_image_custom_url' => $metadata->og_image_url(),
		);
		if ( null !== $metadata->canonical_url() ) {
			$data['canonical_url'] = $metadata->canonical_url(); }
		$this->save( $post_id, $data );
		return $this->verify( $post_id, $metadata );
	}

	public function verify( int $post_id, SeoMetadata $metadata ): array {
		$state  = $this->snapshot( $post_id );
		$checks = array(
			'title'          => $metadata->title() === ( $state['title'] ?? null ),
			'description'    => $metadata->description() === ( $state['description'] ?? null ),
			'focus_keyword'  => $metadata->focus_keyword() === $this->focus_keyphrase( $state['keyphrases'] ?? null ),
			'og_title'       => $metadata->og_title() === ( $state['og_title'] ?? null ),
			'og_description' => $metadata->og_description() === ( $state['og_description'] ?? null ),
			'og_image'       => 'custom' === ( $state['og_image_type'] ?? null ) && $metadata->og_image_url() === ( $state['og_image_custom_url'] ?? null ),
		);
		if ( in_array( false, $checks, true ) ) {
			throw new PipelineException( 'AIOSEO native metadata verification failed.' ); }
		return array(
			'status'        => 'PASS',
			'adapter'       => $this->name(),
			'version'       => $this->version(),
			'checks'        => $checks,
			'title'         => $metadata->title(),
			'description'   => $metadata->description(),
			'focus_keyword' => $metadata->focus_keyword(),
			'og_image_url'  => $metadata->og_image_url(),
		);
	}

	private function model( int $post_id ): object {
		$callable = array( $this->model_class, 'getPost' );
		if ( ! is_callable( $callable ) ) {
			throw new ReceiverException( 'receiver_aioseo_unavailable', 'The verified AIOSEO 4.9.8 post model is unavailable.', 503 );
		}
		$model = call_user_func( $callable, $post_id );
		if ( ! is_object( $model ) ) {
			throw new ReceiverException( 'receiver_aioseo_write_failed', 'AIOSEO returned an invalid post model.', 500 );
		}
		return $model;
	}

	/** @param array<string,mixed> $data Native AIOSEO patch. */
	private function save( int $post_id, array $data ): void {
		$callable = array( $this->model_class, 'savePost' );
		if ( ! is_callable( $callable ) ) {
			throw new ReceiverException( 'receiver_aioseo_unavailable', 'The verified AIOSEO 4.9.8 save contract is unavailable.', 503 );
		}
		$result = call_user_func( $callable, $post_id, $data );
		if ( false === $result || ( is_string( $result ) && '' !== $result ) ) {
			throw new ReceiverException( 'receiver_aioseo_write_failed', 'AIOSEO rejected the native metadata write.', 500 );
		}
	}

	private function nullable_string( mixed $value ): ?string {
		return null === $value || is_string( $value ) ? $value : null;
	}

	private function focus_keyphrase( mixed $keyphrases ): string {
		$keyphrases = $this->json_object_or_null( $keyphrases, 'AIOSEO keyphrases' );
		if ( null === $keyphrases ) {
			return '';
		}
		$focus = $this->json_object_or_null( $keyphrases['focus'] ?? null, 'AIOSEO focus keyphrase' );
		return null !== $focus && is_string( $focus['keyphrase'] ?? null ) ? $focus['keyphrase'] : '';
	}

	/** @return array<string,mixed>|null */
	private function json_object_or_null( mixed $value, string $label ): ?array {
		if ( null === $value ) {
			return null;
		}
		$encoded = wp_json_encode( $value );
		$decoded = false === $encoded ? null : json_decode( $encoded, true );
		if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			throw new ReceiverException( 'receiver_aioseo_write_failed', $label . ' is malformed.', 500 );
		}
		$result = array();
		foreach ( $decoded as $key => $item ) {
			if ( ! is_string( $key ) ) {
				throw new ReceiverException( 'receiver_aioseo_write_failed', $label . ' contains an invalid key.', 500 );
			}
			$result[ $key ] = $item;
		}
		return $result;
	}
}
