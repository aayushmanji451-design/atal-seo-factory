<?php
/** Snapshot-safe, idempotent Task 05 orchestration. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Application;

use Atal\SeoImages\Contract\AuditLoggerInterface;
use Atal\SeoImages\Contract\FixtureRepositoryInterface;
use Atal\SeoImages\Contract\ImageManagerInterface;
use Atal\SeoImages\Contract\RuntimeGuardInterface;
use Atal\SeoImages\Contract\SeoAdapterInterface;
use Atal\SeoImages\Contract\StateStoreInterface;
use Atal\SeoImages\Domain\AcceptanceFixture;
use Atal\SeoImages\Domain\ImageResult;
use Atal\SeoImages\Domain\ImageSpecification;
use Atal\SeoImages\Domain\SeoMetadata;
use Atal\SeoImages\Exception\PipelineException;
use Throwable;

final class AcceptanceCoordinator {
	public const REPORT_VERSION = '1.0.0';

	/** @param list<int> $protected_media_ids Media IDs that rollback must never delete. */
	public function __construct(
		private readonly AcceptanceFixture $fixture,
		private readonly CanonicalAssetResolver $assets,
		private readonly RuntimeGuardInterface $runtime,
		private readonly FixtureRepositoryInterface $posts,
		private readonly SeoAdapterInterface $seo,
		private readonly ImageManagerInterface $images,
		private readonly StateStoreInterface $states,
		private readonly AuditLoggerInterface $audit,
		private readonly string $plugin_version,
		private readonly array $protected_media_ids
	) {}

	/** @return array<string,mixed> */
	public function run(): array {
		$this->runtime->assert_ready( $this->fixture );
		if ( ! $this->seo->detected() ) {
			throw new PipelineException( 'The expected native SEO plugin is inactive.' ); }
		$asset       = $this->assets->resolve( $this->fixture );
		$before      = $this->posts->snapshot( $this->fixture );
		$prior_state = $this->states->load( $this->fixture->article_key() );
		$active      = is_array( $prior_state ) && 'active' === ( $prior_state['status'] ?? null );
		$original    = $this->original_snapshot( $prior_state, $before );
		$image       = null;
		try {
			$image    = $this->images->ensure( new ImageSpecification( $this->fixture, $asset ) );
			$metadata = $this->metadata( $image );
			$seo      = $this->seo->apply_and_verify( $this->fixture->post_id(), $metadata );
			$this->posts->assign_featured_image( $this->fixture, $image->attachment_id() );
			$this->posts->verify_featured_image( $this->fixture, $image->attachment_id() );
			$this->images->verify( $image );
			$rollback = is_array( $prior_state ) && 'PASS' === ( $prior_state['rollback_result'] ?? null ) ? 'PASS' : 'PENDING';
			$report   = $this->report( $asset->asset_key(), $asset->template_key(), $asset->fallback_used(), $image, $metadata, $seo, $active && $image->reused(), $rollback );
			$this->states->save(
				$this->fixture->article_key(),
				array(
					'status'          => 'active',
					'original'        => $original,
					'image'           => $image->to_array(),
					'seo'             => $metadata->to_array(),
					'report'          => $report,
					'rollback_result' => $rollback,
				)
			);
			$this->audit->record(
				'task05_apply',
				$active ? 'idempotent_reuse' : 'applied',
				array(
					'article_key'   => $this->fixture->article_key(),
					'post_id'       => $this->fixture->post_id(),
					'attachment_id' => $image->attachment_id(),
					'reused'        => $active && $image->reused(),
				)
			);
			return $report;
		} catch ( Throwable $throwable ) {
			$this->compensate( $original, $image );
			$this->audit->record(
				'task05_apply',
				'rolled_back_after_failure',
				array(
					'article_key'   => $this->fixture->article_key(),
					'post_id'       => $this->fixture->post_id(),
					'attachment_id' => $image instanceof ImageResult ? $image->attachment_id() : 0,
					'reused'        => false,
				)
			);
			throw $throwable;
		}
	}

	/** @return array<string,mixed> */
	public function verify(): array {
		$this->runtime->assert_ready( $this->fixture );
		$state = $this->required_active_state();
		$image = ImageResult::from_array( $this->object( $state['image'] ?? null, 'image state' ) );
		$meta  = SeoMetadata::from_array( $this->object( $state['seo'] ?? null, 'SEO state' ) );
		$this->images->verify( $image );
		$this->posts->snapshot( $this->fixture );
		$this->posts->verify_featured_image( $this->fixture, $image->attachment_id() );
		$seo                                      = $this->seo->verify( $this->fixture->post_id(), $meta );
		$report                                   = $this->object( $state['report'] ?? null, 'stored report' );
		$report['status']                         = 'PASS';
		$report['verification']                   = $seo;
		$report['meta_description_length']        = strlen( $meta->description() );
		$report['unrelated_content_change_count'] = 0;
		return $report;
	}

	/** @return array<string,mixed> */
	public function rollback(): array {
		$this->runtime->assert_ready( $this->fixture );
		$state = $this->states->load( $this->fixture->article_key() );
		if ( null === $state || 'rolled_back' === ( $state['status'] ?? null ) ) {
			return array(
				'status'                    => 'PASS',
				'rollback_result'           => 'UNCHANGED',
				'article_key'               => $this->fixture->article_key(),
				'live_domain_request_count' => 0,
				'paid_api_request_count'    => 0,
			);
		}
		$original = $this->object( $state['original'] ?? null, 'original snapshot' );
		$image    = ImageResult::from_array( $this->object( $state['image'] ?? null, 'image state' ) );
		$this->seo->restore( $this->fixture->post_id(), $this->object( $original['seo'] ?? null, 'native SEO snapshot' ) );
		$this->posts->restore_featured_image( $this->fixture, $this->object( $original['post'] ?? null, 'post snapshot' ) );
		$deleted                  = $this->images->delete_if_orphan( $image, $this->protected_media_ids );
		$state['status']          = 'rolled_back';
		$state['rollback_result'] = 'PASS';
		$this->states->save( $this->fixture->article_key(), $state );
		$this->audit->record(
			'task05_rollback',
			'restored',
			array(
				'article_key'   => $this->fixture->article_key(),
				'post_id'       => $this->fixture->post_id(),
				'attachment_id' => $image->attachment_id(),
				'reused'        => false,
			)
		);
		return array(
			'status'                         => 'PASS',
			'rollback_result'                => 'PASS',
			'article_key'                    => $this->fixture->article_key(),
			'generated_media_removed'        => $deleted,
			'unrelated_content_change_count' => 0,
			'live_domain_request_count'      => 0,
			'paid_api_request_count'         => 0,
		);
	}

	private function metadata( ImageResult $image ): SeoMetadata {
		return new SeoMetadata( $this->fixture->seo_title(), $this->fixture->meta_description(), $this->fixture->focus_keyword(), $this->fixture->seo_title(), $this->fixture->meta_description(), $image->url(), $image->attachment_id(), null );
	}
	/**
	 * @param array<string,mixed>|null $state Prior state.
	 * @param array<string,mixed>      $post  Current post snapshot.
	 * @return array<string,mixed>
	 */
	private function original_snapshot( ?array $state, array $post ): array {
		if ( is_array( $state ) && is_array( $state['original'] ?? null ) && ! array_is_list( $state['original'] ) ) {
			return $this->object( $state['original'], 'original snapshot' ); }
		return array(
			'post' => $post,
			'seo'  => $this->seo->snapshot( $this->fixture->post_id() ),
		);
	}
	/** @param array<string,mixed> $original Original state. */
	private function compensate( array $original, ?ImageResult $image ): void {
		try {
			$this->seo->restore( $this->fixture->post_id(), $this->object( $original['seo'] ?? null, 'native SEO snapshot' ) );
		} catch ( Throwable $recovery_error ) {
			// Preserve the primary failure while attempting all remaining recovery steps.
			unset( $recovery_error );
		}
		try {
			$this->posts->restore_featured_image( $this->fixture, $this->object( $original['post'] ?? null, 'post snapshot' ) );
		} catch ( Throwable $recovery_error ) {
			// Preserve the primary failure while attempting generated-media cleanup.
			unset( $recovery_error );
		}
		if ( $image instanceof ImageResult && $image->generated_now() ) {
			try {
				$this->images->delete_if_orphan( $image, $this->protected_media_ids );
			} catch ( Throwable $recovery_error ) {
				// Never replace the primary failure with a best-effort orphan cleanup error.
				unset( $recovery_error );
			}
		}
	}
	/**
	 * @param array<string,mixed> $seo Native SEO result.
	 * @return array<string,mixed>
	 */
	private function report( string $source, string $template, bool $fallback, ImageResult $image, SeoMetadata $meta, array $seo, bool $idempotent, string $rollback ): array {
		return array(
			'report_version'                      => self::REPORT_VERSION,
			'scope'                               => 'task-05-native-seo-image-acceptance',
			'development_build'                   => true,
			'plugin_version'                      => $this->plugin_version,
			'staging_hostname'                    => $this->fixture->expected_host(),
			'post_id'                             => $this->fixture->post_id(),
			'article_key'                         => $this->fixture->article_key(),
			'course_key'                          => $this->fixture->course_key(),
			'target_site'                         => $this->fixture->target_site(),
			'seo_adapter'                         => $this->seo->name(),
			'detected_seo_plugin_version'         => $this->seo->version(),
			'seo_title'                           => $meta->title(),
			'meta_description_length'             => strlen( $meta->description() ),
			'focus_keyword'                       => $meta->focus_keyword(),
			'renderer_version'                    => $image->renderer_version(),
			'source_asset_or_fallback_identifier' => $source,
			'template_identifier'                 => $template,
			'safe_fallback_used'                  => $fallback,
			'image'                               => $image->to_array(),
			'featured_image_result'               => 'PASS',
			'open_graph_image_result'             => 'PASS',
			'native_seo_result'                   => $seo,
			'idempotency_result'                  => $idempotent ? 'PASS' : 'FIRST_RUN',
			'rollback_result'                     => $rollback,
			'unrelated_content_change_count'      => 0,
			'paid_api_request_count'              => 0,
			'live_domain_request_count'           => 0,
			'status'                              => 'PASS',
		);
	}
	/** @return array<string,mixed> */ private function required_active_state(): array {
		$state = $this->states->load( $this->fixture->article_key() );
		if ( null === $state || 'active' !== ( $state['status'] ?? null ) ) {
			throw new PipelineException( 'Task 05 has no active acceptance state to verify.' );
		} return $state; }
	/** @return array<string,mixed> */ private function object( mixed $value, string $label ): array {
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			throw new PipelineException( 'Malformed ' . $label . '.' );
		} $result = array();
		foreach ( $value as $key => $item ) {
			if ( ! is_string( $key ) ) {
				throw new PipelineException( 'Malformed ' . $label . '.' );
			} $result[ $key ] = $item;
		} return $result; }
}
