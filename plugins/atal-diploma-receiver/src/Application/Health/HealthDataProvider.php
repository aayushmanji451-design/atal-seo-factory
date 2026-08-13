<?php
/** Safe read-only receiver health. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Health;

use Atal\DiplomaReceiver\Application\Migration\ReceiverStateStoreInterface;
use Atal\DiplomaReceiver\Application\Migration\SchemaDatabaseInterface;
use Atal\DiplomaReceiver\Config\Identifiers;
use Atal\DiplomaReceiver\Domain\Receiver\AioseoAdapterInterface;
use Atal\DiplomaReceiver\Infrastructure\Database\TableNames;
final class HealthDataProvider {
	public function __construct( private readonly SchemaDatabaseInterface $database, private readonly ReceiverStateStoreInterface $state, private readonly TableNames $tables, private readonly AioseoAdapterInterface $aioseo ) {}
	/** @return array<string,mixed> */
	public function snapshot(): array {
		$home   = home_url( '/' );
		$host   = wp_parse_url( $home, PHP_URL_HOST );
		$active = get_option( 'active_plugins', array() );
		$old    = array();
		if ( is_array( $active ) ) {
			foreach ( $active as $plugin ) {
				if ( is_string( $plugin ) && str_contains( strtolower( $plugin ), 'atal-seo-connector' ) ) {
					$old[] = $plugin; }
			}
		}
		$table_status = array();
		foreach ( $this->tables->all() as $table ) {
			$table_status[ $table ] = $this->database->exists( $table ); }
		$secret      = get_option( Identifiers::OPTION_HMAC_SECRET, '' );
		$blog_public = get_option( 'blog_public', '1' );
		$wp_memory   = defined( 'WP_MEMORY_LIMIT' ) ? constant( 'WP_MEMORY_LIMIT' ) : '';
		$php_memory  = ini_get( 'memory_limit' );
		return array(
			'plugin_version'            => '0.3.0-dev',
			'development_build'         => true,
			'plugin_slug'               => Identifiers::PLUGIN_SLUG,
			'rest_namespace'            => Identifiers::REST_NAMESPACE,
			'site_url'                  => $home,
			'hostname'                  => is_string( $host ) ? $host : '',
			'expected_hostname'         => Identifiers::TARGET_HOST,
			'hostname_valid'            => Identifiers::TARGET_HOST === $host,
			'search_indexing_disabled'  => in_array( $blog_public, array( 0, '0' ), true ),
			'aioseo_detected'           => $this->aioseo->detected(),
			'aioseo_version'            => $this->aioseo->version(),
			'old_atal_connector_active' => array() !== $old,
			'database_version'          => $this->state->database_version(),
			'expected_database_version' => Identifiers::DATABASE_VERSION,
			'tables'                    => $table_status,
			'hmac_configured'           => is_string( $secret ) && 32 <= strlen( $secret ),
			'wp_memory_limit'           => $this->string_value( $wp_memory ),
			'php_memory_limit'          => $php_memory,
			'read_only'                 => true,
		);
	}
	private function string_value( mixed $value ): string {
		return is_string( $value ) || is_int( $value ) || is_float( $value ) ? (string) $value : ''; }
}
