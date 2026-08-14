<?php
/**
 * Local deterministic text similarity.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Application;

/**
 * Uses token-set similarity; it never calls an AI or remote service.
 */
final class Similarity {

	/**
	 * Compare two strings using Jaccard token similarity.
	 *
	 * @param string $left  First string.
	 * @param string $right Second string.
	 */
	public function score( string $left, string $right ): float {
		$left_tokens  = $this->tokens( $left );
		$right_tokens = $this->tokens( $right );
		if ( array() === $left_tokens || array() === $right_tokens ) {
			return 0.0;
		}

		$intersection = array_intersect_key( $left_tokens, $right_tokens );
		$union        = $left_tokens + $right_tokens;

		return count( $intersection ) / count( $union );
	}

	/**
	 * Build a normalized token set.
	 *
	 * @param string $value Raw content.
	 *
	 * @return array<string,true>
	 */
	private function tokens( string $value ): array {
		$normalized = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', strtolower( $value ) );
		$parts      = preg_split( '/\s+/u', trim( is_string( $normalized ) ? $normalized : '' ) );
		$tokens     = array();
		foreach ( is_array( $parts ) ? $parts : array() as $part ) {
			if ( strlen( $part ) >= 3 ) {
				$tokens[ $part ] = true;
			}
		}

		return $tokens;
	}
}
