<?php
/** Admin-only Task 04 canary controls. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Admin;

use Atal\Contracts\Value\TargetSite;
use Atal\SeoFactory\Application\Canary\CanaryCoordinator;
use Atal\SeoFactory\Application\Canary\CanaryJsonImporter;
use Atal\SeoFactory\Config\Identifiers;
use Throwable;

final class CanaryPanel {
	public const RUN_INSTITUTE_ACTION  = 'atal_seo_factory_task04_run_institute';
	public const RUN_DIPLOMA_ACTION    = 'atal_seo_factory_task04_run_diploma';
	public const VERIFY_ACTION         = 'atal_seo_factory_task04_verify';
	public const ROLLBACK_ACTION       = 'atal_seo_factory_task04_rollback';
	public const CONFIGURE_HMAC_ACTION = 'atal_seo_factory_task04_configure_hmac';
	private const NONCE                = 'atal_seo_factory_task04_canary';

	public function __construct( private readonly CanaryCoordinator $coordinator, private readonly CanaryJsonImporter $importer ) {}

	public function render(): void {
		$latest = get_option( Identifiers::OPTION_CANARY_REPORT, null );
		?>
		<hr><h2><?php echo esc_html__( 'Task 04 Canary (CANARY/DEVELOPMENT)', 'atal-seo-factory-core' ); ?></h2>
		<p><?php echo esc_html__( 'Each run imports exactly one strict JSON request and creates one draft. Replace featured_image_id with an existing image attachment ID on the target staging site.', 'atal-seo-factory-core' ); ?></p>
		<form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0"><input type="hidden" name="action" value="<?php echo esc_html( self::CONFIGURE_HMAC_ACTION ); ?>"><?php echo wp_nonce_field( self::NONCE, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress nonce markup. ?><label><?php echo esc_html__( 'Task 04 Diploma HMAC secret', 'atal-seo-factory-core' ); ?> <input type="password" name="task04_hmac_secret" minlength="64" maxlength="64" autocomplete="new-password" required></label> <?php submit_button( esc_html__( 'Save development HMAC secret', 'atal-seo-factory-core' ), 'secondary', 'submit', false ); ?></form>
		<?php $this->run_form( self::RUN_INSTITUTE_ACTION, 'Run Institute Canary', $this->importer->template( TargetSite::INSTITUTE ) ); ?>
		<?php $this->run_form( self::RUN_DIPLOMA_ACTION, 'Run Diploma Canary', $this->importer->template( TargetSite::DIPLOMA ) ); ?>
		<div style="display:flex;gap:8px;margin-top:12px"><?php $this->simple_form( self::VERIFY_ACTION, 'Verify Canary' ); ?><?php $this->simple_form( self::ROLLBACK_ACTION, 'Roll Back Canary' ); ?></div>
		<?php if ( is_array( $latest ) ) : ?>
			<h3><?php echo esc_html__( 'Latest Task 04 result', 'atal-seo-factory-core' ); ?></h3>
			<pre><?php echo esc_html( (string) wp_json_encode( $latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
		<?php endif; ?>
		<?php
	}

	public function run_institute(): void {
		$this->execute( fn(): array => $this->coordinator->run_institute( $this->json_input() ) ); }
	public function run_diploma(): void {
		$this->execute( fn(): array => $this->coordinator->run_diploma( $this->json_input() ) ); }
	public function verify(): void {
		$this->execute( fn(): array => $this->coordinator->verify() ); }
	public function rollback(): void {
		$this->execute( fn(): array => $this->coordinator->rollback() ); }
	public function configure_hmac(): void {
		$this->authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; exact hexadecimal validation follows.
		$secret = isset( $_POST['task04_hmac_secret'] ) && is_string( $_POST['task04_hmac_secret'] ) ? wp_unslash( $_POST['task04_hmac_secret'] ) : '';
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $secret ) ) {
			wp_die( esc_html__( 'Task 04 HMAC secret must be exactly 64 lowercase hexadecimal characters.', 'atal-seo-factory-core' ) );
		}
		update_option( Identifiers::OPTION_DIPLOMA_HMAC_SECRET, $secret, false );
		update_option(
			Identifiers::OPTION_CANARY_REPORT,
			array(
				'status'                   => 'PASS',
				'hmac_configured'          => true,
				'live_domain_access_count' => 0,
			),
			false
		);
		$this->redirect();
	}

	private function run_form( string $action, string $label, string $template ): void {
		?>
		<form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0"><input type="hidden" name="action" value="<?php echo esc_html( $action ); ?>"><?php echo wp_nonce_field( self::NONCE, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress nonce markup. ?><textarea name="canary_json" rows="10" class="large-text code"><?php echo esc_textarea( $template ); ?></textarea><?php submit_button( esc_html( $label ), 'secondary', 'submit', false ); ?></form>
		<?php
	}

	private function simple_form( string $action, string $label ): void {
		?>
		<form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_html( $action ); ?>"><?php echo wp_nonce_field( self::NONCE, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress nonce markup. ?><?php submit_button( esc_html( $label ), 'secondary', 'submit', false ); ?></form>
		<?php
	}

	/** @param callable():array<string,mixed> $operation */
	private function execute( callable $operation ): void {
		$this->authorize();
		try {
			$report = $operation();
		} catch ( Throwable $throwable ) {
			$message = preg_replace( '/(?:password|secret|token|authorization|signature)\s*[:=]\s*\S+/i', '[redacted]', $throwable->getMessage() );
			$report  = array(
				'status'                   => 'FAIL',
				'error'                    => is_string( $message ) ? $message : 'The canary failed safely.',
				'exception'                => $throwable::class,
				'live_domain_access_count' => 0,
			);
		}
		update_option( Identifiers::OPTION_CANARY_REPORT, $report, false );
		$this->redirect();
	}

	private function redirect(): void {
		$url = add_query_arg( 'page', HealthPage::PAGE_SLUG, admin_url( 'tools.php' ) );
		if ( ! wp_safe_redirect( $url ) ) {
			wp_die( esc_html__( 'Unable to return to the Task 04 canary page.', 'atal-seo-factory-core' ) );
		}
		exit;
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run a canary.', 'atal-seo-factory-core' ) );
		}
		if ( false === check_admin_referer( self::NONCE ) ) {
			wp_die( esc_html__( 'The Task 04 canary request could not be verified.', 'atal-seo-factory-core' ) );
		}
	}

	private function json_input(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked before strict JSON decoding; sanitizing would corrupt JSON.
		$value = isset( $_POST['canary_json'] ) && is_string( $_POST['canary_json'] ) ? wp_unslash( $_POST['canary_json'] ) : '';
		return $value;
	}
}
