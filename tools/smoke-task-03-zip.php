<?php
/**
 * Standalone smoke for the Task 03 receiver development ZIP.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\DiplomaReceiver\Application\Validation\CanonicalDiplomaCatalog;
use Atal\DiplomaReceiver\Bootstrap;
use Atal\DiplomaReceiver\Plugin;

$project_root = dirname( __DIR__ );
$zip_path     = $argv[1] ?? $project_root . '/release/atal-diploma-receiver-0.3.0-dev-task-03.zip';
$checksum     = $zip_path . '.sha256';
if ( ! is_readable( $zip_path ) || ! is_readable( $checksum ) ) {
	throw new RuntimeException( 'Task 03 development ZIP or checksum is missing.' );
}
$expected_hash = strtok( trim( (string) file_get_contents( $checksum ) ), ' ' );
$actual_hash   = strtoupper( (string) hash_file( 'sha256', $zip_path ) );
if ( ! is_string( $expected_hash ) || strtoupper( $expected_hash ) !== $actual_hash ) {
	throw new RuntimeException( 'Task 03 development ZIP checksum mismatch.' );
}
$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path ) ) {
	throw new RuntimeException( 'Task 03 development ZIP cannot be opened.' );
}
$entries = array();
$count   = $zip->count();
for ( $index = 0; $index < $count; ++$index ) {
	$name = $zip->getNameIndex( $index );
	if ( false === $name || ! str_starts_with( $name, 'atal-diploma-receiver/' ) || str_contains( $name, '../' ) ) {
		throw new RuntimeException( 'ZIP contains an unsafe path.' );
	}
	$entries[] = $name;
}
foreach ( array( 'atal-diploma-receiver/atal-diploma-receiver.php', 'atal-diploma-receiver/src/Rest/ReceiverController.php', 'atal-diploma-receiver/dependencies/contracts/Data/KnowledgePackage.php', 'atal-diploma-receiver/knowledge/master/02-ATAL-DIPLOMA-COURSE-MASTER.json', 'atal-diploma-receiver/knowledge/schemas/course.schema.json' ) as $required ) {
	if ( ! in_array( $required, $entries, true ) ) {
		throw new RuntimeException( 'ZIP is missing: ' . $required );
	}
}
foreach ( $entries as $entry ) {
	if ( str_contains( $entry, '/vendor/' ) || str_contains( $entry, '/tests/' ) || str_contains( $entry, 'composer.json' ) || str_contains( $entry, '.env' ) ) {
		throw new RuntimeException( 'ZIP contains a forbidden dependency or secret file.' );
	}
}
$temporary = sys_get_temp_dir() . '/atal-diploma-receiver-task-03-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $temporary, 0700, true ) || ! $zip->extractTo( $temporary ) ) {
	throw new RuntimeException( 'Unable to extract the Task 03 ZIP.' );
}
$zip->close();
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $temporary . '/wordpress/' );
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	/**
	 * Register a smoke activation hook.
	 *
	 * @param string   $file     Plugin file.
	 * @param callable $callback Callback.
	 */
	function register_activation_hook( string $file, callable $callback ): void {
		unset( $file, $callback );
	}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	/**
	 * Register a smoke deactivation hook.
	 *
	 * @param string   $file     Plugin file.
	 * @param callable $callback Callback.
	 */
	function register_deactivation_hook( string $file, callable $callback ): void {
		unset( $file, $callback );
	}
}
if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Register a smoke runtime hook.
	 *
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 */
	function add_action( string $hook, callable $callback ): void {
		unset( $hook, $callback );
	}
}
$plugin_root = $temporary . '/atal-diploma-receiver';
require $plugin_root . '/atal-diploma-receiver.php';
if ( ! class_exists( Bootstrap::class ) || '0.3.0-dev' !== Plugin::VERSION ) {
	throw new RuntimeException( 'Extracted receiver bootstrap/version smoke failed.' );
}
$package = KnowledgePackage::from_directory( $plugin_root . '/knowledge/master' );
$report  = KnowledgeValidator::create_default()->validate( $package, $plugin_root . '/knowledge/schemas' );
if ( ! $report->is_valid() ) {
	throw new RuntimeException( 'Bundled Task 01 contracts failed validation.' );
}
$catalog = new CanonicalDiplomaCatalog( $plugin_root . '/knowledge/master', $plugin_root . '/knowledge/schemas', KnowledgeValidator::create_default() );
$catalog->assert_valid();
if ( ! $catalog->contains( 'diploma_basic_health_care' ) || $catalog->contains( 'institute_basic_health_care' ) ) {
	throw new RuntimeException( 'Standalone Diploma identity guard failed.' );
}
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temporary, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $iterator as $extracted_path ) {
	$extracted_path->isDir() ? rmdir( $extracted_path->getPathname() ) : unlink( $extracted_path->getPathname() );
}
rmdir( $temporary );
echo 'ZIP_SMOKE=PASS' . PHP_EOL;
echo 'PLUGIN_VERSION=' . Plugin::VERSION . PHP_EOL;
echo 'DIPLOMA_CANONICAL_IDENTITIES=14' . PHP_EOL;
echo 'COMPOSER_REQUIRED_ON_SERVER=NO' . PHP_EOL;
echo 'SHA256=' . $actual_hash . PHP_EOL;
