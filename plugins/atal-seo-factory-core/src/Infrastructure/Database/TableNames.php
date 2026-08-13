<?php
/**
 * Core table names.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\Database;

use Atal\SeoFactory\Config\Identifiers;

/**
 * Resolves all seven new names from the current WordPress table prefix.
 */
final class TableNames {

	public function __construct( private readonly string $wordpress_prefix ) {
	}

	public function courses(): string {
		return $this->name( 'courses' );
	}

	public function topics(): string {
		return $this->name( 'topics' );
	}

	public function articles(): string {
		return $this->name( 'articles' );
	}

	public function assets(): string {
		return $this->name( 'assets' );
	}

	public function publish_jobs(): string {
		return $this->name( 'publish_jobs' );
	}

	public function cost_ledger(): string {
		return $this->name( 'cost_ledger' );
	}

	public function audit_logs(): string {
		return $this->name( 'audit_logs' );
	}

	/**
	 * @return array<string,string>
	 */
	public function keyed(): array {
		return array(
			'courses'      => $this->courses(),
			'topics'       => $this->topics(),
			'articles'     => $this->articles(),
			'assets'       => $this->assets(),
			'publish_jobs' => $this->publish_jobs(),
			'cost_ledger'  => $this->cost_ledger(),
			'audit_logs'   => $this->audit_logs(),
		);
	}

	/**
	 * @return list<string>
	 */
	public function all(): array {
		return array_values( $this->keyed() );
	}

	private function name( string $suffix ): string {
		return $this->wordpress_prefix . Identifiers::TABLE_PREFIX . $suffix;
	}
}
