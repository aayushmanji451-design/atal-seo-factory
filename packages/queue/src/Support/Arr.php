<?php
/**
 * Typed array accessors.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Support;

/**
 * Narrow, defensive accessors used when hydrating persisted rows.
 */
final class Arr {

	/**
	 * Read a string value.
	 *
	 * @param array<mixed,mixed> $data     Source row.
	 * @param string             $key      Key.
	 * @param string             $fallback Fallback.
	 */
	public static function string( array $data, string $key, string $fallback = '' ): string {
		$value = $data[ $key ] ?? null;

		return is_string( $value ) ? $value : ( is_int( $value ) || is_float( $value ) ? (string) $value : $fallback );
	}

	/**
	 * Read an integer value.
	 *
	 * @param array<mixed,mixed> $data     Source row.
	 * @param string             $key      Key.
	 * @param int                $fallback Fallback.
	 */
	public static function int( array $data, string $key, int $fallback = 0 ): int {
		$value = $data[ $key ] ?? null;

		return is_int( $value ) ? $value : ( is_numeric( $value ) ? (int) $value : $fallback );
	}

	/**
	 * Read a boolean value.
	 *
	 * @param array<mixed,mixed> $data     Source row.
	 * @param string             $key      Key.
	 * @param bool               $fallback Fallback.
	 */
	public static function bool( array $data, string $key, bool $fallback = false ): bool {
		$value = $data[ $key ] ?? null;
		if ( is_bool( $value ) ) {
			return $value;
		}

		return is_numeric( $value ) ? 0 !== (int) $value : $fallback;
	}

	/**
	 * Read an array value, decoding JSON strings.
	 *
	 * @param array<mixed,mixed> $data Source row.
	 * @param string             $key  Key.
	 *
	 * @return array<string,mixed>
	 */
	public static function map( array $data, string $key ): array {
		$value = $data[ $key ] ?? null;
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : null;
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$output = array();
		foreach ( $value as $item_key => $item ) {
			$output[ (string) $item_key ] = $item;
		}

		return $output;
	}

	/**
	 * Encode a value as stable JSON for persistence.
	 *
	 * @param array<mixed,mixed> $value Value.
	 */
	public static function json( array $value ): string {
		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( is_string( $encoded ) ) {
				return $encoded;
			}
		}

		return (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Redact secrets and bound a diagnostic message.
	 *
	 * @param string $message Raw message.
	 * @param int    $limit   Maximum characters.
	 */
	public static function safe_message( string $message, int $limit = 500 ): string {
		$redacted = preg_replace( '/(?:password|secret|token|authorization|api[ _-]?key|stream[ _-]?key|signature)\s*[:=]\s*\S+/i', '[redacted]', $message );
		$text     = is_string( $redacted ) ? $redacted : '';

		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
	}
}
