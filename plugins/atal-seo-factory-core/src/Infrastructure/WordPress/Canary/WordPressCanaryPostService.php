<?php
/** Institute-owned deterministic draft and Rank Math adapter. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Infrastructure\WordPress\Canary;

use Atal\SeoFactory\Application\Canary\CanaryException;
use Atal\SeoFactory\Domain\Canary\CanaryArticle;
use Atal\SeoFactory\Domain\Canary\CanaryMutation;
use Atal\SeoFactory\Domain\Canary\CanaryPostServiceInterface;
use WP_Error;
use WP_Post;

final class WordPressCanaryPostService implements CanaryPostServiceInterface {
	public const OWNER_META            = '_atal_seo_factory_canary_article_key';
	public const COURSE_META           = '_atal_seo_factory_canary_course_key';
	public const TARGET_META           = '_atal_seo_factory_canary_target_site';
	public const H1_META               = '_atal_seo_factory_canary_h1';
	public const ASSET_META            = '_atal_seo_factory_canary_asset_key';
	public const RANK_MATH_TITLE       = 'rank_math_title';
	public const RANK_MATH_DESCRIPTION = 'rank_math_description';
	public const RANK_MATH_FOCUS       = 'rank_math_focus_keyword';

	public function create_draft( CanaryArticle $article ): CanaryMutation {
		$this->verify_attachment( $article->featured_image_id() );
		$ids = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'any',
				'numberposts'      => 2,
				'fields'           => 'ids',
				'meta_key'         => self::OWNER_META,
				'meta_value'       => $article->article_key(),
				'suppress_filters' => true,
			)
		);
		if ( 1 < count( $ids ) ) {
			throw new CanaryException( 'Multiple Institute drafts share the deterministic canary identity.' );
		}
		if ( isset( $ids[0] ) ) {
			$this->verify_draft( $article, $ids[0] );
			return new CanaryMutation( $ids[0], false, null );
		}
		$collision = get_page_by_path( $article->slug(), OBJECT, 'post' );
		if ( $collision instanceof WP_Post ) {
			throw new CanaryException( 'An unrelated post already uses the deterministic canary slug.' );
		}
		$result = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => $article->title(),
				'post_name'    => $article->slug(),
				'post_content' => $article->content(),
				'post_excerpt' => $article->excerpt(),
			),
			true
		);
		if ( $result instanceof WP_Error || 1 > $result ) {
			throw new CanaryException( 'The Institute canary draft could not be created.' );
		}
		$this->meta( $result, self::OWNER_META, $article->article_key() );
		$this->meta( $result, self::COURSE_META, $article->course_key() );
		$this->meta( $result, self::TARGET_META, $article->target_site() );
		$this->meta( $result, self::H1_META, $article->h1() );
		$this->meta( $result, self::ASSET_META, $article->image_asset_key() );
		$this->meta( $result, self::RANK_MATH_TITLE, $article->seo_title() );
		$this->meta( $result, self::RANK_MATH_DESCRIPTION, $article->meta_description() );
		$this->meta( $result, self::RANK_MATH_FOCUS, $article->focus_keyword() );
		if ( false === set_post_thumbnail( $result, $article->featured_image_id() ) ) {
			throw new CanaryException( 'The verified Institute canary image could not be attached.' );
		}
		$this->verify_draft( $article, $result );
		return new CanaryMutation( $result, true, null );
	}

	public function verify_draft( CanaryArticle $article, int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'post' !== get_post_type( $post_id ) || 'draft' !== $post->post_status ) {
			throw new CanaryException( 'The Institute canary is not an owned WordPress draft.' );
		}
		$expected = array(
			self::OWNER_META            => $article->article_key(),
			self::COURSE_META           => $article->course_key(),
			self::TARGET_META           => $article->target_site(),
			self::H1_META               => $article->h1(),
			self::ASSET_META            => $article->image_asset_key(),
			self::RANK_MATH_TITLE       => $article->seo_title(),
			self::RANK_MATH_DESCRIPTION => $article->meta_description(),
			self::RANK_MATH_FOCUS       => $article->focus_keyword(),
		);
		foreach ( $expected as $key => $value ) {
			if ( get_post_meta( $post_id, $key, true ) !== $value ) {
				throw new CanaryException( 'Institute canary metadata verification failed.' );
			}
		}
		if ( $article->title() !== $post->post_title || $article->slug() !== $post->post_name || $article->content() !== $post->post_content || $article->featured_image_id() !== get_post_thumbnail_id( $post_id ) ) {
			throw new CanaryException( 'Institute canary draft content or image verification failed.' );
		}
		return array(
			'post_id'           => $post_id,
			'post_status'       => 'draft',
			'course_key'        => $article->course_key(),
			'article_key'       => $article->article_key(),
			'rank_math'         => 'accepted',
			'featured_image_id' => $article->featured_image_id(),
		);
	}

	public function rollback( int $post_id, bool $created, ?array $previous_state ): void {
		unset( $previous_state );
		if ( ! $created ) {
			return;
		}
		$post  = get_post( $post_id );
		$owner = get_post_meta( $post_id, self::OWNER_META, true );
		if ( ! $post instanceof WP_Post || 'draft' !== $post->post_status || ! is_string( $owner ) || ! str_starts_with( $owner, 'article_task04_' ) ) {
			throw new CanaryException( 'Rollback can remove only the owned Task 04 draft.' );
		}
		if ( false === wp_delete_post( $post_id, true ) ) {
			throw new CanaryException( 'The owned Institute canary draft could not be removed.' );
		}
	}

	private function verify_attachment( int $attachment_id ): void {
		if ( 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
			throw new CanaryException( 'The Institute featured_image_id is not an existing image attachment.' );
		}
	}

	private function meta( int $post_id, string $key, string $value ): void {
		if ( false === update_post_meta( $post_id, $key, $value ) ) {
			throw new CanaryException( 'The Institute canary metadata could not be saved.' );
		}
	}
}
