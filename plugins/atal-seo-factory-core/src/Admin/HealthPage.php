<?php
/**
 * Core health and development-only Task 02 acceptance page.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Admin;

use Atal\SeoFactory\Application\Acceptance\AcceptanceReportStoreInterface;
use Atal\SeoFactory\Application\Acceptance\AcceptanceRunner;
use Atal\SeoFactory\Application\Health\HealthDataProvider;
use Atal\SeoFactory\Plugin;

/**
 * Displays read-only health data and one bounded development acceptance action.
 */
final class HealthPage {

	public const PAGE_SLUG = 'atal-seo-factory-core-health';

	public const RUN_ACTION = 'atal_seo_factory_task_02_acceptance';

	public const DOWNLOAD_ACTION = 'atal_seo_factory_task_02_acceptance_download';

	private const RUN_NONCE = 'atal_seo_factory_task_02_acceptance_run';

	private const DOWNLOAD_NONCE = 'atal_seo_factory_task_02_acceptance_download';

	public function __construct(
		private readonly HealthDataProvider $health,
		private readonly AcceptanceRunner $acceptance,
		private readonly AcceptanceReportStoreInterface $reports,
		private readonly CanaryPanel $canary
	) {
	}

	/**
	 * Register only admin-facing development hooks.
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register' ) );
		if ( Plugin::is_development_build() ) {
			add_action( 'admin_post_' . self::RUN_ACTION, array( $this, 'run_acceptance' ) );
			add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'download_report' ) );
		}
	}

	public function register(): void {
		add_management_page(
			esc_html__( 'ATAL SEO Factory Health', 'atal-seo-factory-core' ),
			esc_html__( 'ATAL SEO Factory Health', 'atal-seo-factory-core' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		$this->authorize_view();
		$snapshot = $this->health->snapshot();
		$latest   = $this->reports->latest();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'ATAL SEO Factory Core Health', 'atal-seo-factory-core' ); ?></h1>
			<p><?php echo esc_html__( 'Read-only environment diagnostics. A 40M WP limit is advisory when the actual admin/PHP runtime has safe headroom.', 'atal-seo-factory-core' ); ?></p>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $this->summary_rows( $snapshot ) as $label => $value ) : ?>
						<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<h2><?php echo esc_html__( 'Core tables', 'atal-seo-factory-core' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $this->table_rows( $snapshot ) as $name => $status ) : ?>
						<tr><th scope="row"><?php echo esc_html( $name ); ?></th><td><?php echo esc_html( $status ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php $this->render_acceptance_section( $latest ); ?>
			<?php $this->canary->render(); ?>
		</div>
		<?php
	}

	public function run_institute_canary(): void {
		$this->canary->run_institute(); }

	public function run_diploma_canary(): void {
		$this->canary->run_diploma(); }

	public function verify_canary(): void {
		$this->canary->verify(); }

	public function rollback_canary(): void {
		$this->canary->rollback(); }

	public function configure_canary_hmac(): void {
		$this->canary->configure_hmac(); }

	public function run_acceptance(): void {
		$this->authorize_action( self::RUN_NONCE );
		if ( ! Plugin::is_development_build() ) {
			wp_die( esc_html__( 'Task 02 acceptance is available only in a development build.', 'atal-seo-factory-core' ) );
		}

		$report = $this->acceptance->run()->to_array();
		$this->reports->save( $report );
		$status = $report['status'];
		$url    = add_query_arg(
			array(
				'page'                      => self::PAGE_SLUG,
				'task_02_acceptance_status' => rawurlencode( $status ),
			),
			admin_url( 'tools.php' )
		);
		if ( ! wp_safe_redirect( $url ) ) {
			wp_die( esc_html__( 'Unable to return to the health page.', 'atal-seo-factory-core' ) );
		}
		exit;
	}

	public function download_report(): void {
		$this->authorize_action( self::DOWNLOAD_NONCE );
		$report = $this->reports->latest();
		if ( null === $report ) {
			wp_die( esc_html__( 'No Task 02 acceptance report is available.', 'atal-seo-factory-core' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="atal-seo-factory-task-02-acceptance.json"' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional JSON download after capability and nonce checks.
		exit;
	}

	/**
	 * @param array<string,mixed>|null $latest Latest report.
	 */
	private function render_acceptance_section( ?array $latest ): void {
		if ( ! Plugin::is_development_build() ) {
			return;
		}
		?>
		<h2><?php echo esc_html__( 'Task 02 Staging Acceptance', 'atal-seo-factory-core' ); ?></h2>
		<p><?php echo esc_html__( 'Runs bounded schema and canonical-knowledge checks only. It does not create posts, publish, call remote services, generate media, or change Rank Math.', 'atal-seo-factory-core' ); ?></p>
		<form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_html( self::RUN_ACTION ); ?>">
			<?php $this->nonce_field( self::RUN_NONCE ); ?>
			<?php submit_button( esc_html__( 'Run Task 02 Acceptance', 'atal-seo-factory-core' ), 'primary', 'submit', false ); ?>
		</form>
		<?php if ( null !== $latest ) : ?>
			<p><strong><?php echo esc_html__( 'Latest result:', 'atal-seo-factory-core' ); ?></strong> <?php echo esc_html( $this->display_string( $latest['status'] ?? 'UNKNOWN' ) ); ?></p>
			<p><a class="button" href="<?php echo esc_html( $this->download_url() ); ?>"><?php echo esc_html__( 'Download JSON acceptance report', 'atal-seo-factory-core' ); ?></a></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param array<string,mixed> $snapshot Health snapshot.
	 *
	 * @return array<string,string>
	 */
	private function summary_rows( array $snapshot ): array {
		/** @var array<string,mixed> $memory */
		$memory = $snapshot['memory'];

		return array(
			'Plugin version'                      => $this->display_string( $snapshot['plugin_version'] ?? null ),
			'REST namespace'                      => $this->display_string( $snapshot['rest_namespace'] ?? null ),
			'Database version'                    => $this->display_string( $snapshot['database_version'] ?? null ),
			'Knowledge fingerprint'               => $this->display_string( $snapshot['knowledge_fingerprint'] ?? 'not imported' ),
			'Site URL'                            => $this->display_string( $snapshot['site_url'] ?? null ),
			'Environment type'                    => $this->display_string( $snapshot['environment_type'] ?? null ),
			'WordPress version'                   => $this->display_string( $snapshot['wordpress_version'] ?? null ),
			'PHP version'                         => $this->display_string( $snapshot['php_version'] ?? null ),
			'WP_MEMORY_LIMIT'                     => $this->display_string( $memory['wordpress_memory_limit'] ?? null ),
			'WP_MAX_MEMORY_LIMIT'                 => $this->display_string( $memory['wordpress_max_memory_limit'] ?? null ),
			'PHP ini memory_limit'                => $this->display_string( $memory['php_memory_limit'] ?? null ),
			'Current memory usage (bytes)'        => $this->display_string( $memory['current_usage_bytes'] ?? null ),
			'Peak memory usage (bytes)'           => $this->display_string( $memory['peak_usage_bytes'] ?? null ),
			'WordPress admin can raise memory'    => true === $memory['wordpress_admin_can_raise'] ? 'YES' : 'NO',
			'Actual available memory (bytes)'     => $this->display_string( $memory['actual_available_bytes'] ?? null ),
			'Memory preflight'                    => $this->display_string( $memory['status'] ?? null ),
			'Post-reactivation persistence check' => $this->display_string( $snapshot['post_reactivation_persistence'] ?? null ),
		);
	}

	/**
	 * @param array<string,mixed> $snapshot Health snapshot.
	 *
	 * @return array<string,string>
	 */
	private function table_rows( array $snapshot ): array {
		$rows = array();
		/** @var array<string,array{name:string,exists:bool}> $tables */
		$tables = $snapshot['tables'];
		foreach ( $tables as $table ) {
			$rows[ $table['name'] ] = $table['exists'] ? 'PASS' : 'MISSING';
		}

		return $rows;
	}

	private function authorize_view(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'atal-seo-factory-core' ) );
		}
	}

	private function authorize_action( string $nonce_action ): void {
		$this->authorize_view();
		if ( false === check_admin_referer( $nonce_action ) ) {
			wp_die( esc_html__( 'The acceptance request could not be verified.', 'atal-seo-factory-core' ) );
		}
	}

	private function download_url(): string {
		$url = add_query_arg( 'action', self::DOWNLOAD_ACTION, admin_url( 'admin-post.php' ) );

		return wp_nonce_url( $url, self::DOWNLOAD_NONCE );
	}

	private function nonce_field( string $action ): void {
		$field = wp_nonce_field( $action, '_wpnonce', true, false );
		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress creates this nonce input markup.
	}

	private function display_string( mixed $value ): string {
		if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		return is_bool( $value ) ? ( $value ? 'YES' : 'NO' ) : '';
	}
}
