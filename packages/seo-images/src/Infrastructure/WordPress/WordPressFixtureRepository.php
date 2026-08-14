<?php
/** Exact owned-draft fixture repository. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Infrastructure\WordPress;

use Atal\SeoImages\Contract\FixtureRepositoryInterface;
use Atal\SeoImages\Domain\AcceptanceFixture;
use Atal\SeoImages\Exception\PipelineException;
use WP_Post;

final class WordPressFixtureRepository implements FixtureRepositoryInterface {
	public function __construct( private readonly string $owner_meta, private readonly string $course_meta ) {}

	public function snapshot( AcceptanceFixture $fixture ): array {
		$post = get_post( $fixture->post_id() );
		if ( ! $post instanceof WP_Post || 'post' !== get_post_type( $fixture->post_id() ) || 'draft' !== $post->post_status ) {
			throw new PipelineException( 'Task 05 can modify only the exact controlled staging draft.' ); }
		if ( $fixture->article_key() !== get_post_meta( $fixture->post_id(), $this->owner_meta, true ) || $fixture->course_key() !== get_post_meta( $fixture->post_id(), $this->course_meta, true ) ) {
			throw new PipelineException( 'The controlled staging draft identity does not match Task 05.' ); }
		return array(
			'post_id'           => $fixture->post_id(),
			'post_status'       => $post->post_status,
			'post_title'        => $post->post_title,
			'post_name'         => $post->post_name,
			'article_key'       => $fixture->article_key(),
			'course_key'        => $fixture->course_key(),
			'featured_image_id' => (int) get_post_thumbnail_id( $fixture->post_id() ),
		);
	}
	public function assign_featured_image( AcceptanceFixture $fixture, int $attachment_id ): void {
		$this->snapshot( $fixture );
		if ( (int) get_post_thumbnail_id( $fixture->post_id() ) === $attachment_id ) {
			return;
		}
		if ( false === set_post_thumbnail( $fixture->post_id(), $attachment_id ) ) {
			throw new PipelineException( 'The Task 05 generated image could not be assigned.' ); } }
	public function verify_featured_image( AcceptanceFixture $fixture, int $attachment_id ): void {
		$this->snapshot( $fixture );
		if ( (int) get_post_thumbnail_id( $fixture->post_id() ) !== $attachment_id ) {
			throw new PipelineException( 'The Task 05 featured image verification failed.' ); } }
	public function restore_featured_image( AcceptanceFixture $fixture, array $snapshot ): void {
		$this->snapshot( $fixture );
		$previous = is_numeric( $snapshot['featured_image_id'] ?? null ) ? (int) $snapshot['featured_image_id'] : 0;
		if ( 0 < $previous ) {
			if ( false === set_post_thumbnail( $fixture->post_id(), $previous ) ) {
				throw new PipelineException( 'The prior featured image could not be restored.' ); }
		} elseif ( false === delete_post_thumbnail( $fixture->post_id() ) ) {
			throw new PipelineException( 'The Task 05 featured image could not be removed.' ); }
		if ( (int) get_post_thumbnail_id( $fixture->post_id() ) !== $previous ) {
			throw new PipelineException( 'The prior featured image restoration did not verify.' ); }
	}
}
