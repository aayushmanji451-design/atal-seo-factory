<?php
/** Receiver schema database boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Application\Migration;

interface SchemaDatabaseInterface {
	public function prefix(): string;
	public function charset_collate(): string;
	public function create_or_update( string $sql ): void;
	public function drop( string $table ): void;
	public function exists( string $table ): bool;
}
