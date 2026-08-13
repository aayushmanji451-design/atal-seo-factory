<?php
/** WordPress receiver bootstrap. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver;

use Atal\DiplomaReceiver\Infrastructure\WordPress\ServiceFactory;
use Throwable;
final class Bootstrap {
	public static function activate(): void {
		try {
			ServiceFactory::activator()->activate();
		} catch ( Throwable $throwable ) {
			wp_die( esc_html( 'Atal Diploma Receiver activation failed safely: ' . $throwable->getMessage() ) ); } }
	public static function deactivate(): void {
		ServiceFactory::deactivator()->deactivate(); }
	public static function boot(): void {
		ServiceFactory::plugin()->boot(); }
	private function __construct() {}
}
