<?php
/** Admin-only native SEO and local image acceptance controls. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Admin;

use Atal\SeoFactory\Config\Identifiers;
use Atal\SeoFactory\Infrastructure\WordPress\SeoImages\DiplomaTask05Client;
use Atal\SeoImages\Application\AcceptanceCoordinator;
use Throwable;

final class Task05Panel {
	public const RUN_INSTITUTE_ACTION = 'atal_seo_factory_task05_run_institute';
	public const RUN_DIPLOMA_ACTION   = 'atal_seo_factory_task05_run_diploma';
	public const VERIFY_ACTION        = 'atal_seo_factory_task05_verify';
	public const ROLLBACK_ACTION      = 'atal_seo_factory_task05_rollback';
	public const DOWNLOAD_ACTION      = 'atal_seo_factory_task05_download';
	private const NONCE               = 'atal_seo_factory_task05_acceptance';

	public function __construct( private readonly AcceptanceCoordinator $institute, private readonly DiplomaTask05Client $diploma ) {}
	public function render(): void {
		$latest = get_option( Identifiers::OPTION_TASK05_REPORT, null );
		?>
		<hr><h2><?php echo esc_html__( 'Task 05 — Native SEO and Image Acceptance', 'atal-seo-factory-core' ); ?></h2>
		<p><?php echo esc_html__( 'Development/staging only. Operates only on Institute draft 41 and Diploma draft 5704, uses local WebP rendering, and makes no paid API request.', 'atal-seo-factory-core' ); ?></p>
		<div style="display:flex;gap:8px;flex-wrap:wrap"><?php $this->form( self::RUN_INSTITUTE_ACTION, 'Run Institute SEO/Image Acceptance' ); ?><?php $this->form( self::RUN_DIPLOMA_ACTION, 'Run Diploma SEO/Image Acceptance' ); ?><?php $this->form( self::VERIFY_ACTION, 'Verify Task 05' ); ?><?php $this->form( self::ROLLBACK_ACTION, 'Roll Back Task 05' ); ?></div>
		<?php
		if ( is_array( $latest ) ) :
			?>
			<p><a class="button" href="<?php echo esc_html( $this->download_url() ); ?>"><?php echo esc_html__( 'Download JSON Report', 'atal-seo-factory-core' ); ?></a></p><pre><?php echo esc_html( (string) wp_json_encode( $latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre><?php endif; ?>
		<?php
	}
	public function run_institute(): void {
		$this->single( 'institute', fn(): array => $this->institute->run() ); }
	public function run_diploma(): void {
		$this->single( 'diploma', fn(): array => $this->diploma->run() ); }
	public function verify(): void {
		$this->combined( 'verify', fn(): array => $this->institute->verify(), fn(): array => $this->diploma->verify() ); }
	public function rollback(): void {
		$this->combined( 'rollback', fn(): array => $this->institute->rollback(), fn(): array => $this->diploma->rollback() ); }
	public function download(): void {
		$this->authorize();
		$report = get_option( Identifiers::OPTION_TASK05_REPORT, null );
		if ( ! is_array( $report ) ) {
			wp_die( esc_html__( 'No Task 05 report is available.', 'atal-seo-factory-core' ) );
		} nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="atal-task-05-native-seo-images-report.json"' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Capability- and nonce-protected JSON download.
		exit; }
	/** @param callable():array<string,mixed> $operation */
	private function single( string $site, callable $operation ): void {
		$this->authorize();
		$report = $this->latest();
		try {
			$report[ $site ]       = $operation();
			$report['status']      = 'PASS';
			$report['last_action'] = 'run_' . $site;
		} catch ( Throwable $throwable ) {
			$report[ $site ]  = $this->failure( $throwable );
			$report['status'] = 'FAIL';
		} $this->save( $report ); }
	/** @param callable():array<string,mixed> $institute @param callable():array<string,mixed> $diploma */
	private function combined( string $action, callable $institute, callable $diploma ): void {
		$this->authorize();
		$report = $this->latest();
		try {
			$report['institute']   = $institute();
			$report['diploma']     = $diploma();
			$report['status']      = 'PASS';
			$report['last_action'] = $action;
		} catch ( Throwable $throwable ) {
			$report['status'] = 'FAIL';
			$report['error']  = $this->safe_message( $throwable );
		} $this->save( $report ); }
	/** @return array<string,mixed> */ private function latest(): array {
		$value  = get_option( Identifiers::OPTION_TASK05_REPORT, array() );
		$report = array();
		if ( is_array( $value ) && ! array_is_list( $value ) ) {
			foreach ( $value as $key => $item ) {
				if ( is_string( $key ) ) {
					$report[ $key ] = $item;
				}
			}
		} $report['report_version']          = '1.0.0';
		$report['scope']                     = 'task-05-combined-staging-acceptance';
		$report['paid_api_request_count']    = 0;
		$report['live_domain_request_count'] = 0;
		return $report; }
	/** @param array<string,mixed> $report */ private function save( array $report ): void {
		update_option( Identifiers::OPTION_TASK05_REPORT, $report, false );
		$this->redirect(); }
	/** @return array<string,mixed> */ private function failure( Throwable $throwable ): array {
		return array(
			'status'                    => 'FAIL',
			'error'                     => $this->safe_message( $throwable ),
			'paid_api_request_count'    => 0,
			'live_domain_request_count' => 0,
		); }
	private function safe_message( Throwable $throwable ): string {
		$value = preg_replace( '/(?:password|secret|token|authorization|signature)\s*[:=]\s*\S+/i', '[redacted]', $throwable->getMessage() );
		return is_string( $value ) && '' !== $value ? $value : 'Task 05 failed safely.'; }
	private function form( string $action, string $label ): void {

		?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>"><?php echo wp_nonce_field( self::NONCE, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress nonce markup. ?><?php submit_button( esc_html( $label ), 'secondary', 'submit', false ); ?></form>
		<?php
	}
	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run Task 05.', 'atal-seo-factory-core' ) );
		} if ( false === check_admin_referer( self::NONCE ) ) {
			wp_die( esc_html__( 'The Task 05 request could not be verified.', 'atal-seo-factory-core' ) ); } }
	private function redirect(): void {
		$url = add_query_arg( 'page', HealthPage::PAGE_SLUG, admin_url( 'tools.php' ) );
		if ( ! wp_safe_redirect( $url ) ) {
			wp_die( esc_html__( 'Unable to return to Task 05.', 'atal-seo-factory-core' ) );
		} exit; }
	private function download_url(): string {
		return wp_nonce_url( add_query_arg( 'action', self::DOWNLOAD_ACTION, admin_url( 'admin-post.php' ) ), self::NONCE ); }
}
