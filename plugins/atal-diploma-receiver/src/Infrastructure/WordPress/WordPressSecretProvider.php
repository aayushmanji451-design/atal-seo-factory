<?php
/** Receiver secret lookup without disclosure. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\DiplomaReceiver\Config\Identifiers;
use Atal\DiplomaReceiver\Domain\Security\SecretProviderInterface;
final class WordPressSecretProvider implements SecretProviderInterface {
	public function secret(): string {
		$constant = defined( 'ATAL_DIPLOMA_RECEIVER_HMAC_SECRET' ) ? constant( 'ATAL_DIPLOMA_RECEIVER_HMAC_SECRET' ) : null;
		if ( is_string( $constant ) && '' !== $constant ) {
			return $constant;
		} $value = get_option( Identifiers::OPTION_HMAC_SECRET, '' );
		return is_string( $value ) ? $value : ''; }
}
