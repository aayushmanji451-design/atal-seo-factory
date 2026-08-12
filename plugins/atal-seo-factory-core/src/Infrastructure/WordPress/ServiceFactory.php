<?php
/**
 * WordPress service composition.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress;

use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\SeoFactory\Admin\HealthPage;
use Atal\SeoFactory\Application\Health\HealthDataProvider;
use Atal\SeoFactory\Application\Import\CanonicalKnowledgeImporter;
use Atal\SeoFactory\Application\Import\KnowledgeRecordFactory;
use Atal\SeoFactory\Application\Lifecycle\Activator;
use Atal\SeoFactory\Application\Lifecycle\Deactivator;
use Atal\SeoFactory\Application\Migration\CoreTablesMigration;
use Atal\SeoFactory\Application\Migration\MigrationRunner;
use Atal\SeoFactory\Cli\KnowledgeCommand;
use Atal\SeoFactory\Infrastructure\Database\CoreTableDefinitions;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use Atal\SeoFactory\Plugin;
use RuntimeException;
use wpdb;

/**
 * Builds the small Core services at WordPress entry points.
 */
final class ServiceFactory {

	public static function activator(): Activator {
		$database = self::database();
		$state    = new WordPressCoreStateStore();
		$tables   = new TableNames( $database->table_prefix() );
		$runner   = new MigrationRunner(
			array( new CoreTablesMigration( $database, $tables, new CoreTableDefinitions() ) ),
			$state
		);

		return new Activator( $runner, $state, Plugin::VERSION );
	}

	public static function deactivator(): Deactivator {
		return new Deactivator();
	}

	public static function plugin(): Plugin {
		$database = self::database();
		$state    = new WordPressCoreStateStore();
		$tables   = new TableNames( $database->table_prefix() );
		$health   = new HealthPage( new HealthDataProvider( $database, $state, $tables, new WordPressRuntimeEnvironment() ) );
		$paths    = self::knowledge_paths();
		$importer = new CanonicalKnowledgeImporter(
			KnowledgeValidator::create_default(),
			new KnowledgeRecordFactory(),
			new WpdbKnowledgeRepository( $database, $tables ),
			$database,
			$state
		);

		return new Plugin( $health, new KnowledgeCommand( $importer, $paths['master'], $paths['schemas'] ) );
	}

	/**
	 * @return array{master:string,schemas:string}
	 */
	private static function knowledge_paths(): array {
		$plugin_root = dirname( __DIR__, 3 );
		$bundled     = $plugin_root . '/knowledge';
		if ( is_dir( $bundled . '/master' ) && is_dir( $bundled . '/schemas' ) ) {
			return array(
				'master'  => $bundled . '/master',
				'schemas' => $bundled . '/schemas',
			);
		}

		$project_root = dirname( $plugin_root, 2 );

		return array(
			'master'  => $project_root . '/data/master',
			'schemas' => $project_root . '/data/schemas',
		);
	}

	private static function database(): WpdbAdapter {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			throw new RuntimeException( 'WordPress database is unavailable.' );
		}

		return new WpdbAdapter( $wpdb );
	}

	private function __construct() {
	}
}
