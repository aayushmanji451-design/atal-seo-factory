<?php
/**
 * Core plugin runtime registration.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory;

use Atal\SeoFactory\Admin\HealthPage;
use Atal\SeoFactory\Cli\KnowledgeCommand;
use Closure;
use Throwable;

/**
 * Registers only lightweight admin and command-line entry points.
 */
final class Plugin {

	public const VERSION = '0.2.1-dev-task-02';

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
			'atal-seo-factory-core-health',
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
			$factory = $this->health_page_factory;
			$page    = $factory();
			$page->render();
		} catch ( Throwable $throwable ) {
			$this->render_runtime_error( $throwable );
		}
	}

	private function render_runtime_error( Throwable $throwable ): void {
		$message = sprintf(
			'Task 02 staging acceptance could not start: %s: %s',
			$throwable::class,
			$throwable->getMessage()
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'ATAL SEO Factory Core Health', 'atal-seo-factory-core' ); ?></h1>
			<div class="notice notice-error"><p><?php echo esc_html( $message ); ?></p></div>
		</div>
		<?php
	}
}
