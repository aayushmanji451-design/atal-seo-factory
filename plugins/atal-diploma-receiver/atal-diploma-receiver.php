<?php
/**
 * Plugin Name: Atal Diploma Receiver
 * Plugin URI: https://ataldiploma.com/
 * Description: Secure, staging-first receiver for authenticated Atal Diploma draft payloads.
 * Version: 0.3.0-dev
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author: ATAL Institute
 * Text Domain: atal-diploma-receiver
 *
 * @package AtalDiplomaReceiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefixes = array(
			'Atal\\DiplomaReceiver\\' => __DIR__ . '/src/',
			'Atal\\Contracts\\'       => __DIR__ . '/dependencies/contracts/',
		);

		foreach ( $prefixes as $prefix => $directory ) {
			if ( ! str_starts_with( $class_name, $prefix ) ) {
				continue;
			}
			$relative = substr( $class_name, strlen( $prefix ) );
			$path     = $directory . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}
);

register_activation_hook( __FILE__, array( Atal\DiplomaReceiver\Bootstrap::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Atal\DiplomaReceiver\Bootstrap::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( Atal\DiplomaReceiver\Bootstrap::class, 'boot' ) );
