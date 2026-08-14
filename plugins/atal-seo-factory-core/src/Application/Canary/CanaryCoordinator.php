<?php
/** Bounded Task 04 local/remote canary orchestration. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Application\Canary;

use Atal\Contracts\Value\TargetSite;
use Atal\SeoFactory\Domain\Canary\CanaryArticle;
use Atal\SeoFactory\Domain\Canary\CanaryAuditLoggerInterface;
use Atal\SeoFactory\Domain\Canary\CanaryPostServiceInterface;
use Atal\SeoFactory\Domain\Canary\CanaryRequest;
use Atal\SeoFactory\Domain\Canary\CanaryRuntimeGuardInterface;
use Atal\SeoFactory\Domain\Canary\CanaryStateRepositoryInterface;
use Atal\SeoFactory\Domain\Canary\DiplomaReceiverClientInterface;
use Atal\SeoFactory\Domain\Storage\TransactionManagerInterface;
use Throwable;

final class CanaryCoordinator {
	private const ACTIVE_LOCAL  = 'canary_local_draft_active';
	private const ACTIVE_REMOTE = 'canary_remote_draft_active';
	private const ROLLED_BACK   = 'canary_rolled_back';

	public function __construct(
		private readonly CanaryJsonImporter $importer,
		private readonly CanonicalCanaryArticleBuilder $builder,
		private readonly CanaryContentValidator $validator,
		private readonly CanaryRuntimeGuardInterface $runtime,
		private readonly CanaryPostServiceInterface $posts,
		private readonly CanaryStateRepositoryInterface $states,
		private readonly CanaryAuditLoggerInterface $audit,
		private readonly DiplomaReceiverClientInterface $diploma,
		private readonly TransactionManagerInterface $transactions
	) {}

	/** @return array<string,mixed> */
	public function run_institute( string $json ): array {
		$this->runtime->assert_institute_ready();
		$article = $this->article( $json, TargetSite::INSTITUTE );
		$stored  = $this->states->find( $article->article_key() );
		if ( null !== $stored ) {
			if ( self::ACTIVE_LOCAL === $stored['status'] ) {
				return $this->result( 'PASS', $article, true, $stored['payload'] );
			}
			if ( self::ROLLED_BACK !== $stored['status'] ) {
				throw new CanaryException( 'The deterministic Institute canary is in an unsupported state.' );
			}
		}
		$this->transactions->begin();
		try {
			$mutation = $this->posts->create_draft( $article );
			$verified = $this->posts->verify_draft( $article, $mutation->post_id() );
			$payload  = array(
				'evidence'       => $article->evidence(),
				'post_id'        => $mutation->post_id(),
				'created'        => $mutation->created(),
				'previous_state' => $mutation->previous_state(),
				'verification'   => $verified,
			);
			$this->states->save( $article, self::ACTIVE_LOCAL, $payload );
			$this->audit->record(
				'task04_institute_canary',
				'draft_created',
				array(
					'article_key' => $article->article_key(),
					'post_id'     => $mutation->post_id(),
					'target_site' => $article->target_site(),
				)
			);
			$this->transactions->commit();
			return $this->result( 'PASS', $article, false, $payload );
		} catch ( Throwable $throwable ) {
			$this->transactions->rollback();
			throw $throwable;
		}
	}

	/** @return array<string,mixed> */
	public function run_diploma( string $json ): array {
		$this->runtime->assert_diploma_send_ready();
		$article = $this->article( $json, TargetSite::DIPLOMA );
		$stored  = $this->states->find( $article->article_key() );
		if ( null !== $stored ) {
			if ( self::ACTIVE_REMOTE === $stored['status'] ) {
				return $this->result( 'PASS', $article, true, $this->public_payload( $stored['payload'] ) );
			}
			if ( self::ROLLED_BACK !== $stored['status'] ) {
				throw new CanaryException( 'The deterministic Diploma canary is in an unsupported state.' );
			}
		}
		$response = $this->diploma->send( $article );
		$this->assert_remote_acceptance( $article, $response );
		$recovery     = $this->recovery_token( $response );
		$verification = $this->object( $response['verification'] ?? null, 'Diploma receiver verification' );
		$payload      = array(
			'evidence'            => $article->evidence(),
			'post_id'             => $this->integer( $response['post_id'] ?? null ),
			'post_status'         => $this->string( $response['post_status'] ?? null ),
			'created'             => true === ( $response['created'] ?? false ),
			'remote_verification' => $verification,
			'recovery_token'      => $recovery,
		);
		$this->transactions->begin();
		try {
			$this->states->save( $article, self::ACTIVE_REMOTE, $payload );
			$this->audit->record(
				'task04_diploma_canary',
				'remote_draft_accepted',
				array(
					'article_key' => $article->article_key(),
					'post_id'     => $payload['post_id'],
					'target_site' => $article->target_site(),
				)
			);
			$this->transactions->commit();
		} catch ( Throwable $throwable ) {
			$this->transactions->rollback();
			$this->diploma->rollback( $article->article_key(), $recovery );
			throw $throwable;
		}
		return $this->result( 'PASS', $article, false, $this->public_payload( $payload ) );
	}

	/** @return array<string,mixed> */
	public function verify(): array {
		$checks = array();
		foreach ( array( CanonicalCanaryArticleBuilder::INSTITUTE_ARTICLE_KEY, CanonicalCanaryArticleBuilder::DIPLOMA_ARTICLE_KEY ) as $article_key ) {
			$stored = $this->states->find( $article_key );
			if ( null === $stored ) {
				$checks[ $article_key ] = array(
					'status'  => 'PENDING',
					'message' => 'Canary has not run.',
				);
				continue;
			}
			if ( self::ROLLED_BACK === $stored['status'] ) {
				$checks[ $article_key ] = array(
					'status' => 'PASS',
					'state'  => self::ROLLED_BACK,
				);
				continue;
			}
			$article = $this->article_from_payload( $stored['payload'] );
			if ( TargetSite::INSTITUTE === $article->target_site() ) {
				$post_id                = $this->integer( $stored['payload']['post_id'] ?? null );
				$checks[ $article_key ] = array(
					'status'       => 'PASS',
					'state'        => $stored['status'],
					'verification' => $this->posts->verify_draft( $article, $post_id ),
				);
			} else {
				$remote_ok              = 'draft' === ( $stored['payload']['post_status'] ?? null ) && 0 < $this->integer( $stored['payload']['post_id'] ?? null );
				$checks[ $article_key ] = array(
					'status'           => $remote_ok ? 'PASS' : 'FAIL',
					'state'            => $stored['status'],
					'recorded_outcome' => $this->public_payload( $stored['payload'] ),
				);
			}
		}
		$live_count = count( array_filter( $this->diploma->contacted_hosts(), static fn( string $host ): bool => in_array( $host, array( 'atalinstitute.com', 'ataldiploma.com' ), true ) ) );
		return array(
			'status'                   => in_array( 'FAIL', array_column( $checks, 'status' ), true ) ? 'FAIL' : 'PASS',
			'checks'                   => $checks,
			'live_domain_access_count' => $live_count,
		);
	}

	/** @return array<string,mixed> */
	public function rollback(): array {
		$results              = array();
		$results['institute'] = $this->rollback_institute();
		$results['diploma']   = $this->rollback_diploma();
		return array(
			'status'                   => 'PASS',
			'results'                  => $results,
			'live_domain_access_count' => 0,
		);
	}

	/** @return array<string,mixed> */
	private function rollback_institute(): array {
		$key    = CanonicalCanaryArticleBuilder::INSTITUTE_ARTICLE_KEY;
		$stored = $this->states->find( $key );
		if ( null === $stored || self::ROLLED_BACK === $stored['status'] ) {
			return array(
				'status'      => 'unchanged',
				'article_key' => $key,
			);
		}
		$article  = $this->article_from_payload( $stored['payload'] );
		$post_id  = $this->integer( $stored['payload']['post_id'] ?? null );
		$created  = true === ( $stored['payload']['created'] ?? false );
		$previous = $this->optional_object( $stored['payload']['previous_state'] ?? null, 'Institute previous state' );
		$this->transactions->begin();
		try {
			$this->posts->rollback( $post_id, $created, $previous );
			$this->states->save(
				$article,
				self::ROLLED_BACK,
				array(
					'evidence' => $article->evidence(),
					'post_id'  => $post_id,
					'rollback' => 'local_recovered',
				)
			);
			$this->audit->record(
				'task04_institute_canary',
				'rolled_back',
				array(
					'article_key' => $key,
					'post_id'     => $post_id,
					'target_site' => TargetSite::INSTITUTE,
				)
			);
			$this->transactions->commit();
		} catch ( Throwable $throwable ) {
			$this->transactions->rollback();
			throw $throwable;
		}
		return array(
			'status'      => 'recovered',
			'article_key' => $key,
			'post_id'     => $post_id,
		);
	}

	/** @return array<string,mixed> */
	private function rollback_diploma(): array {
		$key    = CanonicalCanaryArticleBuilder::DIPLOMA_ARTICLE_KEY;
		$stored = $this->states->find( $key );
		if ( null === $stored || self::ROLLED_BACK === $stored['status'] ) {
			return array(
				'status'      => 'unchanged',
				'article_key' => $key,
			);
		}
		$token = $this->string( $stored['payload']['recovery_token'] ?? null );
		if ( '' === $token ) {
			throw new CanaryException( 'The stored Diploma outcome has no recovery token.' );
		}
		$response = $this->diploma->rollback( $key, $token );
		if ( 'recovered' !== ( $response['status'] ?? null ) || ( $response['article_key'] ?? null ) !== $key ) {
			throw new CanaryException( 'The Diploma receiver did not confirm bounded recovery.' );
		}
		$article = $this->article_from_payload( $stored['payload'] );
		$this->states->save(
			$article,
			self::ROLLED_BACK,
			array(
				'evidence' => $article->evidence(),
				'post_id'  => $stored['payload']['post_id'] ?? 0,
				'rollback' => 'remote_recovered',
			)
		);
		$this->audit->record(
			'task04_diploma_canary',
			'rolled_back',
			array(
				'article_key' => $key,
				'post_id'     => $this->integer( $stored['payload']['post_id'] ?? null ),
				'target_site' => TargetSite::DIPLOMA,
			)
		);
		return array(
			'status'      => 'recovered',
			'article_key' => $key,
			'post_id'     => $stored['payload']['post_id'] ?? 0,
		);
	}

	private function article( string $json, string $site ): CanaryArticle {
		$article = $this->builder->build( $this->importer->import( $json, $site ) );
		$this->validator->validate( $article );
		return $article;
	}

	/** @param array<string,mixed> $payload */
	private function article_from_payload( array $payload ): CanaryArticle {
		$evidence = $payload['evidence'] ?? null;
		if ( ! is_array( $evidence ) ) {
			throw new CanaryException( 'Stored canary evidence is incomplete.' );
		}
		$site    = $this->string( $evidence['target_site'] ?? null );
		$request = new CanaryRequest(
			$site,
			$this->string( $evidence['course_key'] ?? null ),
			$this->string( $evidence['intent_key'] ?? null ),
			is_string( $evidence['option_key'] ?? null ) ? $evidence['option_key'] : null,
			$this->integer( $evidence['featured_image_id'] ?? null )
		);
		$article = $this->builder->build( $request );
		$this->validator->validate( $article );
		return $article;
	}

	/** @param array<string,mixed> $response */
	private function assert_remote_acceptance( CanaryArticle $article, array $response ): void {
		if ( 'accepted' !== ( $response['status'] ?? null ) || 'draft' !== ( $response['post_status'] ?? null ) || $article->article_key() !== ( $response['article_key'] ?? null ) || 1 > $this->integer( $response['post_id'] ?? null ) ) {
			throw new CanaryException( 'The Diploma receiver did not return the exact accepted-draft contract.' );
		}
		$verification = $this->object( $response['verification'] ?? null, 'Diploma receiver verification' );
		if ( $article->course_key() !== ( $verification['course_key'] ?? null ) || $article->title() !== ( $verification['title'] ?? null ) || $article->slug() !== ( $verification['slug'] ?? null ) || 'accepted' !== ( $verification['aioseo_payload'] ?? null ) || 'accepted' !== ( $verification['aioseo_native'] ?? null ) || $article->featured_image_id() !== ( $verification['featured_image_id'] ?? null ) ) {
			throw new CanaryException( 'The Diploma receiver did not verify the exact canary content, AIOSEO payload, and image.' );
		}
	}

	/** @param array<string,mixed> $response */
	private function recovery_token( array $response ): string {
		$recovery = $response['recovery'] ?? null;
		$token    = is_array( $recovery ) ? ( $recovery['token'] ?? null ) : null;
		if ( ! is_string( $token ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			throw new CanaryException( 'The Diploma receiver recovery contract is missing.' );
		}
		return $token;
	}

	/**
	 * @param array<string,mixed> $payload Stored payload.
	 *
	 * @return array<string,mixed>
	 */
	private function public_payload( array $payload ): array {
		unset( $payload['recovery_token'], $payload['previous_state'] );
		return $payload;
	}

	/**
	 * @param array<string,mixed> $payload Stored payload.
	 *
	 * @return array<string,mixed>
	 */
	private function result( string $status, CanaryArticle $article, bool $idempotent, array $payload ): array {
		return array(
			'status'                   => $status,
			'article_key'              => $article->article_key(),
			'course_key'               => $article->course_key(),
			'target_site'              => $article->target_site(),
			'idempotent'               => $idempotent,
			'payload'                  => $this->public_payload( $payload ),
			'live_domain_access_count' => 0,
		);
	}

	private function string( mixed $value ): string {
		return is_string( $value ) ? $value : ''; }
	private function integer( mixed $value ): int {
		return is_numeric( $value ) ? (int) $value : 0; }

	/** @return array<string,mixed> */
	private function object( mixed $value, string $label ): array {
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			throw new CanaryException( $label . ' must be a JSON object.' );
		}
		$result = array();
		foreach ( $value as $key => $item ) {
			if ( ! is_string( $key ) ) {
				throw new CanaryException( $label . ' contains a non-string key.' );
			}
			$result[ $key ] = $item;
		}
		return $result;
	}

	/** @return array<string,mixed>|null */
	private function optional_object( mixed $value, string $label ): ?array {
		return null === $value ? null : $this->object( $value, $label );
	}
}
