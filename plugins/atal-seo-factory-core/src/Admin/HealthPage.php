<?php
/**
 * Read-only staging health page.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Admin;

use Atal\SeoFactory\Application\Health\HealthDataProvider;

/**
 * Displays bounded health data under Tools without running maintenance work.
 */
final class HealthPage {

	/**
	 * Create the read-only page.
	 *
	 * @param HealthDataProvider $health Health-data provider.
	 */
	public function __construct( private readonly HealthDataProvider $health ) {
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
	 * Render read-only diagnostic values.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'atal-seo-factory-core' ) );
		}

		$snapshot = $this->health->snapshot();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'ATAL SEO Factory Core Health', 'atal-seo-factory-core' ); ?></h1>
			<p><?php echo esc_html__( 'Read-only staging diagnostics. This page never runs migrations or imports.', 'atal-seo-factory-core' ); ?></p>
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
		</div>
		<?php
	}

	/**
	 * Build the summary rows.
	 *
	 * @param array{plugin_version:string,plugin_slug:string,rest_namespace:string,database_version:int,expected_database_version:int,site_url:string,environment_type:string,wordpress_version:string,php_version:string,wordpress_memory_limit:string,php_memory_limit:string,memory_prerequisite_met:bool,tables:array<string,array{name:string,exists:bool}>,read_only:bool} $snapshot Health snapshot.
	 *
	 * @return array<string,string>
	 */
	private function summary_rows( array $snapshot ): array {
		return array(
			'Plugin version'            => $snapshot['plugin_version'],
			'REST namespace'            => $snapshot['rest_namespace'],
			'Database version'          => (string) $snapshot['database_version'],
			'Site URL'                  => $snapshot['site_url'],
			'Environment type'          => $snapshot['environment_type'],
			'WordPress version'         => $snapshot['wordpress_version'],
			'PHP version'               => $snapshot['php_version'],
			'WP memory limit'           => $snapshot['wordpress_memory_limit'],
			'PHP memory limit'          => $snapshot['php_memory_limit'],
			'256M staging memory ready' => true === $snapshot['memory_prerequisite_met'] ? 'PASS' : 'BLOCKED',
		);
	}

	/**
	 * Build the table status rows.
	 *
	 * @param array{plugin_version:string,plugin_slug:string,rest_namespace:string,database_version:int,expected_database_version:int,site_url:string,environment_type:string,wordpress_version:string,php_version:string,wordpress_memory_limit:string,php_memory_limit:string,memory_prerequisite_met:bool,tables:array<string,array{name:string,exists:bool}>,read_only:bool} $snapshot Health snapshot.
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
}
