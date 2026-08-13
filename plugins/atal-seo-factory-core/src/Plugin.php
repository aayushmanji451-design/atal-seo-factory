<?php
/**
 * Core plugin runtime registration.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory;

use Atal\SeoFactory\Admin\HealthPage;
use Atal\SeoFactory\Admin\CanaryPanel;
use Atal\SeoFactory\Cli\KnowledgeCommand;
use Closure;
use Throwable;

/**
 * Registers only lightweight admin and command-line entry points.
 */
final class Plugin {

	public const VERSION = '0.4.0-dev';

	public static function is_development_build(): bool {
		return str_ends_with( self::VERSION, '-dev' );
	}

	/**
	 * Create the lightweight runtime registrar.
	 *
	 * @param Closure():HealthPage       $health_page_factory       Lazy health-page factory.
	 * @param Closure():KnowledgeCommand $knowledge_command_factory Lazy CLI-command factory.
	 */
	public function __construct(
		private readonly Closure $health_page_factory,
		private readonly Closure $knowledge_command_factory
	) {
	}

	/**
	 * Register runtime hooks without performing migrations or imports.
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_health_page' ) );
		if ( self::is_development_build() ) {
			add_action( 'admin_post_' . HealthPage::RUN_ACTION, array( $this, 'run_acceptance' ) );
			add_action( 'admin_post_' . HealthPage::DOWNLOAD_ACTION, array( $this, 'download_acceptance_report' ) );
			add_action( 'admin_post_' . CanaryPanel::RUN_INSTITUTE_ACTION, array( $this, 'run_institute_canary' ) );
			add_action( 'admin_post_' . CanaryPanel::RUN_DIPLOMA_ACTION, array( $this, 'run_diploma_canary' ) );
			add_action( 'admin_post_' . CanaryPanel::VERIFY_ACTION, array( $this, 'verify_canary' ) );
			add_action( 'admin_post_' . CanaryPanel::ROLLBACK_ACTION, array( $this, 'rollback_canary' ) );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\\WP_CLI' ) ) {
			$factory = $this->knowledge_command_factory;
			\WP_CLI::add_command( 'atal-seo-factory knowledge', $factory() );
		}
	}

	/**
	 * Register the Tools page without constructing acceptance services.
	 */
	public function register_health_page(): void {
		add_management_page(
			esc_html__( 'ATAL SEO Factory Health', 'atal-seo-factory-core' ),
			esc_html__( 'ATAL SEO Factory Health', 'atal-seo-factory-core' ),
			'manage_options',
			HealthPage::PAGE_SLUG,
			array( $this, 'render_health_page' )
		);
	}

	/**
	 * Construct and render the health page only when explicitly requested.
	 */
	public function render_health_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'atal-seo-factory-core' ) );
		}

		try {
			$this->health_page()->render();
		} catch ( Throwable $throwable ) {
			$this->render_runtime_error( $throwable );
		}
	}

	/**
	 * Run the nonce-protected bounded acceptance action lazily.
	 */
	public function run_acceptance(): void {
		try {
			$this->health_page()->run_acceptance();
		} catch ( Throwable $throwable ) {
			wp_die( esc_html( $this->runtime_error_message( $throwable ) ) );
		}
	}

	/**
	 * Download the latest acceptance report lazily.
	 */
	public function download_acceptance_report(): void {
		try {
			$this->health_page()->download_report();
		} catch ( Throwable $throwable ) {
			wp_die( esc_html( $this->runtime_error_message( $throwable ) ) );
		}
	}

	public function run_institute_canary(): void {
		$this->health_page()->run_institute_canary(); }

	public function run_diploma_canary(): void {
		$this->health_page()->run_diploma_canary(); }

	public function verify_canary(): void {
		$this->health_page()->verify_canary(); }

	public function rollback_canary(): void {
		$this->health_page()->rollback_canary(); }

	private function health_page(): HealthPage {
		$factory = $this->health_page_factory;

		return $factory();
	}

	private function render_runtime_error( Throwable $throwable ): void {
		$message = $this->runtime_error_message( $throwable );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'ATAL SEO Factory Core Health', 'atal-seo-factory-core' ); ?></h1>
			<div class="notice notice-error"><p><?php echo esc_html( $message ); ?></p></div>
		</div>
		<?php
	}

	private function runtime_error_message( Throwable $throwable ): string {
		$message = preg_replace(
			'/(?:password|secret|token|authorization|stream[ _-]?key)\s*[:=]\s*\S+/i',
			'[redacted]',
			$throwable->getMessage()
		);
		$detail  = is_string( $message ) && '' !== $message ? $message : 'The bounded acceptance operation failed safely.';

		return sprintf( 'Task 02 staging acceptance could not start: %s: %s', $throwable::class, $detail );
	}
}
