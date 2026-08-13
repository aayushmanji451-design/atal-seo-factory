<?php
/** AIOSEO environment adapter. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\DiplomaReceiver\Domain\Receiver\AioseoAdapterInterface;
final class AioseoEnvironmentAdapter implements AioseoAdapterInterface {
	public function detected(): bool {
		return function_exists( 'aioseo' ) || defined( 'AIOSEO_VERSION' ) || ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) ); }
	public function version(): ?string {
		$value = defined( 'AIOSEO_VERSION' ) ? constant( 'AIOSEO_VERSION' ) : null;
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		} if ( defined( 'WP_PLUGIN_DIR' ) && function_exists( 'get_plugin_data' ) ) {
			$root = constant( 'WP_PLUGIN_DIR' );
			if ( is_string( $root ) ) {
				$data    = get_plugin_data( $root . '/all-in-one-seo-pack/all_in_one_seo_pack.php', false, false );
				$version = $data['Version'] ?? null;
				return is_string( $version ) && '' !== $version ? $version : null;
			}
		} return null; }
}
