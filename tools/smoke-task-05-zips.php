<?php
/**
 * Standalone Task 05 ZIP smoke test.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

$root  = dirname( __DIR__ );
$specs = array(
	array(
		'slug'     => 'atal-seo-factory-core',
		'zip'      => $root . '/release/atal-seo-factory-core-0.5.0-dev-task-05.zip',
		'main'     => 'atal-seo-factory-core.php',
		'class'    => 'Atal\\SeoFactory\\Plugin',
		'required' => 'src/Admin/Task05Panel.php',
	),
	array(
		'slug'     => 'atal-diploma-receiver',
		'zip'      => $root . '/release/atal-diploma-receiver-0.5.0-dev-task-05.zip',
		'main'     => 'atal-diploma-receiver.php',
		'class'    => 'Atal\\DiplomaReceiver\\Plugin',
		'required' => 'src/Application/SeoImages/Task05RemoteService.php',
	),
);

foreach ( $specs as $spec ) {
	$checksum = $spec['zip'] . '.sha256';
	if ( ! is_readable( $spec['zip'] ) || ! is_readable( $checksum ) ) {
		throw new RuntimeException( 'Task 05 ZIP or checksum is missing.' );
	}
	$expected = strtoupper( (string) strtok( trim( (string) file_get_contents( $checksum ) ), ' ' ) );
	$actual   = strtoupper( (string) hash_file( 'sha256', $spec['zip'] ) );
	if ( $expected !== $actual ) {
		throw new RuntimeException( 'Task 05 ZIP checksum mismatch.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $spec['zip'] ) ) {
		throw new RuntimeException( 'Task 05 ZIP cannot be opened.' );
	}
	$entries     = array();
	$entry_count = $zip->count();
	for ( $index = 0; $index < $entry_count; ++$index ) {
		$name = $zip->getNameIndex( $index );
		if ( false === $name || ! str_starts_with( $name, $spec['slug'] . '/' ) || str_contains( $name, '../' ) || str_starts_with( $name, '/' ) ) {
			throw new RuntimeException( 'Unsafe Task 05 ZIP path.' );
		}
		$entries[] = $name;
	}
	foreach ( array( $spec['slug'] . '/' . $spec['main'], $spec['slug'] . '/' . $spec['required'], $spec['slug'] . '/dependencies/contracts/Data/KnowledgePackage.php', $spec['slug'] . '/dependencies/seo-images/Application/AcceptanceCoordinator.php', $spec['slug'] . '/knowledge/master/05-IMAGE-ASSET-MAP.json', $spec['slug'] . '/knowledge/schemas/image-asset.schema.json' ) as $required ) {
		if ( ! in_array( $required, $entries, true ) ) {
			throw new RuntimeException( 'Task 05 ZIP is missing ' . $required );
		}
	}
	foreach ( $entries as $entry ) {
		if ( str_contains( $entry, '/vendor/' ) || str_contains( $entry, '/tests/' ) || str_contains( $entry, '.env' ) || str_contains( $entry, 'composer.json' ) ) {
			throw new RuntimeException( 'Task 05 ZIP contains a forbidden file.' );
		}
	}
	$temporary = sys_get_temp_dir() . '/atal-task05-' . $spec['slug'] . '-' . bin2hex( random_bytes( 6 ) );
	if ( ! mkdir( $temporary, 0700, true ) || ! $zip->extractTo( $temporary ) ) {
		throw new RuntimeException( 'Unable to extract Task 05 ZIP.' );
	}
	$zip->close();
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $temporary . '/wordpress/' );
	}
	if ( ! function_exists( 'register_activation_hook' ) ) {
		/**
		 * No-op standalone activation stub.
		 *
		 * @param string   $file     Plugin file.
		 * @param callable $callback Activation callback.
		 */
		function register_activation_hook( string $file, callable $callback ): void {
			unset( $file, $callback ); }
	}
	if ( ! function_exists( 'register_deactivation_hook' ) ) {
		/**
		 * No-op standalone deactivation stub.
		 *
		 * @param string   $file     Plugin file.
		 * @param callable $callback Deactivation callback.
		 */
		function register_deactivation_hook( string $file, callable $callback ): void {
			unset( $file, $callback ); }
	}
	if ( ! function_exists( 'add_action' ) ) {
		/**
		 * No-op standalone action stub.
		 *
		 * @param string   $hook     Hook name.
		 * @param callable $callback Hook callback.
		 */
		function add_action( string $hook, callable $callback ): void {
			unset( $hook, $callback ); }
	}
	require $temporary . '/' . $spec['slug'] . '/' . $spec['main'];
	if ( ! class_exists( $spec['class'] ) || '0.5.0-dev' !== constant( $spec['class'] . '::VERSION' ) ) {
		throw new RuntimeException( 'Task 05 standalone version smoke failed.' );
	}
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temporary, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $extracted_path ) {
		$extracted_path->isDir() ? rmdir( $extracted_path->getPathname() ) : unlink( $extracted_path->getPathname() );
	}
	rmdir( $temporary );
	echo strtoupper( str_replace( '-', '_', $spec['slug'] ) ) . '_ZIP_SMOKE=PASS' . PHP_EOL;
	echo strtoupper( str_replace( '-', '_', $spec['slug'] ) ) . '_SHA256=' . $actual . PHP_EOL;
}
echo 'DEVELOPMENT_BUILDS_ONLY=YES' . PHP_EOL;
echo 'COMPOSER_REQUIRED_ON_SERVER=NO' . PHP_EOL;
echo 'PAID_IMAGE_API_CALLS=0' . PHP_EOL;
