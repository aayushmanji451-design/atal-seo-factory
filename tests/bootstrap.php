<?php
/**
 * PHPUnit bootstrap for local and CI test runs.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

$autoload_path = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_readable( $autoload_path ) ) {
	throw new RuntimeException( 'Composer dependencies are missing. Run composer install.' );
}

require_once $autoload_path;
