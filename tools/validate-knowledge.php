<?php
/**
 * Validate the complete canonical knowledge package.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

use Atal\Contracts\Cli\ValidateKnowledgeCommand;

$project_root = dirname( __DIR__ );
$autoload     = $project_root . '/vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
	fwrite( STDERR, 'Composer dependencies are missing. Run composer install.' . PHP_EOL );
	exit( 1 );
}

require_once $autoload;

$command = new ValidateKnowledgeCommand();
exit( $command->run( $project_root ) );
