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

	private const REQUIRED_STAGING_MEMORY_BYTES = 268435456;

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
	 * @return array{plugin_version:string,plugin_slug:string,rest_namespace:string,database_version:int,expected_database_version:int,site_url:string,environment_type:string,wordpress_version:string,php_version:string,wordpress_memory_limit:string,wordpress_max_memory_limit:string,php_memory_limit:string,current_memory_usage:int,peak_memory_usage:int,memory_status:string,memory_message:string,tables:array<string,array{name:string,exists:bool}>,read_only:bool}
	 */
	public function snapshot(): array {
		$table_status = array();
		foreach ( $this->tables->keyed() as $key => $table_name ) {
			$table_status[ $key ] = array(
				'name'   => $table_name,
				'exists' => $this->database->table_exists( $table_name ),
			);
		}

		$wp_memory = $this->environment->wordpress_memory_limit();

		return array(
			'plugin_version'             => Plugin::VERSION,
			'plugin_slug'                => Identifiers::PLUGIN_SLUG,
			'rest_namespace'             => Identifiers::REST_NAMESPACE,
			'database_version'           => $this->state_store->database_version(),
			'expected_database_version'  => Identifiers::DATABASE_VERSION,
			'site_url'                   => $this->environment->site_url(),
			'environment_type'           => $this->environment->environment_type(),
			'wordpress_version'          => $this->environment->wordpress_version(),
			'php_version'                => $this->environment->php_version(),
			'wordpress_memory_limit'     => $wp_memory,
			'wordpress_max_memory_limit' => $this->environment->wordpress_max_memory_limit(),
			'php_memory_limit'           => $this->environment->php_memory_limit(),
			'current_memory_usage'       => $this->environment->current_memory_usage(),
			'peak_memory_usage'          => $this->environment->peak_memory_usage(),
			'memory_status'              => $this->memory_status( $wp_memory ),
			'memory_message'             => $this->memory_message( $wp_memory ),
			'tables'                     => $table_status,
			'read_only'                  => true,
		);
	}

	private function memory_status( string $wp_memory ): string {
		return $this->memory_bytes( $wp_memory ) >= self::REQUIRED_STAGING_MEMORY_BYTES ? 'PASS' : 'WARNING';
	}

	private function memory_message( string $wp_memory ): string {
		if ( 'PASS' === $this->memory_status( $wp_memory ) ) {
			return 'The normal WordPress request memory limit meets the 256M staging recommendation.';
		}

		return 'The normal WordPress request memory limit is below the 256M recommendation; bounded Task 02 acceptance remains available and fails only on an actual runtime error.';
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
