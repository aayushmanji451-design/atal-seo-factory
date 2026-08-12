<?php
/**
 * Runtime JSON value narrowing helpers.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Data;

use RuntimeException;

/**
 * Converts schema-validated mixed JSON values into explicit PHP types.
 */
final class JsonValue {

	/**
	 * Read an object field as a string.
	 *
	 * @param array<string,mixed> $data JSON object.
	 * @param string              $key    Field name.
	 */
	public static function string_field( array $data, string $key ): string {
		$value = $data[ $key ] ?? null;
		if ( ! is_string( $value ) ) {
			throw new RuntimeException( 'Expected string field: ' . $key );
		}

		return $value;
	}

	/**
	 * Read an object field as an integer.
	 *
	 * @param array<string,mixed> $data JSON object.
	 * @param string              $key    Field name.
	 */
	public static function integer_field( array $data, string $key ): int {
		$value = $data[ $key ] ?? null;
		if ( ! is_int( $value ) ) {
			throw new RuntimeException( 'Expected integer field: ' . $key );
		}

		return $value;
	}

	/**
	 * Read an object field as a boolean.
	 *
	 * @param array<string,mixed> $data JSON object.
	 * @param string              $key    Field name.
	 */
	public static function boolean_field( array $data, string $key ): bool {
		$value = $data[ $key ] ?? null;
		if ( ! is_bool( $value ) ) {
			throw new RuntimeException( 'Expected boolean field: ' . $key );
		}

		return $value;
	}

	/**
	 * Read an object field as a JSON object.
	 *
	 * @param array<string,mixed> $data JSON object.
	 * @param string              $key    Field name.
	 *
	 * @return array<string,mixed>
	 */
	public static function object_field( array $data, string $key ): array {
		$value = $data[ $key ] ?? null;

		return self::object( $value, $key );
	}

	/**
	 * Narrow a mixed value to a JSON object with string keys.
	 *
	 * @param mixed  $value Mixed JSON value.
	 * @param string $label Diagnostic label.
	 *
	 * @return array<string,mixed>
	 */
	public static function object( mixed $value, string $label ): array {
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			throw new RuntimeException( 'Expected object value: ' . $label );
		}

		$result = array();
		foreach ( $value as $key => $item ) {
			if ( ! is_string( $key ) ) {
				throw new RuntimeException( 'Expected string object key in: ' . $label );
			}
			$result[ $key ] = $item;
		}

		return $result;
	}

	/**
	 * Read an object field as a list of JSON objects.
	 *
	 * @param array<string,mixed> $data JSON object.
	 * @param string              $key    Field name.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function object_list_field( array $data, string $key ): array {
		$value = $data[ $key ] ?? null;
		if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
			throw new RuntimeException( 'Expected object-list field: ' . $key );
		}

		$result = array();
		foreach ( $value as $item ) {
			$result[] = self::object( $item, $key . ' item' );
		}

		return $result;
	}

	/**
	 * Read an object field as a list of strings.
	 *
	 * @param array<string,mixed> $data JSON object.
	 * @param string              $key    Field name.
	 *
	 * @return list<string>
	 */
	public static function string_list_field( array $data, string $key ): array {
		$value = $data[ $key ] ?? null;
		if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
			throw new RuntimeException( 'Expected string-list field: ' . $key );
		}

		$result = array();
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) ) {
				throw new RuntimeException( 'Expected every item in ' . $key . ' to be a string.' );
			}
			$result[] = $item;
		}

		return $result;
	}

	/**
	 * Read an optional object-list field.
	 *
	 * @param array<string,mixed> $data JSON object.
	 * @param string              $key    Field name.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function optional_object_list_field( array $data, string $key ): array {
		if ( ! array_key_exists( $key, $data ) ) {
			return array();
		}

		return self::object_list_field( $data, $key );
	}
}
