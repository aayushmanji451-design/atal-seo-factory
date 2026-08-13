<?php
/** Canonical course allowlist boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Receiver;

interface CourseCatalogInterface {
	public function assert_valid(): void;
	public function contains( string $course_key ): bool;
}
