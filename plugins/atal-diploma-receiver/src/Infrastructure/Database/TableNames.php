<?php
/** Receiver table names. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\Database;

use Atal\DiplomaReceiver\Config\Identifiers;
final class TableNames {
	public function __construct( private readonly string $wordpress_prefix ) {}
	public function receipts(): string {
		return $this->wordpress_prefix . Identifiers::TABLE_PREFIX . 'receipts'; }
	public function audit(): string {
		return $this->wordpress_prefix . Identifiers::TABLE_PREFIX . 'audit'; }
	/** @return list<string> */ public function all(): array {
		return array( $this->receipts(), $this->audit() ); }
}
