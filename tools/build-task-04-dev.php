<?php
/** Build both standalone Task 04 development plugins. @package AtalSeoFactory */
declare(strict_types=1);

$project_root = dirname( __DIR__ );
$release_dir  = $project_root . '/release';
if ( ! is_dir( $release_dir ) && ! mkdir( $release_dir, 0775, true ) && ! is_dir( $release_dir ) ) {
	throw new RuntimeException( 'Unable to create the release directory.' );
}

/**
 * Build one deterministic, standalone development plugin archive.
 *
 * @return array{zip:string,files:int,sha256:string}
 * @throws RuntimeException When the archive cannot be built or hashed.
 */
function build_task04_plugin( string $project_root, string $release_dir, string $slug, string $filename ): array {
	$zip_path = $release_dir . '/' . $filename;
	$sources  = array(
		$project_root . '/plugins/' . $slug       => $slug,
		$project_root . '/packages/contracts/src' => $slug . '/dependencies/contracts',
		$project_root . '/data/master'            => $slug . '/knowledge/master',
		$project_root . '/data/schemas'           => $slug . '/knowledge/schemas',
	);
	$entries  = array();
	foreach ( $sources as $source => $destination ) {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || '.gitkeep' === $file->getFilename() ) {
				continue; }
			$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $source ) + 1 ) );
			if ( str_contains( $relative, '/dependencies/' ) || str_contains( $relative, '/knowledge/' ) ) {
				continue; }
			if ( str_starts_with( $destination, $slug . '/knowledge/' ) && ! str_ends_with( $relative, '.json' ) ) {
				continue; }
			$entries[ $destination . '/' . $relative ] = $file->getPathname();
		}
	}
	ksort( $entries );
	if ( file_exists( $zip_path ) && ! unlink( $zip_path ) ) {
		throw new RuntimeException( 'Unable to replace the Task 04 development ZIP.' ); }
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::EXCL ) ) {
		throw new RuntimeException( 'Unable to create the Task 04 development ZIP.' ); }
	foreach ( $entries as $archive_name => $source_path ) {
		if ( ! $zip->addFile( $source_path, $archive_name ) ) {
			throw new RuntimeException( 'Unable to add ' . $archive_name ); }
		$zip->setMtimeName( $archive_name, 315532800 );
	}
	if ( ! $zip->close() ) {
		throw new RuntimeException( 'Unable to finalize the Task 04 development ZIP.' ); }
	$hash = hash_file( 'sha256', $zip_path );
	if ( false === $hash || false === file_put_contents( $zip_path . '.sha256', strtoupper( $hash ) . '  ' . basename( $zip_path ) . PHP_EOL ) ) {
		throw new RuntimeException( 'Unable to write the Task 04 checksum.' ); }
	return array(
		'zip'    => $zip_path,
		'files'  => count( $entries ),
		'sha256' => strtoupper( $hash ),
	);
}

$builds = array(
	build_task04_plugin( $project_root, $release_dir, 'atal-seo-factory-core', 'atal-seo-factory-core-0.4.1-dev-task-04.zip' ),
	build_task04_plugin( $project_root, $release_dir, 'atal-diploma-receiver', 'atal-diploma-receiver-0.4.1-dev-task-04.zip' ),
);
foreach ( $builds as $build ) {
	echo 'ZIP=' . $build['zip'] . PHP_EOL;
	echo 'FILES=' . $build['files'] . PHP_EOL;
	echo 'SHA256=' . $build['sha256'] . PHP_EOL;
}
