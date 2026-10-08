<?php
/**
 * Task 07 background queue and daily automation admin panel.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Admin;

use Atal\Queue\Application\DailyBatchCoordinator;
use Atal\Queue\Application\QueueWorker;
use Atal\Queue\Contract\BatchRepositoryInterface;
use Atal\Queue\Contract\JobRepositoryInterface;
use Atal\Queue\Contract\QueueControlStoreInterface;
use Atal\Queue\Domain\DailyBatch;
use Atal\Queue\Domain\QueueConfig;
use Atal\Queue\Domain\QueueControlState;
use Atal\SeoFactory\Config\Identifiers;
use Throwable;

/**
 * Admin controls for background queue, daily approval, full auto mode, and safety switches.
 */
final class Task07Panel {

	public const SAVE_CONFIG_ACTION     = 'atal_seo_factory_task_07_save_config';
	public const PREPARE_BATCH_ACTION   = 'atal_seo_factory_task_07_prepare_batch';
	public const APPROVE_BATCH_ACTION   = 'atal_seo_factory_task_07_approve_batch';
	public const SKIP_BATCH_ACTION      = 'atal_seo_factory_task_07_skip_batch';
	public const PAUSE_ACTION           = 'atal_seo_factory_task_07_pause';
	public const RESUME_ACTION          = 'atal_seo_factory_task_07_resume';
	public const EMERGENCY_STOP_ACTION  = 'atal_seo_factory_task_07_emergency_stop';
	public const RESET_EMERGENCY_ACTION = 'atal_seo_factory_task_07_reset_emergency';
	public const RUN_WORKER_STEP_ACTION = 'atal_seo_factory_task_07_run_worker_step';

	private const NONCE_ACTION = 'atal_seo_factory_task_07';

	public function __construct(
		private readonly DailyBatchCoordinator $coordinator,
		private readonly QueueWorker $worker,
		private readonly QueueControlStoreInterface $controls,
		private readonly BatchRepositoryInterface $batches,
		private readonly JobRepositoryInterface $jobs
	) {
	}

	public function render(): void {
		$state       = $this->controls->get_state();
		$config      = $this->controls->get_config();
		$today       = gmdate( 'Y-m-d' );
		$batch_key   = DailyBatch::compute_key( $today );
		$today_batch = $this->batches->find( $batch_key );
		$job_counts  = $this->jobs->count_by_state();
		$latest_rep  = get_option( Identifiers::OPTION_TASK07_REPORT, null );
		?>
		<hr>
		<h2><?php echo esc_html__( 'Task 07 Background Queue & Daily Automation', 'atal-seo-factory-core' ); ?></h2>
		<p><?php echo esc_html__( 'Manages configurable 25+25 daily automation, Daily Approval / Full Auto modes, pause/resume, and emergency stop.', 'atal-seo-factory-core' ); ?></p>

		<!-- Control State Banner -->
		<table class="widefat striped" style="margin-bottom:16px;">
			<tbody>
				<tr>
					<th scope="row" style="width:220px;"><?php echo esc_html__( 'Queue Control State', 'atal-seo-factory-core' ); ?></th>
					<td>
						<?php if ( QueueControlState::RUNNING === $state ) : ?>
							<strong style="color:#007017;">[ RUNNING ]</strong> <?php echo esc_html__( 'Queue processes jobs normally.', 'atal-seo-factory-core' ); ?>
						<?php elseif ( QueueControlState::PAUSED === $state ) : ?>
							<strong style="color:#d9822b;">[ PAUSED ]</strong> <?php echo esc_html__( 'New jobs will not be claimed. Running jobs complete or defer.', 'atal-seo-factory-core' ); ?>
						<?php else : ?>
							<strong style="color:#d63638;">[ EMERGENCY STOP ]</strong> <?php echo esc_html__( 'All queue worker loops are halted immediately.', 'atal-seo-factory-core' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Current Operating Mode', 'atal-seo-factory-core' ); ?></th>
					<td>
						<strong><?php echo esc_html( strtoupper( str_replace( '_', ' ', $config->mode ) ) ); ?></strong>
						(<?php echo QueueConfig::MODE_DAILY_APPROVAL === $config->mode ? esc_html__( 'Prepares batch automatically; owner gives 1 approval to release staggered queue', 'atal-seo-factory-core' ) : esc_html__( 'Fully automated batch preparation, validation, and scheduled publishing', 'atal-seo-factory-core' ); ?>)
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Configured Daily Limits', 'atal-seo-factory-core' ); ?></th>
					<td>
						<?php
						printf(
							'ATAL Institute: <strong>%d</strong> posts/day | Atal Diploma: <strong>%d</strong> posts/day | Total: <strong>%d</strong>/day (Gen Cap: %d, Pub Cap: %d)',
							(int) $config->institute_daily_limit,
							(int) $config->diploma_daily_limit,
							(int) ( $config->institute_daily_limit + $config->diploma_daily_limit ),
							(int) $config->generation_daily_limit,
							(int) $config->publishing_daily_limit
						);
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Action Buttons Toolbar -->
		<div style="margin-bottom:20px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
			<?php if ( QueueControlState::RUNNING === $state ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::PAUSE_ACTION ); ?>">
					<?php $this->nonce_field(); ?>
					<?php submit_button( esc_html__( 'Pause Queue', 'atal-seo-factory-core' ), 'secondary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::EMERGENCY_STOP_ACTION ); ?>">
					<?php $this->nonce_field(); ?>
					<?php submit_button( esc_html__( 'Emergency Stop', 'atal-seo-factory-core' ), 'delete', 'submit', false ); ?>
				</form>
			<?php elseif ( QueueControlState::PAUSED === $state ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::RESUME_ACTION ); ?>">
					<?php $this->nonce_field(); ?>
					<?php submit_button( esc_html__( 'Resume Queue', 'atal-seo-factory-core' ), 'primary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::EMERGENCY_STOP_ACTION ); ?>">
					<?php $this->nonce_field(); ?>
					<?php submit_button( esc_html__( 'Emergency Stop', 'atal-seo-factory-core' ), 'delete', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::RESET_EMERGENCY_ACTION ); ?>">
					<?php $this->nonce_field(); ?>
					<?php submit_button( esc_html__( 'Reset Emergency (To Paused)', 'atal-seo-factory-core' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::PREPARE_BATCH_ACTION ); ?>">
				<?php $this->nonce_field(); ?>
				<?php
				/* translators: %s: Current UTC date */
				$btn_label = sprintf( esc_html__( 'Prepare Today\'s Batch (%s)', 'atal-seo-factory-core' ), esc_html( $today ) );
				submit_button( $btn_label, 'secondary', 'submit', false );
				?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::RUN_WORKER_STEP_ACTION ); ?>">
				<?php $this->nonce_field(); ?>
				<?php submit_button( esc_html__( 'Run Bounded Worker Step', 'atal-seo-factory-core' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>

		<!-- Today's Batch Card -->
		<?php
		/* translators: %s: Current UTC date */
		$summary_heading = sprintf( esc_html__( 'Today\'s Batch Summary (%s)', 'atal-seo-factory-core' ), $today );
		?>
		<h3><?php echo esc_html( $summary_heading ); ?></h3>
		<?php if ( null !== $today_batch ) : ?>
			<?php $summary = $today_batch->summary(); ?>
			<table class="widefat striped" style="margin-bottom:16px;">
				<tbody>
					<tr><th scope="row" style="width:220px;"><?php echo esc_html__( 'Batch Status', 'atal-seo-factory-core' ); ?></th><td><strong><?php echo esc_html( strtoupper( $today_batch->status ) ); ?></strong></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Candidates Prepared', 'atal-seo-factory-core' ); ?></th><td><?php echo esc_html( (string) $summary['total'] ); ?> (Institute: <?php echo esc_html( (string) $today_batch->institute_limit ); ?>, Diploma: <?php echo esc_html( (string) $today_batch->diploma_limit ); ?>)</td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Passed Quality Gates', 'atal-seo-factory-core' ); ?></th><td><strong style="color:#007017;"><?php echo esc_html( (string) $summary['valid'] ); ?></strong></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Blocked / Isolated Items', 'atal-seo-factory-core' ); ?></th><td><span style="color:#d63638;"><?php echo esc_html( (string) $summary['blocked'] ); ?></span> (Isolated; does not block remaining candidates)</td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Execution Progress', 'atal-seo-factory-core' ); ?></th><td>Published: <?php echo esc_html( (string) $summary['published'] ); ?> | Failed: <?php echo esc_html( (string) $summary['failed'] ); ?></td></tr>
				</tbody>
			</table>

			<?php if ( DailyBatch::STATUS_AWAITING_APPROVAL === $today_batch->status ) : ?>
				<div style="background:#fff8e5;border-left:4px solid #dba617;padding:12px 16px;margin-bottom:16px;">
					<h4 style="margin:0 0 8px 0;"><?php echo esc_html__( 'Owner Approval Required for Today\'s Batch', 'atal-seo-factory-core' ); ?></h4>
					<p style="margin:0 0 12px 0;"><?php echo esc_html__( 'The daily batch has been prepared and all canonical quality gates were verified. One approval will queue valid posts staggered throughout the day.', 'atal-seo-factory-core' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::APPROVE_BATCH_ACTION ); ?>">
						<input type="hidden" name="batch_key" value="<?php echo esc_attr( $batch_key ); ?>">
						<?php $this->nonce_field(); ?>
						<?php submit_button( esc_html__( 'Approve Today\'s Batch', 'atal-seo-factory-core' ), 'primary', 'submit', false ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::SKIP_BATCH_ACTION ); ?>">
						<input type="hidden" name="batch_key" value="<?php echo esc_attr( $batch_key ); ?>">
						<?php $this->nonce_field(); ?>
						<?php submit_button( esc_html__( 'Skip Today\'s Batch', 'atal-seo-factory-core' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<?php
			/* translators: %s: Current UTC date */
			$empty_notice = sprintf( esc_html__( 'No batch prepared yet for today (%s). Click \'Prepare Today\'s Batch\' to generate and validate candidate posts.', 'atal-seo-factory-core' ), $today );
			?>
			<p><em><?php echo esc_html( $empty_notice ); ?></em></p>
		<?php endif; ?>

		<!-- Configuration Form -->
		<h3><?php echo esc_html__( 'Queue & Automation Configuration', 'atal-seo-factory-core' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:600px;margin-bottom:20px;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_CONFIG_ACTION ); ?>">
			<?php $this->nonce_field(); ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="atal-mode"><?php echo esc_html__( 'Operating Mode', 'atal-seo-factory-core' ); ?></label></th>
						<td>
							<select id="atal-mode" name="mode" class="regular-text">
								<option value="<?php echo esc_attr( QueueConfig::MODE_DAILY_APPROVAL ); ?>" <?php echo QueueConfig::MODE_DAILY_APPROVAL === $config->mode ? 'selected' : ''; ?>><?php echo esc_html__( 'Daily Approval Mode (1 Daily Owner Approval)', 'atal-seo-factory-core' ); ?></option>
								<option value="<?php echo esc_attr( QueueConfig::MODE_FULL_AUTO ); ?>" <?php echo QueueConfig::MODE_FULL_AUTO === $config->mode ? 'selected' : ''; ?>><?php echo esc_html__( 'Full Auto Mode (Fully Automated)', 'atal-seo-factory-core' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="atal-institute-limit"><?php echo esc_html__( 'ATAL Institute Daily Target', 'atal-seo-factory-core' ); ?></label></th>
						<td><input id="atal-institute-limit" name="institute_limit" type="number" min="1" max="100" value="<?php echo esc_attr( (string) $config->institute_daily_limit ); ?>" class="small-text"> posts/day</td>
					</tr>
					<tr>
						<th scope="row"><label for="atal-diploma-limit"><?php echo esc_html__( 'Atal Diploma Daily Target', 'atal-seo-factory-core' ); ?></label></th>
						<td><input id="atal-diploma-limit" name="diploma_limit" type="number" min="1" max="100" value="<?php echo esc_attr( (string) $config->diploma_daily_limit ); ?>" class="small-text"> posts/day</td>
					</tr>
					<tr>
						<th scope="row"><label for="atal-gen-limit"><?php echo esc_html__( 'Daily Generation Quota', 'atal-seo-factory-core' ); ?></label></th>
						<td><input id="atal-gen-limit" name="generation_limit" type="number" min="1" max="200" value="<?php echo esc_attr( (string) $config->generation_daily_limit ); ?>" class="small-text"> posts/day</td>
					</tr>
					<tr>
						<th scope="row"><label for="atal-pub-limit"><?php echo esc_html__( 'Daily Publishing Quota', 'atal-seo-factory-core' ); ?></label></th>
						<td><input id="atal-pub-limit" name="publishing_limit" type="number" min="1" max="200" value="<?php echo esc_attr( (string) $config->publishing_daily_limit ); ?>" class="small-text"> posts/day</td>
					</tr>
					<tr>
						<th scope="row"><label for="atal-stagger"><?php echo esc_html__( 'Stagger Interval (seconds)', 'atal-seo-factory-core' ); ?></label></th>
						<td><input id="atal-stagger" name="stagger_interval" type="number" min="60" max="7200" value="<?php echo esc_attr( (string) $config->stagger_interval_seconds ); ?>" class="small-text"> seconds between posts</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( esc_html__( 'Save Queue Configuration', 'atal-seo-factory-core' ), 'primary', 'submit', false ); ?>
		</form>

		<!-- Queue State Metrics -->
		<h3><?php echo esc_html__( 'Active Queue Jobs', 'atal-seo-factory-core' ); ?></h3>
		<p>
			Pending: <strong><?php echo esc_html( (string) ( $job_counts['pending'] ?? 0 ) ); ?></strong> |
			Running: <strong><?php echo esc_html( (string) ( $job_counts['running'] ?? 0 ) ); ?></strong> |
			Waiting Retry: <strong><?php echo esc_html( (string) ( $job_counts['waiting_retry'] ?? 0 ) ); ?></strong> |
			Succeeded: <strong><?php echo esc_html( (string) ( $job_counts['succeeded'] ?? 0 ) ); ?></strong> |
			Dead: <strong><?php echo esc_html( (string) ( $job_counts['dead'] ?? 0 ) ); ?></strong> |
			Cancelled: <strong><?php echo esc_html( (string) ( $job_counts['cancelled'] ?? 0 ) ); ?></strong>
		</p>

		<?php if ( is_array( $latest_rep ) ) : ?>
			<h3><?php echo esc_html__( 'Latest Task 07 Operation Report', 'atal-seo-factory-core' ); ?></h3>
			<pre><?php echo esc_html( (string) wp_json_encode( $latest_rep, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
		<?php endif; ?>
		<?php
	}

	public function save_config(): void {
		$this->authorize();
		$mode             = $this->posted_scalar( 'mode' );
		$institute_limit  = max( 1, (int) $this->posted_scalar( 'institute_limit' ) );
		$diploma_limit    = max( 1, (int) $this->posted_scalar( 'diploma_limit' ) );
		$generation_limit = max( 1, (int) $this->posted_scalar( 'generation_limit' ) );
		$publishing_limit = max( 1, (int) $this->posted_scalar( 'publishing_limit' ) );
		$stagger_interval = max( 60, (int) $this->posted_scalar( 'stagger_interval' ) );

		if ( ! in_array( $mode, array( QueueConfig::MODE_DAILY_APPROVAL, QueueConfig::MODE_FULL_AUTO ), true ) ) {
			$mode = QueueConfig::MODE_DAILY_APPROVAL;
		}

		$config = new QueueConfig(
			$institute_limit,
			$diploma_limit,
			$generation_limit,
			$publishing_limit,
			$mode,
			$stagger_interval,
			5,
			300
		);

		$this->controls->set_config( $config );

		$this->record_report(
			array(
				'action' => 'SAVE_CONFIG',
				'config' => $config->to_array(),
			)
		);
		$this->redirect();
	}

	public function prepare_batch(): void {
		$this->authorize();
		$today = gmdate( 'Y-m-d' );
		$batch = $this->coordinator->prepare_daily_batch( $today );

		$this->record_report(
			array(
				'action' => 'PREPARE_BATCH',
				'batch'  => $batch->to_array(),
			)
		);
		$this->redirect();
	}

	public function approve_batch(): void {
		$this->authorize();
		$batch_key = $this->posted_scalar( 'batch_key' );
		if ( '' === $batch_key ) {
			$batch_key = DailyBatch::compute_key( gmdate( 'Y-m-d' ) );
		}

		try {
			$batch = $this->coordinator->approve_batch( $batch_key );
			$this->record_report(
				array(
					'action' => 'APPROVE_BATCH',
					'batch'  => $batch->to_array(),
				)
			);
		} catch ( Throwable $throwable ) {
			$this->record_report(
				array(
					'action' => 'APPROVE_BATCH_ERROR',
					'error'  => $throwable->getMessage(),
				)
			);
		}

		$this->redirect();
	}

	public function skip_batch(): void {
		$this->authorize();
		$batch_key = $this->posted_scalar( 'batch_key' );
		if ( '' === $batch_key ) {
			$batch_key = DailyBatch::compute_key( gmdate( 'Y-m-d' ) );
		}

		try {
			$batch = $this->coordinator->skip_batch( $batch_key, 'Skipped via admin dashboard' );
			$this->record_report(
				array(
					'action' => 'SKIP_BATCH',
					'batch'  => $batch->to_array(),
				)
			);
		} catch ( Throwable $throwable ) {
			$this->record_report(
				array(
					'action' => 'SKIP_BATCH_ERROR',
					'error'  => $throwable->getMessage(),
				)
			);
		}

		$this->redirect();
	}

	public function pause(): void {
		$this->authorize();
		$this->controls->set_state( QueueControlState::PAUSED, 'Paused via admin panel' );
		$this->record_report(
			array(
				'action' => 'PAUSE_QUEUE',
				'state'  => QueueControlState::PAUSED,
			)
		);
		$this->redirect();
	}

	public function resume(): void {
		$this->authorize();
		$this->controls->set_state( QueueControlState::RUNNING, 'Resumed via admin panel' );
		$this->record_report(
			array(
				'action' => 'RESUME_QUEUE',
				'state'  => QueueControlState::RUNNING,
			)
		);
		$this->redirect();
	}

	public function emergency_stop(): void {
		$this->authorize();
		$this->controls->set_state( QueueControlState::EMERGENCY_STOP, 'Emergency stop via admin panel' );
		$this->record_report(
			array(
				'action' => 'EMERGENCY_STOP',
				'state'  => QueueControlState::EMERGENCY_STOP,
			)
		);
		$this->redirect();
	}

	public function reset_emergency(): void {
		$this->authorize();
		$this->controls->set_state( QueueControlState::PAUSED, 'Reset emergency to paused' );
		$this->record_report(
			array(
				'action' => 'RESET_EMERGENCY_TO_PAUSED',
				'state'  => QueueControlState::PAUSED,
			)
		);
		$this->redirect();
	}

	public function run_worker_step(): void {
		$this->authorize();
		$summary = $this->worker->run_step( 'admin_manual_worker' );
		$this->record_report(
			array(
				'action'  => 'RUN_WORKER_STEP',
				'summary' => null !== $summary ? $summary->to_array() : null,
			)
		);
		$this->redirect();
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Task 07 queue controls.', 'atal-seo-factory-core' ) );
		}
		if ( false === check_admin_referer( self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'The Task 07 request could not be verified.', 'atal-seo-factory-core' ) );
		}
	}

	private function posted_scalar( string $name ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce is verified in authorize(); scalar sanitized.
		$val = $_POST[ $name ] ?? '';

		return is_string( $val ) ? sanitize_text_field( wp_unslash( $val ) ) : '';
	}

	/**
	 * @param array<string,mixed> $report Report payload.
	 */
	private function record_report( array $report ): void {
		update_option( Identifiers::OPTION_TASK07_REPORT, $report, false );
	}

	private function nonce_field(): void {
		$field = wp_nonce_field( self::NONCE_ACTION, '_wpnonce', true, false );
		echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nonce input generated by WordPress.
	}

	private function redirect(): void {
		$url = add_query_arg(
			array(
				'page'           => HealthPage::PAGE_SLUG,
				'task07_updated' => (string) gmdate( 'U' ),
			),
			admin_url( 'tools.php' )
		);
		if ( ! wp_safe_redirect( $url ) ) {
			wp_die( esc_html__( 'Unable to return to the health page.', 'atal-seo-factory-core' ) );
		}
		if ( ! defined( 'PHPUNIT_COMPOSER_INSTALL' ) && ! defined( '__PHPUNIT_PHAR__' ) ) {
			exit;
		}
	}
}
