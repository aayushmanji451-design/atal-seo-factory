<?php
/** Task 05 isolated test doubles. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Support\SeoImages;

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
use RuntimeException;

final class FakeRuntimeGuard implements RuntimeGuardInterface {
	public int $checks = 0;
	public function assert_ready( AcceptanceFixture $fixture ): void {
		unset( $fixture );
		++$this->checks; }
}

final class FakeFixtureRepository implements FixtureRepositoryInterface {
	public int $featured;
	public int $assignments = 0;
	public function __construct( int $featured ) {
		$this->featured = $featured; }
	public function snapshot( AcceptanceFixture $fixture ): array {
		return array(
			'post_id'           => $fixture->post_id(),
			'post_status'       => 'draft',
			'post_title'        => $fixture->seo_title(),
			'post_name'         => 'controlled-task-04-draft',
			'article_key'       => $fixture->article_key(),
			'course_key'        => $fixture->course_key(),
			'featured_image_id' => $this->featured,
		); }
	public function assign_featured_image( AcceptanceFixture $fixture, int $attachment_id ): void {
		unset( $fixture );
		$this->featured = $attachment_id;
		++$this->assignments; }
	public function verify_featured_image( AcceptanceFixture $fixture, int $attachment_id ): void {
		unset( $fixture );
		if ( $this->featured !== $attachment_id ) {
			throw new RuntimeException( 'featured mismatch' ); } }
	public function restore_featured_image( AcceptanceFixture $fixture, array $snapshot ): void {
		unset( $fixture );
		$this->featured = is_numeric( $snapshot['featured_image_id'] ?? null ) ? (int) $snapshot['featured_image_id'] : 0; }
}

final class FakeSeoAdapter implements SeoAdapterInterface {
	/** @var array<string,mixed> */ public array $current = array(
		'title'         => 'before',
		'description'   => 'before',
		'focus_keyword' => 'before',
		'og_image_url'  => 'before',
	);
	public int $writes                                    = 0;
	public function name(): string {
		return 'fake_native'; }
	public function detected(): bool {
		return true; }
	public function version(): string {
		return 'test-1.0'; }
	public function snapshot( int $post_id ): array {
		unset( $post_id );
		return $this->current; }
	public function apply_and_verify( int $post_id, SeoMetadata $metadata ): array {
		unset( $post_id );
		$next = $metadata->to_array();
		if ( $this->current !== $next ) {
			$this->current = $next;
			++$this->writes;
		} return $this->verify( 1, $metadata ); }
	public function verify( int $post_id, SeoMetadata $metadata ): array {
		unset( $post_id );
		if ( $this->current !== $metadata->to_array() ) {
			throw new RuntimeException( 'SEO mismatch' );
		} return array(
			'status'    => 'PASS',
			'native_ui' => true,
			'og_image'  => $metadata->og_image_url(),
		); }
	public function restore( int $post_id, array $snapshot ): void {
		unset( $post_id );
		$this->current = $snapshot; }
}

final class FakeImageManager implements ImageManagerInterface {
	/** @var array<string,ImageResult> */ private array $results = array();
	private int $next_id  = 9001;
	public int $generated = 0;
	public int $deleted   = 0;
	public function ensure( ImageSpecification $specification ): ImageResult {
		$existing = $this->results[ $specification->fingerprint() ] ?? null;
		if ( $existing instanceof ImageResult ) {
			return new ImageResult( $existing->attachment_id(), $existing->url(), $existing->filename(), $existing->mime(), $existing->width(), $existing->height(), $existing->alt_text(), $existing->output_hash(), $existing->fingerprint(), $existing->renderer_version(), true, false ); }
		$id     = $this->next_id++;
		$result = new ImageResult( $id, 'https://staging.test/uploads/' . $specification->filename(), $specification->filename(), ImageSpecification::MIME, ImageSpecification::WIDTH, ImageSpecification::HEIGHT, $specification->alt_text(), hash( 'sha256', $specification->fingerprint() . ':webp' ), $specification->fingerprint(), 'task05-local-webp-v1', false, true );
		$this->results[ $specification->fingerprint() ] = $result;
		++$this->generated;
		return $result;
	}
	public function verify( ImageResult $result ): void {
		if ( ImageSpecification::MIME !== $result->mime() || 1200 !== $result->width() || 630 !== $result->height() || 1 !== preg_match( '/^[a-f0-9]{64}$/', $result->output_hash() ) ) {
			throw new RuntimeException( 'image mismatch' ); } }
	public function delete_if_orphan( ImageResult $result, array $protected_ids ): bool {
		if ( in_array( $result->attachment_id(), $protected_ids, true ) ) {
			return false;
		} unset( $this->results[ $result->fingerprint() ] );
		++$this->deleted;
		return true; }
	public function corrupt( string $fingerprint ): void {
		unset( $this->results[ $fingerprint ] ); }
}

final class FakeStateStore implements StateStoreInterface {
	/** @var array<string,array<string,mixed>> */ public array $states = array();
	public function load( string $article_key ): ?array {
		return $this->states[ $article_key ] ?? null; }
	public function save( string $article_key, array $state ): void {
		$this->states[ $article_key ] = $state; }
}

final class FakeAuditLogger implements AuditLoggerInterface {
	/** @var list<array<string,mixed>> */ public array $events = array();
	public function record( string $event, string $outcome, array $context ): void {
		$encoded = json_encode( $context );
		if ( is_string( $encoded ) && 1 === preg_match( '/secret|token|password|signature/i', $encoded ) ) {
			throw new RuntimeException( 'unsafe audit' );
		} $this->events[] = array(
			'event'   => $event,
			'outcome' => $outcome,
			'context' => $context,
		); }
}
