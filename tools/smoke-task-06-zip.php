<?php
/**
 * Standalone Task 06 Core ZIP smoke test.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

$root     = dirname( __DIR__ );
$slug     = 'atal-seo-factory-core';
$zip_path = $root . '/release/atal-seo-factory-core-0.6.0-dev-task-06.zip';
$checksum = $zip_path . '.sha256';
if ( ! is_readable( $zip_path ) || ! is_readable( $checksum ) ) {
	throw new RuntimeException( 'Task 06 ZIP or checksum is missing.' );
}
$expected = strtoupper( (string) strtok( trim( (string) file_get_contents( $checksum ) ), ' ' ) );
$actual   = strtoupper( (string) hash_file( 'sha256', $zip_path ) );
if ( $expected !== $actual ) {
	throw new RuntimeException( 'Task 06 ZIP checksum mismatch.' );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path ) ) {
	throw new RuntimeException( 'Task 06 ZIP cannot be opened.' );
}
$entries     = array();
$entry_count = $zip->count();
for ( $index = 0; $index < $entry_count; ++$index ) {
	$name = $zip->getNameIndex( $index );
	if ( false === $name || ! str_starts_with( $name, $slug . '/' ) || str_contains( $name, '../' ) || str_starts_with( $name, '/' ) ) {
		throw new RuntimeException( 'Unsafe Task 06 ZIP path.' );
	}
	$entries[] = $name;
}

$required = array(
	$slug . '/atal-seo-factory-core.php',
	$slug . '/src/Admin/Task06Panel.php',
	$slug . '/dependencies/topics/Application/TopicValidator.php',
	$slug . '/dependencies/topics/Application/DeterministicRotation.php',
	$slug . '/dependencies/contracts/Data/KnowledgePackage.php',
	$slug . '/knowledge/master/01-ATAL-INSTITUTE-COURSE-MASTER.json',
	$slug . '/knowledge/schemas/course.schema.json',
);
foreach ( $required as $entry ) {
	if ( ! in_array( $entry, $entries, true ) ) {
		throw new RuntimeException( 'Task 06 ZIP is missing ' . $entry );
	}
}
foreach ( $entries as $entry ) {
	if ( str_contains( $entry, '/vendor/' ) || str_contains( $entry, '/tests/' ) || str_contains( $entry, '.env' ) || str_contains( $entry, 'composer.json' ) ) {
		throw new RuntimeException( 'Task 06 ZIP contains a forbidden file.' );
	}
}

$temporary = sys_get_temp_dir() . '/atal-task06-core-' . bin2hex( random_bytes( 6 ) );
if ( ! mkdir( $temporary, 0700, true ) || ! $zip->extractTo( $temporary ) ) {
	throw new RuntimeException( 'Unable to extract the Task 06 ZIP.' );
}
$zip->close();
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $temporary . '/wordpress/' );
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	/**
	 * Ignore an activation hook in standalone smoke.
	 *
	 * @param string   $file     Plugin file.
	 * @param callable $callback Activation callback.
	 */
	function register_activation_hook( string $file, callable $callback ): void {
		unset( $file, $callback ); }
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	/**
	 * Ignore a deactivation hook in standalone smoke.
	 *
	 * @param string   $file     Plugin file.
	 * @param callable $callback Deactivation callback.
	 */
	function register_deactivation_hook( string $file, callable $callback ): void {
		unset( $file, $callback ); }
}
if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Ignore an action hook in standalone smoke.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Hook callback.
	 */
	function add_action( string $hook, callable $callback ): void {
		unset( $hook, $callback ); }
}
require $temporary . '/' . $slug . '/atal-seo-factory-core.php';
if ( ! class_exists( 'Atal\\SeoFactory\\Plugin' ) || '0.6.0-dev' !== Atal\SeoFactory\Plugin::VERSION || ! class_exists( 'Atal\\Topics\\Application\\TopicValidator' ) ) {
	throw new RuntimeException( 'Task 06 standalone class/version smoke failed.' );
}

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temporary, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $iterator as $entry_path ) {
	$entry_path->isDir() ? rmdir( $entry_path->getPathname() ) : unlink( $entry_path->getPathname() );
}
rmdir( $temporary );

echo 'ATAL_SEO_FACTORY_CORE_ZIP_SMOKE=PASS' . PHP_EOL;
echo 'ATAL_SEO_FACTORY_CORE_SHA256=' . $actual . PHP_EOL;
echo 'DEVELOPMENT_BUILD_ONLY=YES' . PHP_EOL;
echo 'COMPOSER_REQUIRED_ON_SERVER=NO' . PHP_EOL;
echo 'PUBLISHING_CALLS=0' . PHP_EOL;
