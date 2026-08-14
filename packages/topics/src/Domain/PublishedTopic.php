<?php
/**
 * Registry snapshot.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Domain;

/**
 * Topic values used for duplicate and cannibalization checks.
 */
final class PublishedTopic {

	/**
	 * Create a registry snapshot.
	 *
	 * @param TopicProposal $proposal      Topic proposal.
	 * @param string        $published_url Optional published URL registry value.
	 */
	public function __construct(
		private readonly TopicProposal $proposal,
		private readonly string $published_url = ''
	) {
	}

	/** Return the topic proposal. */
	public function proposal(): TopicProposal {
		return $this->proposal;
	}

	/** Return the optional published URL. */
	public function published_url(): string {
		return $this->published_url;
	}

	/** Return the deterministic snapshot hash. */
	public function source_hash(): string {
		$encoded = wp_json_encode( $this->to_array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
	}

	/**
	 * Export the snapshot.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array_merge( $this->proposal->to_array(), array( 'published_url' => $this->published_url ) );
	}
}
