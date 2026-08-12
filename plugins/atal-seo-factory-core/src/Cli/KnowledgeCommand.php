<?php
/**
 * WP-CLI canonical knowledge commands.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Cli;

use Atal\Contracts\Data\KnowledgePackage;
use Atal\SeoFactory\Application\Import\CanonicalKnowledgeImporter;
use Atal\SeoFactory\Application\Import\ImportPlan;
use Atal\SeoFactory\Application\Import\InvalidKnowledgeException;

/**
 * Keeps imports out of browser requests and requires an explicit apply flag.
 */
final class KnowledgeCommand {

	public function __construct(
		private readonly CanonicalKnowledgeImporter $importer,
		private readonly string $master_directory,
		private readonly string $schema_directory
	) {
	}

	/**
	 * Display canonical changes without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp atal-seo-factory knowledge dry-run
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string,string> $assoc_args Named arguments.
	 */
	public function dry_run( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );
		try {
			$this->display_plan( $this->importer->dry_run( $this->package(), $this->schema_directory ) );
		} catch ( InvalidKnowledgeException $exception ) {
			$this->report_invalid( $exception );
		}
	}

	/**
	 * Import canonical records transactionally after showing the plan.
	 *
	 * ## OPTIONS
	 *
	 * [--confirm]
	 * : Required acknowledgement that the displayed dry-run may be applied.
	 *
	 * ## EXAMPLES
	 *
	 *     wp atal-seo-factory knowledge import --confirm
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string,string> $assoc_args Named arguments.
	 */
	public function import( array $args, array $assoc_args ): void {
		unset( $args );
		$package = $this->package();

		try {
			$plan = $this->importer->dry_run( $package, $this->schema_directory );
			$this->display_plan( $plan );

			if ( ! array_key_exists( 'confirm', $assoc_args ) ) {
				\WP_CLI::error( 'Dry-run complete. Re-run with --confirm to apply this exact canonical package.' );
			}

			$result = $this->importer->import( $package, $this->schema_directory );
			\WP_CLI::success( 'Canonical knowledge import committed. Writes: ' . $result->writes() );
		} catch ( InvalidKnowledgeException $exception ) {
			$this->report_invalid( $exception );
		}
	}

	private function package(): KnowledgePackage {
		return KnowledgePackage::from_directory( $this->master_directory );
	}

	private function display_plan( ImportPlan $plan ): void {
		foreach ( $plan->changes() as $change ) {
			if ( 'unchanged' === $change->action() ) {
				continue;
			}
			\WP_CLI::log( strtoupper( $change->action() ) . ' ' . $change->entity_type() . ':' . $change->entity_key() );
		}

		\WP_CLI::log( 'DRY_RUN_INSERTS=' . $plan->inserts() );
		\WP_CLI::log( 'DRY_RUN_UPDATES=' . $plan->updates() );
		\WP_CLI::log( 'DRY_RUN_UNCHANGED=' . $plan->unchanged() );
		\WP_CLI::log( 'DRY_RUN_WRITES=' . $plan->writes() );
	}

	private function report_invalid( InvalidKnowledgeException $exception ): void {
		foreach ( $exception->report()->issues() as $issue ) {
			\WP_CLI::warning( $issue->format() );
		}
		\WP_CLI::error( 'Import rejected: canonical knowledge validation failed before writes.' );
	}
}
