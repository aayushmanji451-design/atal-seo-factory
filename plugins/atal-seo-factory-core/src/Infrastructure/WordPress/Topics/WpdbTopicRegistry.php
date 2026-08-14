<?php
/**
 * WordPress topic registry.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress\Topics;

use Atal\Topics\Contract\TopicRegistryInterface;
use Atal\Topics\Domain\PublishedTopic;
use Atal\Topics\Domain\TopicProposal;
use JsonException;
use RuntimeException;
use wpdb;

/**
 * Stores Task 06 data in the existing Core-owned topics table.
 */
final class WpdbTopicRegistry implements TopicRegistryInterface {

	public function __construct( private readonly wpdb $database, private readonly string $table_name ) {
	}

	public function all(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal Core table name; bounded registry read.
		$rows   = $this->database->get_results( "SELECT payload_json FROM {$this->table_name} ORDER BY id ASC", ARRAY_A );
		$output = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$payload = $row['payload_json'] ?? null;
			if ( ! is_string( $payload ) ) {
				continue;
			}
			try {
				$data = json_decode( $payload, true, 128, JSON_THROW_ON_ERROR );
			} catch ( JsonException ) {
				continue;
			}
			if ( ! is_array( $data ) || ! isset( $data['primary_keyword'], $data['intent'], $data['year'], $data['slug'] ) ) {
				continue;
			}
			$output[] = new PublishedTopic( TopicProposal::from_array( $data ), is_string( $data['published_url'] ?? null ) ? $data['published_url'] : '' );
		}

		return $output;
	}

	public function save( PublishedTopic $topic ): void {
		$proposal = $topic->proposal();
		$key      = $proposal->identity()->key();
		$now      = gmdate( 'Y-m-d H:i:s' );
		$encoded  = wp_json_encode( $topic->to_array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) {
			throw new RuntimeException( 'Unable to encode the topic registry payload.' );
		}

		$query = $this->database->prepare( "SELECT id FROM {$this->table_name} WHERE topic_key = %s LIMIT 1", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal Core table name.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deterministic upsert identity read.
		$id      = $this->database->get_var( $query );
		$data    = array(
			'course_key'       => $proposal->identity()->course_key(),
			'target_site'      => $proposal->identity()->target_site(),
			'title'            => $proposal->title(),
			'payload_json'     => $encoded,
			'source_hash'      => $topic->source_hash(),
			'contract_version' => 'task-06-v1',
			'updated_at'       => $now,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
		if ( null === $id ) {
			$data['topic_key']  = $key;
			$data['created_at'] = $now;
			$formats[]          = '%s';
			$formats[]          = '%s';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Core-owned table insert.
			$result = $this->database->insert( $this->table_name, $data, $formats );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Core-owned table update.
			$result = $this->database->update( $this->table_name, $data, array( 'topic_key' => $key ), $formats, array( '%s' ) );
		}

		if ( false === $result ) {
			throw new RuntimeException( 'Unable to persist the deterministic topic.' );
		}
	}

	public function begin(): void {
		$this->transaction( 'START TRANSACTION' );
	}

	public function commit(): void {
		$this->transaction( 'COMMIT' );
	}

	public function rollback(): void {
		$this->transaction( 'ROLLBACK' );
	}

	private function transaction( string $statement ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Fixed transaction statements.
		if ( false === $this->database->query( $statement ) ) {
			throw new RuntimeException( 'Unable to complete the topic registry transaction.' );
		}
	}
}
