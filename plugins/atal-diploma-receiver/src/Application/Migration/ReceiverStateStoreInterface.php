<?php
/** Receiver version/secret state boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Migration;

interface ReceiverStateStoreInterface {
	public function database_version(): int;
	public function set_database_version( int $version ): void;
	public function record_plugin_version( string $version ): void;
	public function ensure_secret(): void;
}
