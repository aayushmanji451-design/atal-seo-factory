<?php
/** WordPress receiver composition root. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\DiplomaReceiver\Admin\HealthPage;
use Atal\DiplomaReceiver\Application\Acceptance\AcceptanceRunner;
use Atal\DiplomaReceiver\Application\Health\HealthDataProvider;
use Atal\DiplomaReceiver\Application\Lifecycle\Activator;
use Atal\DiplomaReceiver\Application\Lifecycle\Deactivator;
use Atal\DiplomaReceiver\Application\Migration\MigrationRunner;
use Atal\DiplomaReceiver\Application\Migration\ReceiverTablesMigration;
use Atal\DiplomaReceiver\Application\Receiver\ArticleReceiver;
use Atal\DiplomaReceiver\Application\Receiver\RollbackReceiver;
use Atal\DiplomaReceiver\Application\Security\HmacAuthenticator;
use Atal\DiplomaReceiver\Application\SeoImages\Task05RemoteService;
use Atal\DiplomaReceiver\Application\Validation\CanonicalDiplomaCatalog;
use Atal\DiplomaReceiver\Application\Validation\PayloadValidator;
use Atal\DiplomaReceiver\Infrastructure\Database\TableDefinitions;
use Atal\DiplomaReceiver\Infrastructure\Database\TableNames;
use Atal\DiplomaReceiver\Plugin;
use Atal\DiplomaReceiver\Rest\JsonPayloadDecoder;
use Atal\DiplomaReceiver\Rest\ReceiverController;
use Atal\SeoImages\Application\AcceptanceCoordinator as Task05Coordinator;
use Atal\SeoImages\Application\CanonicalAssetResolver;
use Atal\SeoImages\Domain\AcceptanceFixture;
use Atal\SeoImages\Infrastructure\WordPress\LocalImageManager;
use Atal\SeoImages\Infrastructure\WordPress\WordPressFixtureRepository;
use Atal\SeoImages\Infrastructure\WordPress\WordPressOptionStateStore;
use Atal\SeoImages\Infrastructure\WordPress\WordPressRuntimeGuard;
use RuntimeException;
use wpdb;
final class ServiceFactory {
	public static function activator(): Activator {
		$database   = self::database();
		$state      = new WordPressStateStore();
		$tables     = new TableNames( $database->prefix() );
		$migrations = new MigrationRunner( array( new ReceiverTablesMigration( $database, $tables, new TableDefinitions() ) ), $state );
		return new Activator( $migrations, $state, self::catalog(), Plugin::VERSION ); }
	public static function deactivator(): Deactivator {
		return new Deactivator(); }
	public static function plugin(): Plugin {
		return new Plugin( static fn(): ReceiverController=>self::controller(), static fn(): HealthPage=>self::health_page() ); }
	private static function controller(): ReceiverController {
		$services = self::services();
		return new ReceiverController( $services['articles'], $services['rollbacks'], $services['authenticator'], $services['decoder'], $services['health'], $services['task05'] ); }
	private static function health_page(): HealthPage {
		$services = self::services();
		return new HealthPage( $services['health'], new AcceptanceRunner( $services['health'], $services['authenticator'], $services['validator'], $services['decoder'] ) ); }
	/** @return array{articles:ArticleReceiver,rollbacks:RollbackReceiver,authenticator:HmacAuthenticator,decoder:JsonPayloadDecoder,health:HealthDataProvider,validator:PayloadValidator,task05:Task05RemoteService} */
	private static function services(): array {
		$native        = self::native_database();
		$database      = new WpdbAdapter( $native );
		$tables        = new TableNames( $database->prefix() );
		$state         = new WordPressStateStore();
		$aioseo        = new AioseoEnvironmentAdapter();
		$authenticator = new HmacAuthenticator( new WordPressSecretProvider(), new SystemClock() );
		$receipts      = new WpdbReceiptStore( $native, $tables );
		$posts         = new WordPressDraftPostService( $aioseo );
		$audit         = new WpdbAuditLogger( $native, $tables );
		$validator     = new PayloadValidator( self::catalog() );
		$paths         = self::knowledge_paths();
		$task05        = new Task05Coordinator(
			new AcceptanceFixture( 'atal_diploma', 'diplomanext.ataldiploma.com', 5704, 'article_task04_atal_diploma_basic_health_care_course_overview_v1', 'diploma_basic_health_care', 'course_overview', 'Diploma in Basic Health Care: Duration and Fees | Atal Diploma', 'Explore the Diploma in Basic Health Care at Atal Diploma, with verified duration, fee, eligibility, learning focus, and approved course information.', 'Diploma in Basic Health Care' ),
			new CanonicalAssetResolver( $paths['master'], $paths['schemas'], KnowledgeValidator::create_default() ),
			new WordPressRuntimeGuard( array( 'atal-seo-connector' ) ),
			new WordPressFixtureRepository( WordPressDraftPostService::OWNER_META, WordPressDraftPostService::COURSE_META ),
			$aioseo,
			new LocalImageManager(),
			new WordPressOptionStateStore( \Atal\DiplomaReceiver\Config\Identifiers::OPTION_TASK05_STATE ),
			$audit,
			Plugin::VERSION,
			array( 5700 )
		);
		return array(
			'articles'      => new ArticleReceiver( $authenticator, $validator, $receipts, $database, $posts, $aioseo, new WordPressFeaturedImageVerifier(), $audit ),
			'rollbacks'     => new RollbackReceiver( $authenticator, $receipts, $database, $posts, $audit ),
			'authenticator' => $authenticator,
			'decoder'       => new JsonPayloadDecoder(),
			'health'        => new HealthDataProvider( $database, $state, $tables, $aioseo ),
			'validator'     => $validator,
			'task05'        => new Task05RemoteService( $authenticator, $receipts, $task05, $database ),
		); }
	private static function catalog(): CanonicalDiplomaCatalog {
		$paths = self::knowledge_paths();
		return new CanonicalDiplomaCatalog( $paths['master'], $paths['schemas'], KnowledgeValidator::create_default() ); }
	/** @return array{master:string,schemas:string} */ private static function knowledge_paths(): array {
		$plugin_root = dirname( __DIR__, 3 );
		$bundled     = $plugin_root . '/knowledge';
		if ( is_dir( $bundled . '/master' ) && is_dir( $bundled . '/schemas' ) ) {
			return array(
				'master'  => $bundled . '/master',
				'schemas' => $bundled . '/schemas',
			);
		} $project = dirname( $plugin_root, 2 );
		return array(
			'master'  => $project . '/data/master',
			'schemas' => $project . '/data/schemas',
		); }
	private static function database(): WpdbAdapter {
		return new WpdbAdapter( self::native_database() ); }
	private static function native_database(): wpdb {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			throw new RuntimeException( 'WordPress database is unavailable.' );
		} return $wpdb; }
	private function __construct() {}
}
