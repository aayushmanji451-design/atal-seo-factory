<?php
/**
 * Task 06 deterministic preview and validation panel.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Admin;

use Atal\SeoFactory\Config\Identifiers;
use Atal\Topics\Application\CanonicalTopicPolicy;
use Atal\Topics\Application\DeterministicRotation;
use Atal\Topics\Application\TopicValidator;
use Atal\Topics\Domain\RotationCandidate;
use Atal\Topics\Domain\TopicProposal;
use JsonException;
use Throwable;

/**
 * Runs only bounded, local previews; it cannot publish or call a remote API.
 */
final class Task06Panel {

	public const PREVIEW_ACTION = 'atal_seo_factory_task_06_preview';

	public const VALIDATE_ACTION = 'atal_seo_factory_task_06_validate';

	private const NONCE_ACTION = 'atal_seo_factory_task_06';

	private const MAX_PAYLOAD_BYTES = 100000;

	public function __construct(
		private readonly DeterministicRotation $rotation,
		private readonly CanonicalTopicPolicy $policy,
		private readonly TopicValidator $validator
	) {
	}

	public function render(): void {
		$latest = get_option( Identifiers::OPTION_TASK06_REPORT, null );
		?>
		<hr>
		<h2><?php echo esc_html__( 'Task 06 Deterministic Topics', 'atal-seo-factory-core' ); ?></h2>
		<p><?php echo esc_html__( 'Development-only local preview and quality validation. No post, media, API, scheduler, or publishing action is registered.', 'atal-seo-factory-core' ); ?></p>
		<?php
		foreach ( array(
			'atal_institute' => 'Preview Institute Rotation',
			'atal_diploma'   => 'Preview Diploma Rotation',
		) as $site => $label ) :
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::PREVIEW_ACTION ); ?>">
				<input type="hidden" name="target_site" value="<?php echo esc_attr( $site ); ?>">
				<input type="hidden" name="intent" value="course_overview">
				<?php $this->nonce_field(); ?>
				<?php submit_button( esc_html( $label ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endforeach; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::VALIDATE_ACTION ); ?>">
			<?php $this->nonce_field(); ?>
			<p><label for="atal-task06-payload"><strong><?php echo esc_html__( 'Topic proposal JSON', 'atal-seo-factory-core' ); ?></strong></label></p>
			<textarea id="atal-task06-payload" name="topic_payload" rows="8" class="large-text code"></textarea>
			<?php submit_button( esc_html__( 'Validate Topic Proposal', 'atal-seo-factory-core' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php if ( is_array( $latest ) ) : ?>
			<h3><?php echo esc_html__( 'Latest Task 06 result', 'atal-seo-factory-core' ); ?></h3>
			<pre><?php echo esc_html( (string) wp_json_encode( $latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
		<?php endif; ?>
		<?php
	}

	public function preview(): void {
		$this->authorize();
		$site   = $this->posted_scalar( 'target_site' );
		$intent = $this->posted_scalar( 'intent' );
		if ( ! in_array( $site, array( 'atal_institute', 'atal_diploma' ), true ) || ! $this->policy->intent_exists( $intent ) ) {
			wp_die( esc_html__( 'Invalid Task 06 preview request.', 'atal-seo-factory-core' ) );
		}

		$candidates = array();
		foreach ( $this->policy->courses_for_site( $site ) as $course ) {
			$key = $course['course_key'] ?? null;
			if ( is_string( $key ) ) {
				$candidates[] = new RotationCandidate( $site, $key, 1, $this->policy->blocked_intent_reason( $key, $intent ) );
			}
		}
		$result = $this->rotation->peek( $site, $candidates )->to_array();
		update_option(
			Identifiers::OPTION_TASK06_REPORT,
			array(
				'operation'   => 'PREVIEW_ONLY',
				'target_site' => $site,
				'intent'      => $intent,
				'result'      => $result,
				'writes'      => 0,
			),
			false
		);
		$this->redirect();
	}

	public function validate(): void {
		$this->authorize();
		$raw = $this->posted_scalar( 'topic_payload' );
		if ( strlen( $raw ) > self::MAX_PAYLOAD_BYTES ) {
			wp_die( esc_html__( 'The Task 06 proposal exceeds the bounded payload limit.', 'atal-seo-factory-core' ) );
		}

		try {
			$data = json_decode( $raw, true, 64, JSON_THROW_ON_ERROR );
			if ( ! is_array( $data ) ) {
				throw new JsonException( 'The topic proposal must be a JSON object.' );
			}
			$report = $this->validator->validate( TopicProposal::from_array( $data ) )->to_array();
		} catch ( JsonException $exception ) {
			wp_die( esc_html( 'Invalid topic JSON: ' . $exception->getMessage() ) );
		} catch ( Throwable $throwable ) {
			wp_die( esc_html( 'Topic validation stopped safely: ' . $throwable->getMessage() ) );
		}

		update_option(
			Identifiers::OPTION_TASK06_REPORT,
			array(
				'operation' => 'VALIDATE_ONLY',
				'report'    => $report,
				'writes'    => 0,
			),
			false
		);
		$this->redirect();
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run Task 06 validation.', 'atal-seo-factory-core' ) );
		}
		if ( false === check_admin_referer( self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'The Task 06 request could not be verified.', 'atal-seo-factory-core' ) );
		}
	}

	private function posted_scalar( string $name ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce is checked and scalar data is unslashed before use.
		$value = $_POST[ $name ] ?? '';

		return is_string( $value ) ? wp_unslash( $value ) : '';
	}

	private function nonce_field(): void {
		$field = wp_nonce_field( self::NONCE_ACTION, '_wpnonce', true, false );
		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress creates the nonce input markup.
	}

	private function redirect(): never {
		$url = add_query_arg( 'page', HealthPage::PAGE_SLUG, admin_url( 'tools.php' ) );
		if ( ! wp_safe_redirect( $url ) ) {
			wp_die( esc_html__( 'Unable to return to the health page.', 'atal-seo-factory-core' ) );
		}
		exit;
	}
}
