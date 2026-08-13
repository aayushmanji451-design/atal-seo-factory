<?php
/**
 * Build the self-contained Task 03 development receiver artifact.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

$project_root = dirname( __DIR__ );
$release_dir  = $project_root . '/release';
$zip_path     = $release_dir . '/atal-diploma-receiver-0.3.0-dev-task-03.zip';
$checksum     = $zip_path . '.sha256';

if ( ! is_dir( $release_dir ) && ! mkdir( $release_dir, 0775, true ) && ! is_dir( $release_dir ) ) {
	throw new RuntimeException( 'Unable to create the release directory.' );
}

$sources = array(
	$project_root . '/plugins/atal-diploma-receiver' => 'atal-diploma-receiver',
	$project_root . '/packages/contracts/src'        => 'atal-diploma-receiver/dependencies/contracts',
	$project_root . '/data/master'                   => 'atal-diploma-receiver/knowledge/master',
	$project_root . '/data/schemas'                  => 'atal-diploma-receiver/knowledge/schemas',
);

$entries = array();
foreach ( $sources as $source => $destination ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || '.gitkeep' === $file->getFilename() ) {
			continue;
		}
		$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $source ) + 1 ) );
		if ( str_contains( $relative, '/dependencies/' ) || str_contains( $relative, '/knowledge/' ) ) {
			continue;
		}
		if ( str_starts_with( $destination, 'atal-diploma-receiver/knowledge/' ) && ! str_ends_with( $relative, '.json' ) ) {
			continue;
		}
		$entries[ $destination . '/' . $relative ] = $file->getPathname();
	}
}

ksort( $entries );
if ( file_exists( $zip_path ) && ! unlink( $zip_path ) ) {
	throw new RuntimeException( 'Unable to replace the Task 03 development ZIP.' );
}
$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::EXCL ) ) {
	throw new RuntimeException( 'Unable to create the Task 03 development ZIP.' );
}
foreach ( $entries as $archive_name => $source_path ) {
	if ( ! $zip->addFile( $source_path, $archive_name ) ) {
		throw new RuntimeException( 'Unable to add required file: ' . $archive_name );
	}
	$zip->setMtimeName( $archive_name, 315532800 );
}
if ( ! $zip->close() ) {
	throw new RuntimeException( 'Unable to finalize the Task 03 development ZIP.' );
}
$hash = hash_file( 'sha256', $zip_path );
if ( false === $hash || false === file_put_contents( $checksum, strtoupper( $hash ) . '  ' . basename( $zip_path ) . PHP_EOL ) ) {
	throw new RuntimeException( 'Unable to write the Task 03 checksum.' );
}
echo 'ZIP=' . $zip_path . PHP_EOL;
echo 'FILES=' . count( $entries ) . PHP_EOL;
echo 'SHA256=' . strtoupper( $hash ) . PHP_EOL;
