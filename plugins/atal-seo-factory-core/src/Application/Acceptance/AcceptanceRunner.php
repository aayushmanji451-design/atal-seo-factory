<?php
/**
 * Self-contained Task 02 staging acceptance runner.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\SeoFactory\Application\Health\HealthDataProvider;
use Atal\SeoFactory\Application\Import\CanonicalKnowledgeImporter;
use Atal\SeoFactory\Application\Import\ImportChange;
use Atal\SeoFactory\Application\Import\ImportPlan;
use Atal\SeoFactory\Application\Migration\MigrationRunner;
use Atal\SeoFactory\Bootstrap;
use Atal\SeoFactory\Config\Identifiers;
use Atal\SeoFactory\Domain\Storage\CoreStateStoreInterface;
use Atal\SeoFactory\Domain\Storage\SchemaDatabaseInterface;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use Atal\SeoFactory\Plugin;
use Throwable;

/**
 * Runs only migrations, canonical validation/import, and read-only safety probes.
 */
final class AcceptanceRunner {

	public function __construct(
		private readonly MigrationRunner $migrations,
		private readonly SchemaDatabaseInterface $database,
		private readonly CoreStateStoreInterface $state_store,
		private readonly TableNames $tables,
		private readonly KnowledgeValidator $validator,
		private readonly CanonicalKnowledgeImporter $importer,
		private readonly HealthDataProvider $health,
		private readonly SafetyMonitorInterface $safety,
		private readonly string $master_directory,
		private readonly string $schema_directory
	) {
	}

	/**
	 * Execute the bounded acceptance sequence and contain every Throwable.
	 */
	public function run(): AcceptanceReport {
		$started        = microtime( true );
		$checks         = array();
		$first_dry_run  = $this->empty_plan_summary();
		$second_dry_run = $this->empty_plan_summary();
		$initial_health = $this->health->snapshot();
		$final_health   = $initial_health;
		$observation    = null;
		$safety_started = false;

		$checks[] = new AcceptanceCheck(
			'memory_preflight',
			$initial_health['memory_status'],
			'WP_MEMORY_LIMIT >= 256M is recommended; lower values continue unless a bounded operation actually fails',
			$this->memory_actual( $initial_health ),
			$initial_health['memory_message']
		);

		try {
			$this->safety->start();
			$safety_started = true;
			$checks[]       = $this->check(
				'plugin_bootstrap',
				is_callable( array( Bootstrap::class, 'boot' ) ),
				'Core bootstrap loaded with the new plugin slug',
				Plugin::VERSION . ' / ' . Identifiers::PLUGIN_SLUG,
				'The Core bootstrap and Task 02 identifiers are available.'
			);

			$version_before = $this->state_store->database_version();
			$tables_before  = $this->table_status();
			$checks[]       = $this->check(
				'database_migration_version',
				Identifiers::DATABASE_VERSION === $version_before,
				(string) Identifiers::DATABASE_VERSION,
				(string) $version_before,
				'Database migration version 1 is active.'
			);
			$checks[]       = $this->check(
				'core_tables',
				7 === count( array_filter( $tables_before ) ),
				'7/7 Core tables present',
				count( array_filter( $tables_before ) ) . '/7 Core tables present',
				'All seven Core-owned tables are present.'
			);

			$this->migrations->migrate_to_latest();
			$tables_after = $this->table_status();
			$checks[]     = $this->check(
				'migration_idempotency',
				$version_before === $this->state_store->database_version() && $tables_before === $tables_after,
				'Rerun leaves version and seven-table set unchanged',
				'version ' . $this->state_store->database_version() . ', ' . count( array_filter( $tables_after ) ) . '/7 tables',
				'Rerunning migration version 1 does not duplicate or remove schema.'
			);

			$package    = KnowledgePackage::from_directory( $this->master_directory );
			$validation = $this->validator->validate( $package, $this->schema_directory );
			$checks[]   = $this->check(
				'bundled_knowledge_validation',
				$validation->is_valid(),
				'All Task 01 canonical validators pass',
				$validation->is_valid() ? 'valid' : count( $validation->issues() ) . ' validation issue(s)',
				'Bundled master data is validated by the Task 01 contracts before import.'
			);
			$checks[]   = $this->metric_check( 'active_identities', 43, $validation->metric( 'unique_active_keys' ), 'The complete active catalog contains 43 unique identities.' );
			$checks[]   = $this->metric_check( 'institute_families', 29, $validation->metric( 'institute_families' ), 'The Institute catalog contains 29 families.' );
			$checks[]   = $this->metric_check( 'diploma_identities', 14, $validation->metric( 'diploma_identities' ), 'The Diploma catalog contains 14 identities.' );
			$checks[]   = $this->metric_check( 'institute_options', 49, $validation->metric( 'institute_options' ), 'The canonical Institute families retain 49 options.' );

			$first_plan    = $this->importer->dry_run( $package, $this->schema_directory );
			$first_dry_run = $this->plan_summary( $first_plan );
			$checks[]      = new AcceptanceCheck(
				'first_knowledge_dry_run',
				AcceptanceCheck::PASS,
				'A bounded plan is returned before any import write',
				$first_plan->writes() . ' planned write(s)',
				'Initial inserts and updates are displayed in the downloadable report.'
			);

			$import_result = $this->importer->import( $package, $this->schema_directory );
			$checks[]      = $this->check(
				'transactional_knowledge_import',
				$first_plan->writes() === $import_result->writes(),
				$first_plan->writes() . ' planned write(s) committed atomically',
				$import_result->writes() . ' write(s) committed',
				'The importer recomputed the validated plan and completed one transaction.'
			);

			$fingerprint = $this->state_store->knowledge_fingerprint();
			$checks[]    = $this->check(
				'knowledge_fingerprint',
				is_string( $fingerprint ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $fingerprint ),
				'64-character SHA-256 fingerprint',
				(string) $fingerprint,
				'The committed canonical package fingerprint is recorded.'
			);

			$second_plan    = $this->importer->dry_run( $package, $this->schema_directory );
			$second_dry_run = $this->plan_summary( $second_plan );
			$checks[]       = $this->check(
				'second_knowledge_dry_run',
				0 === $second_plan->writes(),
				'0 writes',
				$second_plan->writes() . ' write(s)',
				'A second dry-run is idempotent and proposes no writes.'
			);
		} catch ( Throwable $throwable ) {
			$checks[] = new AcceptanceCheck(
				'acceptance_runtime',
				AcceptanceCheck::FAIL,
				'All bounded acceptance operations complete without memory or runtime failure',
				$throwable::class,
				$throwable->getMessage()
			);
		} finally {
			if ( $safety_started ) {
				try {
					$observation = $this->safety->stop();
				} catch ( Throwable $throwable ) {
					$checks[] = new AcceptanceCheck( 'safety_monitor', AcceptanceCheck::FAIL, 'Safety observation completes', $throwable::class, $throwable->getMessage() );
				}
			}
		}

		if ( $observation instanceof SafetyObservation ) {
			$checks = array_merge( $checks, $this->safety_checks( $observation ) );
		}

		try {
			$final_health = $this->health->snapshot();
		} catch ( Throwable $throwable ) {
			$checks[] = new AcceptanceCheck( 'final_health_snapshot', AcceptanceCheck::FAIL, 'Final health snapshot is readable', $throwable::class, $throwable->getMessage() );
		}

		$environment                          = $final_health;
		$environment['database_version']      = $this->state_store->database_version();
		$environment['knowledge_fingerprint'] = $this->state_store->knowledge_fingerprint();
		$environment['elapsed_milliseconds']  = (int) round( ( microtime( true ) - $started ) * 1000 );

		return new AcceptanceReport( $checks, $environment, $first_dry_run, $second_dry_run );
	}

	/**
	 * @return array<string,bool>
	 */
	private function table_status(): array {
		$status = array();
		foreach ( $this->tables->keyed() as $key => $table_name ) {
			$status[ $key ] = $this->database->table_exists( $table_name );
		}

		return $status;
	}

	private function check( string $check_id, bool $passed, string $expected, string $actual, string $message ): AcceptanceCheck {
		return new AcceptanceCheck( $check_id, $passed ? AcceptanceCheck::PASS : AcceptanceCheck::FAIL, $expected, $actual, $message );
	}

	private function metric_check( string $check_id, int $expected, int|string|bool|null $actual, string $message ): AcceptanceCheck {
		return $this->check( $check_id, $expected === $actual, (string) $expected, (string) $actual, $message );
	}

	/**
	 * @return array{inserts:int,updates:int,unchanged:int,writes:int,planned_writes:list<array{entity_type:string,entity_key:string,action:string}>}
	 */
	private function plan_summary( ImportPlan $plan ): array {
		$planned_writes = array();
		foreach ( $plan->changes() as $change ) {
			if ( ImportChange::UNCHANGED === $change->action() ) {
				continue;
			}
			$planned_writes[] = array(
				'entity_type' => $change->entity_type(),
				'entity_key'  => $change->entity_key(),
				'action'      => $change->action(),
			);
		}

		return array(
			'inserts'        => $plan->inserts(),
			'updates'        => $plan->updates(),
			'unchanged'      => $plan->unchanged(),
			'writes'         => $plan->writes(),
			'planned_writes' => $planned_writes,
		);
	}

	/**
	 * @return array{inserts:int,updates:int,unchanged:int,writes:int,planned_writes:list<array{entity_type:string,entity_key:string,action:string}>}
	 */
	private function empty_plan_summary(): array {
		return array(
			'inserts'        => 0,
			'updates'        => 0,
			'unchanged'      => 0,
			'writes'         => 0,
			'planned_writes' => array(),
		);
	}

	/**
	 * @return list<AcceptanceCheck>
	 */
	private function safety_checks( SafetyObservation $observation ): array {
		return array(
			$this->check( 'wordpress_content_unchanged', 0 === $observation->saved_posts_pages(), '0 posts/pages created or modified', (string) $observation->saved_posts_pages(), 'No WordPress post or page save occurred during acceptance.' ),
			$this->check( 'images_not_generated', 0 === $observation->attachment_changes(), '0 attachment changes', (string) $observation->attachment_changes(), 'No image or attachment generation occurred during acceptance.' ),
			$this->check( 'rank_math_unchanged', 0 === $observation->rank_math_changes(), '0 Rank Math metadata changes', (string) $observation->rank_math_changes(), 'No Rank Math content was modified during acceptance.' ),
			$this->check( 'publish_jobs_not_executed', 0 === $observation->publish_job_execution_delta(), '0 newly executed publish jobs', (string) $observation->publish_job_execution_delta(), 'Acceptance does not execute the publish queue.' ),
			$this->check( 'remote_requests_not_sent', 0 === $observation->external_requests(), '0 outbound HTTP requests', (string) $observation->external_requests(), 'No AI, publishing API, or Diploma request was sent.' ),
			$this->check( 'secrets_not_logged', 0 === $observation->sensitive_log_delta(), '0 new sensitive audit-log matches', (string) $observation->sensitive_log_delta(), 'No credentials, shared secrets, or authentication tokens were logged.' ),
		);
	}

	/**
	 * @param array{plugin_version:string,plugin_slug:string,rest_namespace:string,database_version:int,expected_database_version:int,site_url:string,environment_type:string,wordpress_version:string,php_version:string,wordpress_memory_limit:string,wordpress_max_memory_limit:string,php_memory_limit:string,current_memory_usage:int,peak_memory_usage:int,memory_status:string,memory_message:string,tables:array<string,array{name:string,exists:bool}>,read_only:bool} $health Health snapshot.
	 */
	private function memory_actual( array $health ): string {
		return sprintf(
			'WP_MEMORY_LIMIT=%s; WP_MAX_MEMORY_LIMIT=%s; PHP memory_limit=%s; current=%d; peak=%d',
			$health['wordpress_memory_limit'],
			$health['wordpress_max_memory_limit'],
			$health['php_memory_limit'],
			$health['current_memory_usage'],
			$health['peak_memory_usage']
		);
	}
}
