<?php
/** Receiver health and isolated acceptance page. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Admin;

use Atal\DiplomaReceiver\Application\Acceptance\AcceptanceRunner;
use Atal\DiplomaReceiver\Application\Health\HealthDataProvider;
use Atal\DiplomaReceiver\Config\Identifiers;
final class HealthPage {
	public const PAGE_SLUG             = 'atal-diploma-receiver-health';
	public const RUN_ACTION            = 'atal_diploma_receiver_task_03_acceptance';
	public const DOWNLOAD_ACTION       = 'atal_diploma_receiver_task_03_download';
	public const CONFIGURE_HMAC_ACTION = 'atal_diploma_receiver_task_04_configure_hmac';
	private const RUN_NONCE            = 'atal_diploma_receiver_task_03_run';
	private const DOWNLOAD_NONCE       = 'atal_diploma_receiver_task_03_download';
	private const CONFIGURE_HMAC_NONCE = 'atal_diploma_receiver_task_04_configure_hmac';
	public function __construct( private readonly HealthDataProvider $health, private readonly AcceptanceRunner $acceptance ) {}
	public function register(): void {
		add_management_page( esc_html__( 'Atal Diploma Receiver Health', 'atal-diploma-receiver' ), esc_html__( 'Diploma Receiver Health', 'atal-diploma-receiver' ), 'manage_options', self::PAGE_SLUG, array( $this, 'render' ) ); }
	public function render(): void {
		$this->authorize();
		$snapshot = $this->health->snapshot();
		$report   = get_option( Identifiers::OPTION_ACCEPTANCE_REPORT, null ); ?>
		<div class="wrap"><h1><?php echo esc_html__( 'Atal Diploma Receiver Health', 'atal-diploma-receiver' ); ?></h1><p><?php echo esc_html__( 'Read-only Task 03 staging health. The acceptance fixture is isolated and never creates a WordPress post or page.', 'atal-diploma-receiver' ); ?></p><table class="widefat striped"><tbody>
		<?php
		foreach ( $this->rows( $snapshot ) as $label => $value ) :
			?>
			<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr><?php endforeach; ?></tbody></table><h2><?php echo esc_html__( 'Task 04 HMAC pairing', 'atal-diploma-receiver' ); ?></h2><p><?php echo esc_html__( 'The secret is write-only and is never displayed or logged.', 'atal-diploma-receiver' ); ?></p><form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_html( self::CONFIGURE_HMAC_ACTION ); ?>"><?php echo wp_nonce_field( self::CONFIGURE_HMAC_NONCE, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress-generated nonce markup. ?><label><?php echo esc_html__( 'Task 04 shared secret', 'atal-diploma-receiver' ); ?> <input type="password" name="task04_hmac_secret" minlength="64" maxlength="64" autocomplete="new-password" required></label> <?php submit_button( esc_html__( 'Save development HMAC secret', 'atal-diploma-receiver' ), 'secondary', 'submit', false ); ?></form><h2><?php echo esc_html__( 'Task 03 browser acceptance', 'atal-diploma-receiver' ); ?></h2><form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_html( self::RUN_ACTION ); ?>"><?php echo wp_nonce_field( self::RUN_NONCE, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress-generated nonce markup. ?><?php submit_button( esc_html__( 'Run isolated Task 03 acceptance', 'atal-diploma-receiver' ), 'primary', 'submit', false ); ?></form>
			<?php
			if ( is_array( $report ) ) :
				?>
			<p><strong><?php echo esc_html__( 'Latest result:', 'atal-diploma-receiver' ); ?></strong> <?php echo esc_html( is_string( $report['status'] ?? null ) ? $report['status'] : 'UNKNOWN' ); ?></p><p><a class="button" href="<?php echo esc_html( $this->download_url() ); ?>"><?php echo esc_html__( 'Download JSON report', 'atal-diploma-receiver' ); ?></a></p><?php endif; ?></div>
		<?php
	}
	public function run_acceptance(): void {
		$this->authorize_action( self::RUN_NONCE );
		$report = $this->acceptance->run();
		update_option( Identifiers::OPTION_ACCEPTANCE_REPORT, $report, false );
		$url = add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'tools.php' ) );
		if ( ! wp_safe_redirect( $url ) ) {
			wp_die( esc_html__( 'Unable to return to receiver health.', 'atal-diploma-receiver' ) );
		} exit; }
	public function download_report(): void {
		$this->authorize_action( self::DOWNLOAD_NONCE );
		$report = get_option( Identifiers::OPTION_ACCEPTANCE_REPORT, null );
		if ( ! is_array( $report ) ) {
			wp_die( esc_html__( 'No Task 03 acceptance report exists.', 'atal-diploma-receiver' ) );
		} nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="atal-diploma-receiver-task-03-acceptance.json"' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional authenticated JSON download.
		exit; }
	public function configure_hmac(): void {
		$this->authorize_action( self::CONFIGURE_HMAC_NONCE );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; exact hexadecimal validation follows.
		$secret = isset( $_POST['task04_hmac_secret'] ) && is_string( $_POST['task04_hmac_secret'] ) ? wp_unslash( $_POST['task04_hmac_secret'] ) : '';
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $secret ) ) {
			wp_die( esc_html__( 'Task 04 HMAC secret must be exactly 64 lowercase hexadecimal characters.', 'atal-diploma-receiver' ) );
		}
		update_option( Identifiers::OPTION_HMAC_SECRET, $secret, false );
		$url = add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'tools.php' ) );
		if ( ! wp_safe_redirect( $url ) ) {
			wp_die( esc_html__( 'Unable to return to receiver health.', 'atal-diploma-receiver' ) );
		} exit; }
	/**
	 * @param array<string,mixed> $snapshot Health snapshot.
	 * @return array<string,string>
	 */
	private function rows( array $snapshot ): array {
		$tables       = $snapshot['tables'] ?? array();
		$table_status = is_array( $tables ) && ! in_array( false, $tables, true ) ? 'PASS' : 'MISSING';
		return array(
			'Plugin version'              => $this->string_value( $snapshot['plugin_version'] ?? null ),
			'Plugin slug'                 => $this->string_value( $snapshot['plugin_slug'] ?? null ),
			'REST namespace'              => $this->string_value( $snapshot['rest_namespace'] ?? null ),
			'Site URL'                    => $this->string_value( $snapshot['site_url'] ?? null ),
			'Exact staging hostname'      => true === ( $snapshot['hostname_valid'] ?? false ) ? 'PASS' : 'FAIL',
			'Search indexing disabled'    => true === ( $snapshot['search_indexing_disabled'] ?? false ) ? 'PASS' : 'FAIL',
			'AIOSEO detected'             => true === ( $snapshot['aioseo_detected'] ?? false ) ? 'PASS' : 'FAIL',
			'AIOSEO version'              => $this->string_value( $snapshot['aioseo_version'] ?? null ),
			'Old ATAL connector inactive' => false === ( $snapshot['old_atal_connector_active'] ?? true ) ? 'PASS' : 'FAIL',
			'Receiver tables'             => $table_status,
			'HMAC configured'             => true === ( $snapshot['hmac_configured'] ?? false ) ? 'YES' : 'NO',
			'WP_MEMORY_LIMIT'             => $this->string_value( $snapshot['wp_memory_limit'] ?? null ),
			'PHP memory_limit'            => $this->string_value( $snapshot['php_memory_limit'] ?? null ),
		); }
	private function string_value( mixed $value ): string {
		return is_string( $value ) || is_int( $value ) || is_float( $value ) ? (string) $value : ''; }
	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view receiver health.', 'atal-diploma-receiver' ) ); } }
	private function authorize_action( string $nonce ): void {
		$this->authorize();
		if ( false === check_admin_referer( $nonce ) ) {
			wp_die( esc_html__( 'The receiver acceptance action could not be verified.', 'atal-diploma-receiver' ) ); } }
	private function download_url(): string {
		return wp_nonce_url( add_query_arg( 'action', self::DOWNLOAD_ACTION, admin_url( 'admin-post.php' ) ), self::DOWNLOAD_NONCE ); }
}
