<?php
/** Receiver option storage. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\DiplomaReceiver\Application\Migration\ReceiverStateStoreInterface;
use Atal\DiplomaReceiver\Config\Identifiers;
final class WordPressStateStore implements ReceiverStateStoreInterface {
	public function database_version(): int {
		$value = get_option( Identifiers::OPTION_DATABASE_VERSION, 0 );
		return is_numeric( $value ) ? (int) $value : 0; }
	public function set_database_version( int $version ): void {
		update_option( Identifiers::OPTION_DATABASE_VERSION, $version, false ); }
	public function record_plugin_version( string $version ): void {
		update_option( Identifiers::OPTION_PLUGIN_VERSION, $version, false ); }
	public function ensure_secret(): void {
		$existing = get_option( Identifiers::OPTION_HMAC_SECRET, '' );
		if ( ! is_string( $existing ) || 32 > strlen( $existing ) ) {
			update_option( Identifiers::OPTION_HMAC_SECRET, bin2hex( random_bytes( 32 ) ), false ); } }
}
