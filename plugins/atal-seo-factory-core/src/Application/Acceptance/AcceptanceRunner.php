<?php
/**
 * Development-only Task 02 staging acceptance runner.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

use Atal\Contracts\Data\KnowledgePackage;
use Atal\SeoFactory\Application\Health\HealthDataProvider;
use Atal\SeoFactory\Application\Health\RuntimeEnvironmentInterface;
use Atal\SeoFactory\Application\Import\CanonicalKnowledgeImporter;
use Atal\SeoFactory\Application\Import\ImportChange;
use Atal\SeoFactory\Application\Import\ImportPersistenceException;
use Atal\SeoFactory\Application\Import\ImportPlan;
use Atal\SeoFactory\Application\Migration\MigrationRunner;
use Atal\SeoFactory\Bootstrap;
use Atal\SeoFactory\Config\Identifiers;
use Atal\SeoFactory\Domain\Storage\CoreStateStoreInterface;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use Atal\SeoFactory\Plugin;
use RuntimeException;
use Throwable;

/**
 * Runs bounded schema and canonical-import checks without touching WP content.
 */
final class AcceptanceRunner {

	private const RECOMMENDED_OPERATION_HEADROOM_BYTES = 16777216;

	private const EXPECTED_SUMMARY = array(
		'active_total'       => 43,
		'institute_families' => 29,
		'diploma_identities' => 14,
		'institute_options'  => 49,
	);

	public function __construct(
		private readonly HealthDataProvider $health,
		private readonly RuntimeEnvironmentInterface $environment,
		private readonly CoreStateStoreInterface $state_store,
		private readonly TableNames $tables,
		private readonly MigrationRunner $migrations,
		private readonly CanonicalKnowledgeImporter $importer,
		private readonly KnowledgePackageInspector $package_inspector,
		private readonly AcceptanceProbeInterface $probe,
		private readonly string $master_directory,
		private readonly string $schema_directory
	) {
	}

	public function run(): AcceptanceReport {
		$started_at      = gmdate( 'c' );
		$checks          = array();
		$step            = 'initialize';
		$monitoring      = false;
		$remote_requests = 0;

		try {
			$this->probe->start_remote_request_monitor();
			$monitoring      = true;
			$step            = 'environment_preflight';
			$raise_attempted = false;
			$raise_succeeded = false;
			if ( $this->environment->wordpress_admin_can_raise_memory() ) {
				$raise_attempted = true;
				$raise_succeeded = $this->environment->raise_wordpress_admin_memory();
			}
			$health   = $this->health->snapshot();
			$memory   = $health['memory'];
			$checks[] = new AcceptanceCheck(
				'environment_memory_preflight',
				$memory['status'],
				'Actual runtime has at least 16 MiB free; a WP limit below 256M is advisory.',
				array_merge(
					array(
						'site_url'          => $health['site_url'],
						'environment_type'  => $health['environment_type'],
						'wordpress_version' => $health['wordpress_version'],
						'php_version'       => $health['php_version'],
					),
					$memory,
					array(
						'admin_raise_attempted' => $raise_attempted,
						'admin_raise_succeeded' => $raise_succeeded,
					)
				),
				'Runtime capacity is assessed from the effective PHP limit and current request usage.'
			);
			$this->stop_on_failure( $checks, $step );

			$step         = 'plugin_bootstrap';
			$bootstrap_ok = class_exists( Bootstrap::class ) && Plugin::is_development_build();
			$checks[]     = $this->boolean_check(
				'plugin_bootstrap',
				$bootstrap_ok,
				'the Core bootstrap is loaded and version ends in -dev',
				array(
					'bootstrap_loaded' => class_exists( Bootstrap::class ),
					'plugin_version'   => Plugin::VERSION,
				),
				'The acceptance workflow is available only in the development build.'
			);
			$this->stop_on_failure( $checks, $step );

			$step     = 'database_version';
			$checks[] = $this->boolean_check(
				'database_version',
				Identifiers::DATABASE_VERSION === $this->state_store->database_version(),
				Identifiers::DATABASE_VERSION,
				$this->state_store->database_version(),
				'The stored Core schema version must match this build.'
			);

			$step           = 'core_tables';
			$table_status   = $health['tables'];
			$missing_tables = array_keys( array_filter( $table_status, static fn ( array $table ): bool => ! $table['exists'] ) );
			$checks[]       = $this->boolean_check(
				'core_tables_exist',
				array() === $missing_tables && 7 === count( $table_status ),
				array(
					'table_count' => 7,
					'missing'     => array(),
				),
				array(
					'table_count' => count( $table_status ),
					'missing'     => $missing_tables,
				),
				'All seven Core-owned tables must exist before canonical import.'
			);
			$this->stop_on_failure( $checks, $step );

			$step                  = 'migration_idempotency';
			$rows_before_migration = $this->probe->table_row_counts( $this->tables->all() );
			$this->migrations->migrate_to_latest();
			$rows_after_migration = $this->probe->table_row_counts( $this->tables->all() );
			$migration_ok         = $rows_before_migration === $rows_after_migration
				&& Identifiers::DATABASE_VERSION === $this->state_store->database_version();
			$checks[]             = $this->boolean_check(
				'migration_idempotency',
				$migration_ok,
				array(
					'database_version'     => Identifiers::DATABASE_VERSION,
					'row_counts_unchanged' => true,
				),
				array(
					'database_version' => $this->state_store->database_version(),
					'before'           => $rows_before_migration,
					'after'            => $rows_after_migration,
				),
				'Re-running the versioned migration must not change stored rows.'
			);
			$this->stop_on_failure( $checks, $step );

			$step            = 'canonical_package_load';
			$package         = KnowledgePackage::from_directory( $this->master_directory );
			$package_summary = $this->package_inspector->summary( $package );
			$checks[]        = $this->boolean_check(
				'canonical_package_contract_counts',
				self::EXPECTED_SUMMARY === $package_summary,
				self::EXPECTED_SUMMARY,
				$package_summary,
				'The bundled package preserves the approved family, identity, and nested option counts.'
			);
			$this->stop_on_failure( $checks, $step );

			$content_before     = $this->probe->content_snapshot();
			$publishing_before  = $this->probe->publishing_snapshot( $this->tables->publish_jobs() );
			$audit_before       = $this->probe->audit_log_count( $this->tables->audit_logs() );
			$course_rows_before = $rows_after_migration[ $this->tables->courses() ] ?? 0;

			$step            = 'canonical_dry_run';
			$first_plan      = $this->importer->dry_run( $package, $this->schema_directory );
			$first_summary   = $this->plan_summary( $first_plan );
			$checks[]        = new AcceptanceCheck(
				'canonical_package_validation',
				AcceptanceCheck::PASS,
				'All bundled Task 01 contracts validate before any write.',
				'validated',
				'The dry run completed the canonical validator without an exception.'
			);
			$initial_plan_ok = 43 === $first_summary['courses_total']
				&& ( 0 !== $course_rows_before || 43 === $first_summary['course_inserts'] );
			$checks[]        = $this->boolean_check(
				'initial_course_plan',
				$initial_plan_ok,
				0 === $course_rows_before ? array(
					'courses_total'  => 43,
					'course_inserts' => 43,
				) : array( 'courses_total' => 43 ),
				array_merge( array( 'stored_courses_before' => $course_rows_before ), $first_summary ),
				'An empty canonical table plans 43 inserts; an existing table still plans exactly 43 identities.'
			);
			$checks[]        = new AcceptanceCheck(
				'dry_run_counts_before_write',
				AcceptanceCheck::PASS,
				'Exact dry-run counts are recorded before the transaction.',
				$first_summary,
				'No canonical write occurred before these counts were captured.'
			);
			$this->stop_on_failure( $checks, $step );

			$step          = 'canonical_transactional_import';
			$import_result = $this->importer->import( $package, $this->schema_directory );
			$checks[]      = $this->boolean_check(
				'canonical_transactional_import',
				$first_plan->writes() === $import_result->writes(),
				array( 'planned_writes' => $first_plan->writes() ),
				array( 'committed_writes' => $import_result->writes() ),
				'The validated plan was applied through the importer transaction boundary.'
			);

			$step           = 'stored_canonical_summary';
			$stored_summary = $this->probe->course_summary( $this->tables->courses() );
			$checks[]       = $this->boolean_check(
				'stored_canonical_summary',
				self::EXPECTED_SUMMARY === $stored_summary,
				self::EXPECTED_SUMMARY,
				$stored_summary,
				'The Core courses table contains only the 43 approved active identities and their nested options.'
			);
			$fingerprint    = $this->state_store->knowledge_fingerprint();
			$this->state_store->ensure_reactivation_baseline();
			$checks[] = $this->boolean_check(
				'knowledge_fingerprint',
				null !== $fingerprint && 64 === strlen( $fingerprint ),
				'a 64-character SHA-256 knowledge fingerprint',
				$fingerprint,
				'The imported canonical package fingerprint is retained for reactivation checks.'
			);
			$this->stop_on_failure( $checks, $step );

			$step           = 'second_dry_run';
			$second_plan    = $this->importer->dry_run( $package, $this->schema_directory );
			$second_summary = $this->plan_summary( $second_plan );
			$checks[]       = $this->boolean_check(
				'second_dry_run_zero_writes',
				0 === $second_plan->writes() && 43 === $second_summary['course_unchanged'],
				array(
					'writes'           => 0,
					'course_unchanged' => 43,
				),
				$second_summary,
				'Re-running acceptance cannot duplicate canonical records.'
			);

			$content_after    = $this->probe->content_snapshot();
			$publishing_after = $this->probe->publishing_snapshot( $this->tables->publish_jobs() );
			$audit_after      = $this->probe->audit_log_count( $this->tables->audit_logs() );
			$checks[]         = $this->boolean_check(
				'wordpress_content_unchanged',
				$content_before === $content_after,
				$content_before,
				$content_after,
				'Post/page count, identity fingerprint, and modification-state fingerprint remain identical.'
			);
			$checks[]         = $this->boolean_check(
				'publishing_jobs_unchanged',
				$publishing_before === $publishing_after,
				$publishing_before,
				$publishing_after,
				'No publishing job was created, attempted, or changed.'
			);
			$checks[]         = $this->boolean_check(
				'credentials_or_secrets_written_to_logs',
				$audit_before === $audit_after,
				$audit_before,
				$audit_after,
				'The acceptance operation emitted no Core log row and never serializes credentials, tokens, cookies, or request URLs.'
			);

			$final_health            = $this->health->snapshot();
			$available_memory        = $final_health['memory']['actual_available_bytes'];
			$operation_memory_status = 0 === $available_memory
				? AcceptanceCheck::FAIL
				: ( self::RECOMMENDED_OPERATION_HEADROOM_BYTES > $available_memory ? AcceptanceCheck::WARNING : AcceptanceCheck::PASS );
			$checks[]                = new AcceptanceCheck(
				'acceptance_operation_memory',
				$operation_memory_status,
				'Operation completes with at least 16 MiB actual headroom.',
				$final_health['memory'],
				'The completed bounded operation supplies direct current, peak, limit, and available-memory evidence.'
			);
			$this->stop_on_failure( $checks, 'final_safety_verification' );
		} catch ( Throwable $throwable ) {
			$failed_step = $throwable instanceof ImportPersistenceException ? $throwable->failed_step() : $step;
			$checks[]    = new AcceptanceCheck(
				'acceptance_boundary',
				AcceptanceCheck::FAIL,
				'The bounded Task 02 operation completes without an uncaught error.',
				array(
					'failed_step' => $failed_step,
					'exception'   => get_class( $throwable ),
				),
				$this->sanitized_error_message( $throwable )
			);
		} finally {
			if ( $monitoring ) {
				$remote_requests = $this->probe->remote_request_count();
				$this->probe->stop_remote_request_monitor();
			}
		}

		$checks[] = $this->boolean_check(
			'remote_requests',
			0 === $remote_requests,
			0,
			$remote_requests,
			'The request monitor observed no outbound WordPress HTTP request during acceptance.'
		);

		return new AcceptanceReport( $checks, $started_at, gmdate( 'c' ) );
	}

	/**
	 * @param list<AcceptanceCheck> $checks Current checks.
	 */
	private function stop_on_failure( array $checks, string $step ): void {
		foreach ( $checks as $check ) {
			if ( AcceptanceCheck::FAIL === $check->status() ) {
				throw new RuntimeException( 'Acceptance stopped safely after failed step: ' . $step );
			}
		}
	}

	private function boolean_check( string $id, bool $passed, mixed $expected, mixed $actual, string $message ): AcceptanceCheck {
		return new AcceptanceCheck( $id, $passed ? AcceptanceCheck::PASS : AcceptanceCheck::FAIL, $expected, $actual, $message );
	}

	/**
	 * @return array{inserts:int,updates:int,unchanged:int,writes:int,courses_total:int,course_inserts:int,course_updates:int,course_unchanged:int}
	 */
	private function plan_summary( ImportPlan $plan ): array {
		$course_inserts   = 0;
		$course_updates   = 0;
		$course_unchanged = 0;
		foreach ( $plan->changes() as $change ) {
			if ( 'course' !== $change->entity_type() ) {
				continue;
			}
			if ( ImportChange::INSERT === $change->action() ) {
				++$course_inserts;
			} elseif ( ImportChange::UPDATE === $change->action() ) {
				++$course_updates;
			} else {
				++$course_unchanged;
			}
		}

		return array(
			'inserts'          => $plan->inserts(),
			'updates'          => $plan->updates(),
			'unchanged'        => $plan->unchanged(),
			'writes'           => $plan->writes(),
			'courses_total'    => $course_inserts + $course_updates + $course_unchanged,
			'course_inserts'   => $course_inserts,
			'course_updates'   => $course_updates,
			'course_unchanged' => $course_unchanged,
		);
	}

	private function sanitized_error_message( Throwable $throwable ): string {
		$message = preg_replace( '/(?:password|secret|token|authorization|stream[ _-]?key)\s*[:=]\s*\S+/i', '[redacted]', $throwable->getMessage() );

		return is_string( $message ) && '' !== $message ? $message : 'The acceptance operation failed safely.';
	}
}
