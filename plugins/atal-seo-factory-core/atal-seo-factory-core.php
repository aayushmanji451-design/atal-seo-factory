<?php
/**
 * Plugin Name: ATAL SEO Factory Core
 * Plugin URI: https://liveup2.atalinstitute.com/
 * Description: Staging-first storage and canonical knowledge foundation for ATAL Institute.
 * Version: 0.5.0-dev
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author: ATAL Institute
 * Text Domain: atal-seo-factory-core
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin-local development-build autoloader.
 *
 * Register this first so an unrelated wp-content/vendor tree cannot mask the
 * standalone classes and bundled Task 01 contracts shipped in this plugin.
 *
 * @param string $class_name Class name.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefixes = array(
			'Atal\\SeoFactory\\' => __DIR__ . '/src/',
			'Atal\\Contracts\\'  => __DIR__ . '/dependencies/contracts/',
			'Atal\\SeoImages\\'  => __DIR__ . '/dependencies/seo-images/',
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

register_activation_hook( __FILE__, array( Atal\SeoFactory\Bootstrap::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Atal\SeoFactory\Bootstrap::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( Atal\SeoFactory\Bootstrap::class, 'boot' ) );
