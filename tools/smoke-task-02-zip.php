<?php
/**
 * Standalone smoke test for the Task 02 development ZIP.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\SeoFactory\Application\Acceptance\KnowledgePackageInspector;
use Atal\SeoFactory\Bootstrap;
use Atal\SeoFactory\Plugin;
$project_root = dirname( __DIR__ );
$zip_path     = $argv[1] ?? $project_root . '/release/atal-seo-factory-core-0.2.1-dev-task-02.zip';
$checksum     = $zip_path . '.sha256';

if ( ! is_readable( $zip_path ) || ! is_readable( $checksum ) ) {
	throw new RuntimeException( 'Development ZIP or checksum is missing.' );
}

$expected_hash = strtok( trim( (string) file_get_contents( $checksum ) ), ' ' );
$actual_hash   = strtoupper( (string) hash_file( 'sha256', $zip_path ) );
if ( ! is_string( $expected_hash ) || strtoupper( $expected_hash ) !== $actual_hash ) {
	throw new RuntimeException( 'Development ZIP checksum mismatch.' );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path ) ) {
	throw new RuntimeException( 'Development ZIP cannot be opened.' );
}

$entries     = array();
$entry_count = $zip->count();
for ( $index = 0; $index < $entry_count; ++$index ) {
	$name = $zip->getNameIndex( $index );
	if ( false === $name ) {
		throw new RuntimeException( 'ZIP entry cannot be read.' );
	}
	if ( ! str_starts_with( $name, 'atal-seo-factory-core/' ) || str_contains( $name, '../' ) || str_starts_with( $name, '/' ) ) {
		throw new RuntimeException( 'ZIP contains an unsafe or unexpected path.' );
	}
	$entries[] = $name;
}

$required = array(
	'atal-seo-factory-core/atal-seo-factory-core.php',
	'atal-seo-factory-core/src/Plugin.php',
	'atal-seo-factory-core/dependencies/contracts/Data/KnowledgePackage.php',
	'atal-seo-factory-core/knowledge/master/01-ATAL-INSTITUTE-COURSE-MASTER.json',
	'atal-seo-factory-core/knowledge/schemas/course.schema.json',
);
foreach ( $required as $required_entry ) {
	if ( ! in_array( $required_entry, $entries, true ) ) {
		throw new RuntimeException( 'ZIP is missing required entry: ' . $required_entry );
	}
}
foreach ( $entries as $entry ) {
	if ( str_contains( $entry, '/vendor/' ) || str_contains( $entry, 'composer.json' ) || str_contains( $entry, '/tests/' ) ) {
		throw new RuntimeException( 'ZIP contains a forbidden development dependency.' );
	}
}

$temporary = sys_get_temp_dir() . '/atal-seo-factory-task-02-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $temporary, 0700, true ) || ! $zip->extractTo( $temporary ) ) {
	throw new RuntimeException( 'Unable to extract the development ZIP.' );
}
$zip->close();

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $temporary . '/wordpress/' );
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	/**
	 * Register an extracted activation callback without executing it.
	 *
	 * @param string   $file     Plugin file.
	 * @param callable $callback Activation callback.
	 */
	function register_activation_hook( string $file, callable $callback ): void {
		unset( $file, $callback );
	}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	/**
	 * Register an extracted deactivation callback without executing it.
	 *
	 * @param string   $file     Plugin file.
	 * @param callable $callback Deactivation callback.
	 */
	function register_deactivation_hook( string $file, callable $callback ): void {
		unset( $file, $callback );
	}
}
if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Register an extracted runtime callback without executing it.
	 *
	 * @param string   $hook     WordPress hook name.
	 * @param callable $callback Runtime callback.
	 */
	function add_action( string $hook, callable $callback ): void {
		unset( $hook, $callback );
	}
}

$plugin_root = $temporary . '/atal-seo-factory-core';
require $plugin_root . '/atal-seo-factory-core.php';
if ( ! class_exists( Bootstrap::class ) || '0.2.1-dev' !== Plugin::VERSION ) {
	throw new RuntimeException( 'Extracted plugin bootstrap/version smoke failed.' );
}

$package = KnowledgePackage::from_directory( $plugin_root . '/knowledge/master' );
$report  = KnowledgeValidator::create_default()->validate( $package, $plugin_root . '/knowledge/schemas' );
if ( ! $report->is_valid() ) {
	throw new RuntimeException( 'Bundled canonical package failed standalone validation.' );
}
$summary = ( new KnowledgePackageInspector() )->summary( $package );
if ( array(
	'active_total'       => 43,
	'institute_families' => 29,
	'diploma_identities' => 14,
	'institute_options'  => 49,
) !== $summary ) {
	throw new RuntimeException( 'Bundled canonical identity counts are incorrect.' );
}

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temporary, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $iterator as $extracted_path ) {
	if ( $extracted_path->isDir() ) {
		rmdir( $extracted_path->getPathname() );
	} else {
		unlink( $extracted_path->getPathname() );
	}
}
rmdir( $temporary );

echo 'ZIP_SMOKE=PASS' . PHP_EOL;
echo 'PLUGIN_VERSION=' . Plugin::VERSION . PHP_EOL;
echo 'CANONICAL_ACTIVE_IDENTITIES=' . $summary['active_total'] . PHP_EOL;
echo 'COMPOSER_REQUIRED_ON_SERVER=NO' . PHP_EOL;
echo 'SHA256=' . $actual_hash . PHP_EOL;
