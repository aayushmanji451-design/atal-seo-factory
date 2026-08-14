<?php
/** Task 04 canary test doubles. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Support\Canary;

use Atal\SeoFactory\Domain\Canary\CanaryArticle;
use Atal\SeoFactory\Domain\Canary\CanaryAuditLoggerInterface;
use Atal\SeoFactory\Domain\Canary\CanaryMutation;
use Atal\SeoFactory\Domain\Canary\CanaryPostServiceInterface;
use Atal\SeoFactory\Domain\Canary\CanaryRuntimeGuardInterface;
use Atal\SeoFactory\Domain\Canary\CanaryStateRepositoryInterface;
use Atal\SeoFactory\Domain\Canary\DiplomaReceiverClientInterface;
use Atal\SeoFactory\Domain\Storage\TransactionManagerInterface;

final class InMemoryCanaryState implements CanaryStateRepositoryInterface {
	/** @var array<string,array{status:string,payload:array<string,mixed>}> */ public array $records = array();
	public function find( string $article_key ): ?array {
		return $this->records[ $article_key ] ?? null; }
	public function save( CanaryArticle $article, string $status, array $payload ): void {
		$this->records[ $article->article_key() ] = array(
			'status'  => $status,
			'payload' => $payload,
		); }
}

final class InMemoryCanaryPosts implements CanaryPostServiceInterface {
	/** @var array<string,array{post_id:int,article:CanaryArticle}> */ public array $posts = array();
	public int $writes    = 0;
	public int $rollbacks = 0;
	public function create_draft( CanaryArticle $article ): CanaryMutation {
		if ( isset( $this->posts[ $article->article_key() ] ) ) {
			return new CanaryMutation( $this->posts[ $article->article_key() ]['post_id'], false, null ); }
		++$this->writes;
		$post_id                                = 700000 + $this->writes;
		$this->posts[ $article->article_key() ] = array(
			'post_id' => $post_id,
			'article' => $article,
		);
		return new CanaryMutation( $post_id, true, null );
	}
	public function verify_draft( CanaryArticle $article, int $post_id ): array {
		$stored = $this->posts[ $article->article_key() ] ?? null;
		if ( null === $stored || $post_id !== $stored['post_id'] ) {
			throw new \RuntimeException( 'draft missing' ); }
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
		if ( $created ) {
			foreach ( $this->posts as $key => $post ) {
				if ( $post_id === $post['post_id'] ) {
					unset( $this->posts[ $key ] );
					++$this->rollbacks;
					return; }
			}
		}
	}
}

final class InMemoryCanaryAudit implements CanaryAuditLoggerInterface {
	/** @var list<array<string,mixed>> */ public array $records = array();
	public function record( string $event, string $outcome, array $context = array() ): void {
		$this->records[] = array(
			'event'   => $event,
			'outcome' => $outcome,
			'context' => $context,
		); }
	public function encoded(): string {
		$value = json_encode( $this->records );
		return is_string( $value ) ? $value : ''; }
}

final class FakeDiplomaClient implements DiplomaReceiverClientInterface {
	public int $sends                                 = 0;
	public int $rollbacks                             = 0;
	/** @var list<string> */ public array $hosts      = array();
	/** @var array<string,int> */ public array $posts = array();
	public function send( CanaryArticle $article ): array {
		++$this->sends;
		$this->hosts[]                          = 'diplomanext.ataldiploma.com';
		$post_id                                = 800000 + $this->sends;
		$this->posts[ $article->article_key() ] = $post_id;
		return array(
			'status'            => 'accepted',
			'article_key'       => $article->article_key(),
			'post_id'           => $post_id,
			'post_status'       => 'draft',
			'created'           => true,
			'idempotent_replay' => false,
			'verification'      => array(
				'course_key'        => $article->course_key(),
				'title'             => $article->title(),
				'slug'              => $article->slug(),
				'aioseo_payload'    => 'accepted',
				'aioseo_native'     => 'accepted',
				'featured_image_id' => $article->featured_image_id(),
			),
			'recovery'          => array(
				'route' => '/articles/rollback',
				'token' => str_repeat( 'a', 64 ),
			),
		);
	}
	public function rollback( string $article_key, string $recovery_token ): array {
		unset( $recovery_token );
		++$this->rollbacks;
		$post_id = $this->posts[ $article_key ] ?? 0;
		unset( $this->posts[ $article_key ] );
		return array(
			'status'      => 'recovered',
			'article_key' => $article_key,
			'post_id'     => $post_id,
		); }
	public function contacted_hosts(): array {
		return $this->hosts; }
}

final class PassingCanaryRuntime implements CanaryRuntimeGuardInterface {
	public int $institute_checks = 0;
	public int $diploma_checks   = 0;
	public function assert_institute_ready(): void {
		++$this->institute_checks; }
	public function assert_diploma_send_ready(): void {
		++$this->diploma_checks; }
}

final class CanaryTransactions implements TransactionManagerInterface {
	public int $begins    = 0;
	public int $commits   = 0;
	public int $rollbacks = 0;
	public function begin(): void {
		++$this->begins; }
	public function commit(): void {
		++$this->commits; }
	public function rollback(): void {
		++$this->rollbacks; }
}
