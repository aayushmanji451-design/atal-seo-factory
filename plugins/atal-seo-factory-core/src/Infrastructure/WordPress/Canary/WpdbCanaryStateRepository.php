<?php
/** Core articles-table canary state repository. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Infrastructure\WordPress\Canary;

use Atal\SeoFactory\Application\Canary\CanaryException;
use Atal\SeoFactory\Domain\Canary\CanaryArticle;
use Atal\SeoFactory\Domain\Canary\CanaryStateRepositoryInterface;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use wpdb;

final class WpdbCanaryStateRepository implements CanaryStateRepositoryInterface {
	public function __construct( private readonly wpdb $database, private readonly TableNames $tables ) {}

	public function find( string $article_key ): ?array {
		$query = $this->database->prepare( "SELECT status,payload_json FROM {$this->tables->articles()} WHERE article_key = %s LIMIT 1", $article_key );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Repository-owned indexed identity lookup.
		$row = $this->database->get_row( $query, ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$payload = json_decode( is_string( $row['payload_json'] ?? null ) ? $row['payload_json'] : '', true );
		if ( ! is_array( $payload ) || array_is_list( $payload ) || ! is_string( $row['status'] ?? null ) ) {
			throw new CanaryException( 'Stored canary state is malformed.' );
		}
		/** @var array<string,mixed> $payload */
		return array(
			'status'  => $row['status'],
			'payload' => $payload,
		);
	}

	public function save( CanaryArticle $article, string $status, array $payload ): void {
		$encoded = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			throw new CanaryException( 'Unable to encode canary state.' );
		}
		$now      = gmdate( 'Y-m-d H:i:s' );
		$existing = $this->find( $article->article_key() );
		if ( null === $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository-owned canary state insert.
			$result = $this->database->insert(
				$this->tables->articles(),
				array(
					'article_key'  => $article->article_key(),
					'course_key'   => $article->course_key(),
					'target_site'  => $article->target_site(),
					'status'       => $status,
					'payload_json' => $encoded,
					'created_at'   => $now,
					'updated_at'   => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Repository-owned canary state update.
			$result = $this->database->update(
				$this->tables->articles(),
				array(
					'status'       => $status,
					'payload_json' => $encoded,
					'updated_at'   => $now,
				),
				array( 'article_key' => $article->article_key() ),
				array( '%s', '%s', '%s' ),
				array( '%s' )
			);
		}
		if ( false === $result ) {
			throw new CanaryException( 'Unable to persist canary article state.' );
		}
	}
}
