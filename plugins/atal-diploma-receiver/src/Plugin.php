<?php
/** Lightweight receiver hooks. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver;

use Atal\DiplomaReceiver\Admin\HealthPage;
use Atal\DiplomaReceiver\Rest\ReceiverController;
use Closure;
final class Plugin {
	public const VERSION = '0.4.1-dev';
	/**
	 * @param Closure():ReceiverController $controller_factory REST controller factory.
	 * @param Closure():HealthPage         $health_factory     Health page factory.
	 */
	public function __construct( private readonly Closure $controller_factory, private readonly Closure $health_factory ) {}
	public function boot(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_menu', array( $this, 'register_health' ) );
		if ( self::is_development_build() ) {
			add_action( 'admin_post_' . HealthPage::RUN_ACTION, array( $this, 'run_acceptance' ) );
			add_action( 'admin_post_' . HealthPage::DOWNLOAD_ACTION, array( $this, 'download_report' ) );
			add_action( 'admin_post_' . HealthPage::CONFIGURE_HMAC_ACTION, array( $this, 'configure_hmac' ) );
		}
	}
	public static function is_development_build(): bool {
		return str_ends_with( self::VERSION, '-dev' ); }
	public function register_routes(): void {
		$factory = $this->controller_factory;
		$factory()->register(); }
	public function register_health(): void {
		$this->health_page()->register(); }
	public function run_acceptance(): void {
		$this->health_page()->run_acceptance(); }
	public function download_report(): void {
		$this->health_page()->download_report(); }
	public function configure_hmac(): void {
		$this->health_page()->configure_hmac(); }
	private function health_page(): HealthPage {
		$factory = $this->health_factory;
		return $factory(); }
}
