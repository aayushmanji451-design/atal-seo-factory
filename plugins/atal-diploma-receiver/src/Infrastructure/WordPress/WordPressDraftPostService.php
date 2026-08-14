<?php
/** Receiver-owned draft persistence and recovery. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Domain\Receiver\ArticlePayload;
use Atal\DiplomaReceiver\Domain\Receiver\AioseoAdapterInterface;
use Atal\DiplomaReceiver\Domain\Receiver\MutationResult;
use Atal\DiplomaReceiver\Domain\Receiver\PostServiceInterface;
use WP_Error;
use WP_Post;
final class WordPressDraftPostService implements PostServiceInterface {
	private const OWNER_META          = '_atal_diploma_receiver_article_key';
	private const AIOSEO_PAYLOAD_META = '_atal_diploma_receiver_aioseo_payload';
	private const COURSE_META         = '_atal_diploma_receiver_course_key';
	private const TARGET_META         = '_atal_diploma_receiver_target_site';
	public function __construct( private readonly AioseoAdapterInterface $aioseo ) {}
	public function upsert_draft( ArticlePayload $payload ): MutationResult {
		$ids = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'any',
				'numberposts'      => 2,
				'fields'           => 'ids',
				'meta_key'         => self::OWNER_META,
				'meta_value'       => $payload->article_key(),
				'suppress_filters' => true,
			)
		);
		if ( 1 < count( $ids ) ) {
			throw new ReceiverException( 'receiver_duplicate_identity', 'Multiple receiver-owned drafts share the article key.', 409 ); }
		$post_id   = isset( $ids[0] ) ? $ids[0] : 0;
		$created   = 0 === $post_id;
		$previous  = $created ? null : $this->snapshot( $post_id );
		$post_data = array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $payload->title(),
			'post_name'    => $payload->slug(),
			'post_content' => $payload->content(),
			'post_excerpt' => $payload->excerpt(),
		);
		if ( ! $created ) {
			$post_data['ID'] = $post_id; }
		$result = $created ? wp_insert_post( $post_data, true ) : wp_update_post( $post_data, true );
		if ( $result instanceof WP_Error || 1 > $result ) {
			throw new ReceiverException( 'receiver_post_write_failed', 'The receiver-owned draft could not be saved.', 500 ); }
		$post_id = $result;
		$this->persist_meta( $post_id, self::OWNER_META, $payload->article_key() );
		$this->persist_meta( $post_id, self::AIOSEO_PAYLOAD_META, $payload->aioseo() );
		$this->persist_meta( $post_id, self::COURSE_META, $payload->course_key() );
		$this->persist_meta( $post_id, self::TARGET_META, 'atal_diploma' );
		if ( null !== $payload->featured_image_id() && false === set_post_thumbnail( $post_id, $payload->featured_image_id() ) ) {
			throw new ReceiverException( 'receiver_featured_image_write_failed', 'The verified featured image could not be attached.', 500 ); }
		return new MutationResult( $post_id, $created, $previous );
	}
	public function recover( int $post_id, bool $created, ?array $previous_state ): void {
		$owned = get_post_meta( $post_id, self::OWNER_META, true );
		if ( ! is_string( $owned ) || '' === $owned ) {
			throw new ReceiverException( 'receiver_recovery_ownership_failed', 'Recovery can affect only a receiver-owned draft.', 409 ); }
		if ( $created ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post || 'draft' !== $post->post_status || false === wp_delete_post( $post_id, true ) ) {
				throw new ReceiverException( 'receiver_recovery_failed', 'The receiver-created draft could not be removed.', 500 );
			} return; }
		if ( null === $previous_state ) {
			throw new ReceiverException( 'receiver_recovery_failed', 'No previous receiver-owned state is available.', 500 ); }
		$data   = array(
			'ID'           => $post_id,
			'post_type'    => 'post',
			'post_status'  => $this->state_string( $previous_state, 'post_status' ),
			'post_title'   => $this->state_string( $previous_state, 'post_title' ),
			'post_name'    => $this->state_string( $previous_state, 'post_name' ),
			'post_content' => $this->state_string( $previous_state, 'post_content' ),
			'post_excerpt' => $this->state_string( $previous_state, 'post_excerpt' ),
		);
		$result = wp_update_post( $data, true );
		if ( $result instanceof WP_Error ) {
			throw new ReceiverException( 'receiver_recovery_failed', 'The prior receiver-owned draft state could not be restored.', 500 ); }
		if ( isset( $previous_state['aioseo_payload'] ) && is_array( $previous_state['aioseo_payload'] ) ) {
			$this->persist_meta( $post_id, self::AIOSEO_PAYLOAD_META, $previous_state['aioseo_payload'] ); }
		if ( isset( $previous_state['aioseo_native'] ) && is_array( $previous_state['aioseo_native'] ) ) {
			$this->aioseo->restore( $post_id, $this->object( $previous_state['aioseo_native'], 'AIOSEO recovery state' ) ); }
		$this->persist_meta( $post_id, self::COURSE_META, $this->state_string( $previous_state, 'course_key' ) );
		$this->persist_meta( $post_id, self::TARGET_META, $this->state_string( $previous_state, 'target_site' ) );
		$thumbnail = $previous_state['thumbnail_id'] ?? 0;
		if ( is_int( $thumbnail ) && 0 < $thumbnail ) {
			if ( false === set_post_thumbnail( $post_id, $thumbnail ) ) {
				throw new ReceiverException( 'receiver_recovery_failed', 'The previous featured image could not be restored.', 500 );
			}
		} elseif ( false === delete_post_thumbnail( $post_id ) ) {
			throw new ReceiverException( 'receiver_recovery_failed', 'The receiver featured image could not be removed.', 500 ); }
	}
	/** @return array<string,mixed> */
	private function snapshot( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			throw new ReceiverException( 'receiver_post_not_found', 'The receiver-owned draft no longer exists.', 404 );
		} $aioseo = get_post_meta( $post_id, self::AIOSEO_PAYLOAD_META, true );
		$course   = get_post_meta( $post_id, self::COURSE_META, true );
		$target   = get_post_meta( $post_id, self::TARGET_META, true );
		return array(
			'post_status'    => $post->post_status,
			'post_title'     => $post->post_title,
			'post_name'      => $post->post_name,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'thumbnail_id'   => get_post_thumbnail_id( $post_id ),
			'aioseo_payload' => is_array( $aioseo ) ? $aioseo : array(),
			'aioseo_native'  => $this->aioseo->snapshot( $post_id ),
			'course_key'     => is_string( $course ) ? $course : '',
			'target_site'    => is_string( $target ) ? $target : '',
		); }
	/** @param array<string,mixed> $state */ private function state_string( array $state, string $key ): string {
		$value = $state[ $key ] ?? null;
		return is_string( $value ) ? $value : ''; }
	private function persist_meta( int $post_id, string $key, mixed $value ): void {
		if ( false === update_post_meta( $post_id, $key, $value ) ) {
			throw new ReceiverException( 'receiver_meta_write_failed', 'Receiver-owned metadata could not be saved.', 500 ); } }
	/** @return array<string,mixed> */
	private function object( mixed $value, string $label ): array {
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			throw new ReceiverException( 'receiver_recovery_failed', $label . ' is malformed.', 500 );
		}
		$result = array();
		foreach ( $value as $key => $item ) {
			if ( ! is_string( $key ) ) {
				throw new ReceiverException( 'receiver_recovery_failed', $label . ' contains an invalid key.', 500 );
			}
			$result[ $key ] = $item;
		}
		return $result;
	}
}
