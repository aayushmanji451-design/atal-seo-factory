<?php
/**
 * Read-only staging health data.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Health;

use Atal\SeoFactory\Config\Identifiers;
use Atal\SeoFactory\Domain\Storage\CoreStateStoreInterface;
use Atal\SeoFactory\Domain\Storage\SchemaDatabaseInterface;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use Atal\SeoFactory\Plugin;

/**
 * Collects bounded health values and performs no writes.
 */
final class HealthDataProvider {

	private const RECOMMENDED_WORDPRESS_MEMORY_BYTES = 268435456;

	private const MINIMUM_OPERATION_HEADROOM_BYTES = 16777216;

	/**
	 * Create the bounded health-data provider.
	 *
	 * @param SchemaDatabaseInterface     $database    Schema gateway.
	 * @param CoreStateStoreInterface     $state_store Core state store.
	 * @param TableNames                  $tables      Core table names.
	 * @param RuntimeEnvironmentInterface $environment Runtime environment reader.
	 */
	public function __construct(
		private readonly SchemaDatabaseInterface $database,
		private readonly CoreStateStoreInterface $state_store,
		private readonly TableNames $tables,
		private readonly RuntimeEnvironmentInterface $environment
	) {
	}

	/**
	 * Collect the current read-only health snapshot.
	 *
	 * @return array{plugin_version:string,plugin_slug:string,rest_namespace:string,database_version:int,expected_database_version:int,knowledge_fingerprint:?string,site_url:string,environment_type:string,wordpress_version:string,php_version:string,memory:array{status:string,wordpress_memory_limit:string,wordpress_max_memory_limit:string,php_memory_limit:string,current_usage_bytes:int,peak_usage_bytes:int,wordpress_admin_can_raise:bool,actual_limit_bytes:int,actual_available_bytes:int},tables:array<string,array{name:string,exists:bool}>,post_reactivation_persistence:string,read_only:bool}
	 */
	public function snapshot(): array {
		$table_status = array();
		foreach ( $this->tables->keyed() as $key => $table_name ) {
			$table_status[ $key ] = array(
				'name'   => $table_name,
				'exists' => $this->database->table_exists( $table_name ),
			);
		}

		$memory           = $this->memory_snapshot();
		$all_tables_exist = ! in_array( false, array_column( $table_status, 'exists' ), true );
		$fingerprint      = $this->state_store->knowledge_fingerprint();

		return array(
			'plugin_version'                => Plugin::VERSION,
			'plugin_slug'                   => Identifiers::PLUGIN_SLUG,
			'rest_namespace'                => Identifiers::REST_NAMESPACE,
			'database_version'              => $this->state_store->database_version(),
			'expected_database_version'     => Identifiers::DATABASE_VERSION,
			'knowledge_fingerprint'         => $fingerprint,
			'site_url'                      => $this->environment->site_url(),
			'environment_type'              => $this->environment->environment_type(),
			'wordpress_version'             => $this->environment->wordpress_version(),
			'php_version'                   => $this->environment->php_version(),
			'memory'                        => $memory,
			'tables'                        => $table_status,
			'post_reactivation_persistence' => $all_tables_exist && null !== $fingerprint && $this->state_store->post_reactivation_persistence_verified() ? 'PASS' : 'NOT YET VERIFIED',
			'read_only'                     => true,
		);
	}

	/**
	 * Collect actual request memory values without changing configuration.
	 *
	 * @return array{status:string,wordpress_memory_limit:string,wordpress_max_memory_limit:string,php_memory_limit:string,current_usage_bytes:int,peak_usage_bytes:int,wordpress_admin_can_raise:bool,actual_limit_bytes:int,actual_available_bytes:int}
	 */
	private function memory_snapshot(): array {
		$wp_memory        = $this->environment->wordpress_memory_limit();
		$wp_max_memory    = $this->environment->wordpress_max_memory_limit();
		$php_memory       = $this->environment->php_memory_limit();
		$current_usage    = $this->environment->current_memory_usage();
		$peak_usage       = $this->environment->peak_memory_usage();
		$actual_limit     = $this->memory_bytes( $php_memory );
		$actual_available = PHP_INT_MAX === $actual_limit ? PHP_INT_MAX : max( 0, $actual_limit - $current_usage );
		$status           = 'PASS';

		if ( 0 === $actual_limit || 0 === $actual_available ) {
			$status = 'FAIL';
		} elseif ( self::RECOMMENDED_WORDPRESS_MEMORY_BYTES > $this->memory_bytes( $wp_memory ) || self::MINIMUM_OPERATION_HEADROOM_BYTES > $actual_available ) {
			$status = 'WARNING';
		}

		return array(
			'status'                     => $status,
			'wordpress_memory_limit'     => $wp_memory,
			'wordpress_max_memory_limit' => $wp_max_memory,
			'php_memory_limit'           => $php_memory,
			'current_usage_bytes'        => $current_usage,
			'peak_usage_bytes'           => $peak_usage,
			'wordpress_admin_can_raise'  => $this->environment->wordpress_admin_can_raise_memory(),
			'actual_limit_bytes'         => $actual_limit,
			'actual_available_bytes'     => $actual_available,
		);
	}

	/**
	 * Convert a WordPress memory-limit string to bytes.
	 *
	 * @param string $value WordPress memory-limit value.
	 */
	private function memory_bytes( string $value ): int {
		$normalized = strtoupper( trim( $value ) );
		if ( '-1' === $normalized ) {
			return PHP_INT_MAX;
		}

		if ( 1 !== preg_match( '/^(\d+)([KMGT]?)B?$/', $normalized, $matches ) ) {
			return 0;
		}

		$amount = (int) $matches[1];
		$power  = array_search( $matches[2], array( '', 'K', 'M', 'G', 'T' ), true );

		return false === $power ? 0 : $amount * ( 1024 ** $power );
	}
}
