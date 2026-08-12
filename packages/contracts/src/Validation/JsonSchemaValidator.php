<?php
/**
 * Small JSON Schema validator for repository-owned contracts.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

use Atal\Contracts\Data\JsonValue;
use DateTimeImmutable;

/**
 * Validates the JSON Schema keywords used by the Phase 1 contracts.
 */
final class JsonSchemaValidator {

	/**
	 * Validate decoded JSON data against a decoded schema.
	 *
	 * @param mixed               $data   Decoded JSON value.
	 * @param array<string,mixed> $schema Decoded JSON Schema.
	 * @param string              $path   Initial path.
	 *
	 * @return list<ValidationIssue>
	 */
	public function validate( mixed $data, array $schema, string $path = '$' ): array {
		$issues = array();
		$this->validate_node( $data, $schema, $path, $issues );

		return array_values( $issues );
	}

	/**
	 * Validate one node recursively.
	 *
	 * @param mixed                      $data   Decoded value.
	 * @param array<string,mixed>        $schema Schema node.
	 * @param string                     $path   Current path.
	 * @param array<int,ValidationIssue> $issues Collected issues.
	 */
	private function validate_node( mixed $data, array $schema, string $path, array &$issues ): void {
		$types = $this->schema_types( $schema['type'] ?? null );

		if ( array() !== $types && ! $this->matches_any_type( $data, $types ) ) {
			$issues[] = new ValidationIssue(
				'schema_type_mismatch',
				'Expected ' . implode( '|', $types ) . ', received ' . get_debug_type( $data ) . '.',
				$path
			);
			return;
		}

		if ( array_key_exists( 'const', $schema ) && $data !== $schema['const'] ) {
			$issues[] = new ValidationIssue( 'schema_const_mismatch', 'Value does not match the locked constant.', $path );
		}

		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && ! in_array( $data, $schema['enum'], true ) ) {
			$issues[] = new ValidationIssue( 'schema_enum_mismatch', 'Value is not in the allowed set.', $path );
		}

		if ( is_string( $data ) ) {
			$this->validate_string( $data, $schema, $path, $issues );
		}

		if ( is_int( $data ) || is_float( $data ) ) {
			$this->validate_number( $data, $schema, $path, $issues );
		}

		if ( is_array( $data ) && array_is_list( $data ) ) {
			$this->validate_array( $data, $schema, $path, $issues );
		}

		if ( is_array( $data ) && ! array_is_list( $data ) ) {
			$this->validate_object( JsonValue::object( $data, $path ), $schema, $path, $issues );
		}
	}

	/**
	 * Normalize a schema type declaration.
	 *
	 * @param mixed $type Type declaration.
	 *
	 * @return list<string>
	 */
	private function schema_types( mixed $type ): array {
		if ( is_string( $type ) ) {
			return array( $type );
		}

		if ( ! is_array( $type ) ) {
			return array();
		}

		return array_values( array_filter( $type, 'is_string' ) );
	}

	/**
	 * Check whether data matches one declared type.
	 *
	 * @param mixed             $data  Decoded value.
	 * @param array<int,string> $types Allowed types.
	 */
	private function matches_any_type( mixed $data, array $types ): bool {
		foreach ( $types as $type ) {
			$matches = match ( $type ) {
				'object'  => is_array( $data ) && ! array_is_list( $data ),
				'array'   => is_array( $data ) && array_is_list( $data ),
				'string'  => is_string( $data ),
				'integer' => is_int( $data ),
				'number'  => is_int( $data ) || is_float( $data ),
				'boolean' => is_bool( $data ),
				'null'    => null === $data,
				default   => false,
			};

			if ( $matches ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate string keywords.
	 *
	 * @param string                     $data   String value.
	 * @param array<string,mixed>        $schema Schema node.
	 * @param string                     $path   Current path.
	 * @param array<int,ValidationIssue> $issues Collected issues.
	 */
	private function validate_string( string $data, array $schema, string $path, array &$issues ): void {
		if ( isset( $schema['minLength'] ) && is_int( $schema['minLength'] ) && strlen( $data ) < $schema['minLength'] ) {
			$issues[] = new ValidationIssue( 'schema_min_length', 'String is shorter than the required minimum.', $path );
		}

		if ( isset( $schema['pattern'] ) && is_string( $schema['pattern'] ) ) {
			$pattern = '~' . str_replace( '~', '\\~', $schema['pattern'] ) . '~u';
			if ( 1 !== preg_match( $pattern, $data ) ) {
				$issues[] = new ValidationIssue( 'schema_pattern_mismatch', 'String does not match the required pattern.', $path );
			}
		}

		if ( isset( $schema['format'] ) && is_string( $schema['format'] ) && ! $this->matches_format( $data, $schema['format'] ) ) {
			$issues[] = new ValidationIssue( 'schema_format_mismatch', 'String does not match format ' . $schema['format'] . '.', $path );
		}
	}

	/**
	 * Validate numeric keywords.
	 *
	 * @param int|float                  $data   Numeric value.
	 * @param array<string,mixed>        $schema Schema node.
	 * @param string                     $path   Current path.
	 * @param array<int,ValidationIssue> $issues Collected issues.
	 */
	private function validate_number( int|float $data, array $schema, string $path, array &$issues ): void {
		if ( isset( $schema['minimum'] ) && ( is_int( $schema['minimum'] ) || is_float( $schema['minimum'] ) ) && $data < $schema['minimum'] ) {
			$issues[] = new ValidationIssue( 'schema_minimum', 'Number is below the allowed minimum.', $path );
		}
	}

	/**
	 * Validate array keywords and items.
	 *
	 * @param array<int,mixed>           $data   Array value.
	 * @param array<string,mixed>        $schema Schema node.
	 * @param string                     $path   Current path.
	 * @param array<int,ValidationIssue> $issues Collected issues.
	 */
	private function validate_array( array $data, array $schema, string $path, array &$issues ): void {
		if ( isset( $schema['minItems'] ) && is_int( $schema['minItems'] ) && count( $data ) < $schema['minItems'] ) {
			$issues[] = new ValidationIssue( 'schema_min_items', 'Array has fewer items than required.', $path );
		}

		if ( isset( $schema['maxItems'] ) && is_int( $schema['maxItems'] ) && count( $data ) > $schema['maxItems'] ) {
			$issues[] = new ValidationIssue( 'schema_max_items', 'Array has more items than allowed.', $path );
		}

		if ( true === ( $schema['uniqueItems'] ?? false ) ) {
			$encoded = array_map( static fn ( mixed $item ): string => json_encode( $item, JSON_THROW_ON_ERROR ), $data );
			if ( count( $encoded ) !== count( array_unique( $encoded ) ) ) {
				$issues[] = new ValidationIssue( 'schema_unique_items', 'Array contains duplicate items.', $path );
			}
		}

		if ( ! isset( $schema['items'] ) || ! is_array( $schema['items'] ) || array_is_list( $schema['items'] ) ) {
			return;
		}

		$item_schema = JsonValue::object( $schema['items'], $path . '.items' );
		foreach ( $data as $index => $item ) {
			$this->validate_node( $item, $item_schema, $path . '[' . $index . ']', $issues );
		}
	}

	/**
	 * Validate object keywords and properties.
	 *
	 * @param array<string,mixed>        $data   Object value.
	 * @param array<string,mixed>        $schema Schema node.
	 * @param string                     $path   Current path.
	 * @param array<int,ValidationIssue> $issues Collected issues.
	 */
	private function validate_object( array $data, array $schema, string $path, array &$issues ): void {
		$required = $this->schema_types( $schema['required'] ?? null );

		foreach ( $required as $property ) {
			if ( ! array_key_exists( $property, $data ) ) {
				$issues[] = new ValidationIssue( 'schema_required_property', 'Required property is missing.', $path . '.' . $property );
			}
		}

		$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] ) && ! array_is_list( $schema['properties'] )
			? JsonValue::object( $schema['properties'], $path . '.properties' )
			: array();

		foreach ( $properties as $property => $property_schema ) {
			if ( is_array( $property_schema ) && ! array_is_list( $property_schema ) && array_key_exists( $property, $data ) ) {
				$this->validate_node( $data[ $property ], JsonValue::object( $property_schema, $path . '.' . $property ), $path . '.' . $property, $issues );
			}
		}

		if ( false !== ( $schema['additionalProperties'] ?? true ) ) {
			return;
		}

		foreach ( array_keys( $data ) as $property ) {
			if ( ! array_key_exists( $property, $properties ) ) {
				$issues[] = new ValidationIssue( 'schema_additional_property', 'Additional property is not allowed.', $path . '.' . $property );
			}
		}
	}

	/**
	 * Validate supported string formats.
	 *
	 * @param string $value  String value.
	 * @param string $format Format name.
	 */
	private function matches_format( string $value, string $format ): bool {
		if ( 'uri' === $format ) {
			return false !== filter_var( $value, FILTER_VALIDATE_URL );
		}

		if ( 'date' === $format ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

			return false !== $date && $date->format( 'Y-m-d' ) === $value;
		}

		return true;
	}
}
