<?php
/**
 * Read-only staging health page.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Admin;

use Atal\SeoFactory\Application\Acceptance\AcceptanceReport;
use Atal\SeoFactory\Application\Acceptance\AcceptanceRunner;
use Atal\SeoFactory\Application\Health\HealthDataProvider;

/**
 * Displays health data and an explicit bounded Task 02 acceptance action.
 */
final class HealthPage {

	/**
	 * Create the read-only page.
	 *
	 * @param HealthDataProvider $health     Health-data provider.
	 * @param AcceptanceRunner   $acceptance Task 02 acceptance runner.
	 */
	public function __construct(
		private readonly HealthDataProvider $health,
		private readonly AcceptanceRunner $acceptance
	) {
	}

	/**
	 * Register the Tools submenu.
	 */
	public function register(): void {
		add_management_page(
			esc_html__( 'ATAL SEO Factory Health', 'atal-seo-factory-core' ),
			esc_html__( 'ATAL SEO Factory Health', 'atal-seo-factory-core' ),
			'manage_options',
			'atal-seo-factory-core-health',
			array( $this, 'render' )
		);
	}

	/**
	 * Render diagnostic values and the nonce-protected acceptance action.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'atal-seo-factory-core' ) );
		}

		$snapshot = $this->health->snapshot();
		$report   = null;
		$method   = filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_UNSAFE_RAW );
		$action   = filter_input( INPUT_POST, 'atal_task_02_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'POST' === $method && 'run' === $action ) {
			if ( false === check_admin_referer( 'atal_seo_factory_task_02_acceptance', 'atal_task_02_nonce' ) ) {
				wp_die( esc_html__( 'Task 02 acceptance nonce verification failed.', 'atal-seo-factory-core' ) );
			}
			$report = $this->acceptance->run();
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'ATAL SEO Factory Core Health', 'atal-seo-factory-core' ); ?></h1>
			<p><?php echo esc_html__( 'Health diagnostics are read-only. The explicit Task 02 acceptance action is bounded to schema verification and canonical knowledge import.', 'atal-seo-factory-core' ); ?></p>
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
			<h2><?php echo esc_html__( 'Task 02 Staging Acceptance', 'atal-seo-factory-core' ); ?></h2>
			<p><?php echo esc_html__( 'Runs the version-1 migration idempotency check, validates and imports bundled canonical knowledge, and verifies that no publishing side effects occur.', 'atal-seo-factory-core' ); ?></p>
			<form method="post">
				<?php echo wp_nonce_field( 'atal_seo_factory_task_02_acceptance', 'atal_task_02_nonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress generates the nonce and referer fields. ?>
				<input type="hidden" name="atal_task_02_action" value="run" />
				<?php submit_button( esc_html__( 'Run Task 02 Acceptance', 'atal-seo-factory-core' ), 'primary', 'submit', false ); ?>
			</form>
			<?php if ( $report instanceof AcceptanceReport ) : ?>
				<?php $this->render_acceptance_report( $report ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Build the summary rows.
	 *
	 * @param array{plugin_version:string,plugin_slug:string,rest_namespace:string,database_version:int,expected_database_version:int,site_url:string,environment_type:string,wordpress_version:string,php_version:string,wordpress_memory_limit:string,wordpress_max_memory_limit:string,php_memory_limit:string,current_memory_usage:int,peak_memory_usage:int,memory_status:string,memory_message:string,tables:array<string,array{name:string,exists:bool}>,read_only:bool} $snapshot Health snapshot.
	 *
	 * @return array<string,string>
	 */
	private function summary_rows( array $snapshot ): array {
		return array(
			'Plugin version'       => $snapshot['plugin_version'],
			'REST namespace'       => $snapshot['rest_namespace'],
			'Database version'     => (string) $snapshot['database_version'],
			'Site URL'             => $snapshot['site_url'],
			'Environment type'     => $snapshot['environment_type'],
			'WordPress version'    => $snapshot['wordpress_version'],
			'PHP version'          => $snapshot['php_version'],
			'WP_MEMORY_LIMIT'      => (string) $snapshot['wordpress_memory_limit'],
			'WP_MAX_MEMORY_LIMIT'  => (string) $snapshot['wordpress_max_memory_limit'],
			'PHP memory_limit'     => (string) $snapshot['php_memory_limit'],
			'Current memory usage' => $this->format_bytes( (int) $snapshot['current_memory_usage'] ),
			'Peak memory usage'    => $this->format_bytes( (int) $snapshot['peak_memory_usage'] ),
			'Memory status'        => (string) $snapshot['memory_status'],
			'Memory guidance'      => (string) $snapshot['memory_message'],
		);
	}

	/**
	 * Build the table status rows.
	 *
	 * @param array{plugin_version:string,plugin_slug:string,rest_namespace:string,database_version:int,expected_database_version:int,site_url:string,environment_type:string,wordpress_version:string,php_version:string,wordpress_memory_limit:string,wordpress_max_memory_limit:string,php_memory_limit:string,current_memory_usage:int,peak_memory_usage:int,memory_status:string,memory_message:string,tables:array<string,array{name:string,exists:bool}>,read_only:bool} $snapshot Health snapshot.
	 *
	 * @return array<string,string>
	 */
	private function table_rows( array $snapshot ): array {
		$rows   = array();
		$tables = $snapshot['tables'];

		foreach ( $tables as $table ) {
			$rows[ $table['name'] ] = true === $table['exists'] ? 'PASS' : 'MISSING';
		}

		return $rows;
	}

	private function render_acceptance_report( AcceptanceReport $report ): void {
		$json = wp_json_encode( $report->to_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			$json = '{"overall_status":"FAIL","message":"Unable to encode acceptance report."}';
		}
		$download = 'data:application/json;charset=utf-8,' . rawurlencode( $json );
		$first    = $report->first_dry_run();
		?>
		<h3><?php echo esc_html__( 'Acceptance result', 'atal-seo-factory-core' ); ?>: <?php echo esc_html( $report->status() ); ?></h3>
		<p><a class="button button-secondary" download="atal-seo-factory-task-02-acceptance.json" href="<?php echo esc_attr( $download ); ?>"><?php echo esc_html__( 'Download JSON acceptance report', 'atal-seo-factory-core' ); ?></a></p>
		<h3><?php echo esc_html__( 'Checks', 'atal-seo-factory-core' ); ?></h3>
		<table class="widefat striped">
			<thead><tr><th><?php echo esc_html__( 'Check', 'atal-seo-factory-core' ); ?></th><th><?php echo esc_html__( 'Status', 'atal-seo-factory-core' ); ?></th><th><?php echo esc_html__( 'Expected', 'atal-seo-factory-core' ); ?></th><th><?php echo esc_html__( 'Actual', 'atal-seo-factory-core' ); ?></th><th><?php echo esc_html__( 'Message', 'atal-seo-factory-core' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $report->checks() as $check ) : ?>
				<?php $row = $check->to_array(); ?>
				<tr><td><?php echo esc_html( $row['check_id'] ); ?></td><td><?php echo esc_html( $row['status'] ); ?></td><td><?php echo esc_html( $row['expected'] ); ?></td><td><?php echo esc_html( $row['actual'] ); ?></td><td><?php echo esc_html( $row['message'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h3><?php echo esc_html__( 'First dry-run planned writes', 'atal-seo-factory-core' ); ?></h3>
		<p><?php echo esc_html( sprintf( 'Inserts: %d; updates: %d; total writes: %d.', $first['inserts'], $first['updates'], $first['writes'] ) ); ?></p>
		<?php
	}

	private function format_bytes( int $bytes ): string {
		return number_format_i18n( $bytes / 1048576, 2 ) . ' MiB';
	}
}
