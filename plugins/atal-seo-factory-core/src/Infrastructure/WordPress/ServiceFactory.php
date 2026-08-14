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
use Atal\SeoFactory\Admin\CanaryPanel;
use Atal\SeoFactory\Application\Acceptance\AcceptanceRunner;
use Atal\SeoFactory\Application\Acceptance\KnowledgePackageInspector;
use Atal\SeoFactory\Application\Canary\CanaryContentValidator;
use Atal\SeoFactory\Application\Canary\CanaryCoordinator;
use Atal\SeoFactory\Application\Canary\CanaryJsonImporter;
use Atal\SeoFactory\Application\Canary\CanonicalCanaryArticleBuilder;
use Atal\SeoFactory\Application\Canary\HmacRequestSigner;
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
use Atal\SeoFactory\Infrastructure\WordPress\Canary\WordPressCanaryPostService;
use Atal\SeoFactory\Infrastructure\WordPress\Canary\WordPressCanaryRuntimeGuard;
use Atal\SeoFactory\Infrastructure\WordPress\Canary\WordPressDiplomaReceiverClient;
use Atal\SeoFactory\Infrastructure\WordPress\Canary\WpdbCanaryAuditLogger;
use Atal\SeoFactory\Infrastructure\WordPress\Canary\WpdbCanaryStateRepository;
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
		return new Plugin(
			static fn (): HealthPage => self::health_page(),
			static fn (): KnowledgeCommand => self::knowledge_command()
		);
	}

	private static function health_page(): HealthPage {
		$native_database = self::native_database();
		$database        = new WpdbAdapter( $native_database );
		$state           = new WordPressCoreStateStore();
		$tables          = new TableNames( $database->table_prefix() );
		$runtime         = new WordPressRuntimeEnvironment();
		$health_provider = new HealthDataProvider( $database, $state, $tables, $runtime );
		$paths           = self::knowledge_paths();
		$importer        = new CanonicalKnowledgeImporter(
			KnowledgeValidator::create_default(),
			new KnowledgeRecordFactory(),
			new WpdbKnowledgeRepository( $database, $tables ),
			$database,
			$state
		);
		$migrations      = new MigrationRunner(
			array( new CoreTablesMigration( $database, $tables, new CoreTableDefinitions() ) ),
			$state
		);
		$acceptance      = new AcceptanceRunner(
			$health_provider,
			$runtime,
			$state,
			$tables,
			$migrations,
			$importer,
			new KnowledgePackageInspector(),
			new WpdbAcceptanceProbe( $native_database ),
			$paths['master'],
			$paths['schemas']
		);

		$canary_importer = new CanaryJsonImporter();
		$canary          = new CanaryCoordinator(
			$canary_importer,
			new CanonicalCanaryArticleBuilder( $paths['master'], $paths['schemas'], KnowledgeValidator::create_default() ),
			new CanaryContentValidator(),
			new WordPressCanaryRuntimeGuard(),
			new WordPressCanaryPostService(),
			new WpdbCanaryStateRepository( $native_database, $tables ),
			new WpdbCanaryAuditLogger( $native_database, $tables ),
			new WordPressDiplomaReceiverClient( new HmacRequestSigner() ),
			$database
		);

		return new HealthPage( $health_provider, $acceptance, new WordPressAcceptanceReportStore(), new CanaryPanel( $canary, $canary_importer ) );
	}

	private static function knowledge_command(): KnowledgeCommand {
		$database = self::database();
		$state    = new WordPressCoreStateStore();
		$tables   = new TableNames( $database->table_prefix() );
		$paths    = self::knowledge_paths();
		$importer = new CanonicalKnowledgeImporter(
			KnowledgeValidator::create_default(),
			new KnowledgeRecordFactory(),
			new WpdbKnowledgeRepository( $database, $tables ),
			$database,
			$state
		);

		return new KnowledgeCommand( $importer, $paths['master'], $paths['schemas'] );
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
		return new WpdbAdapter( self::native_database() );
	}

	private static function native_database(): wpdb {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			throw new RuntimeException( 'WordPress database is unavailable.' );
		}

		return $wpdb;
	}

	private function __construct() {
	}
}
