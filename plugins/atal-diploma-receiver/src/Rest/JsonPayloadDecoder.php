<?php
/** Strict JSON object decoder. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Rest;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use JsonException;
final class JsonPayloadDecoder {
	/** @return array<string,mixed> */
	public function decode( string $body ): array {
		try {
			$decoded = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			throw new ReceiverException( 'receiver_malformed_json', 'The request body is not valid JSON.', 400 );
		} if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			throw new ReceiverException( 'receiver_malformed_payload', 'The request body must be a JSON object.', 400 );
		} $result = array();
		foreach ( $decoded as $key => $value ) {
			if ( ! is_string( $key ) ) {
				throw new ReceiverException( 'receiver_malformed_payload', 'The request body must use object keys.', 400 );
			} $result[ $key ] = $value;
		} return $result; }
}
