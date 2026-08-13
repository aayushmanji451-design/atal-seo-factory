<?php
/**
 * Canonical row storage operations.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Domain\Storage;

/**
 * Narrow prepared row gateway used by WordPress repositories.
 */
interface RowStoreInterface {

	/**
	 * @param string $table_name      Fully qualified table name.
	 * @param string $identity_column Identity column.
	 * @param string $identity        Identity value.
	 */
	public function find_source_hash( string $table_name, string $identity_column, string $identity ): ?string;

	/**
	 * @param string                        $table_name      Fully qualified table name.
	 * @param string                        $identity_column Identity column.
	 * @param string                        $identity        Identity value.
	 * @param array<string,int|string|null> $data            Column values excluding identity.
	 * @param list<string>                  $formats         WordPress value formats.
	 */
	public function upsert_row( string $table_name, string $identity_column, string $identity, array $data, array $formats ): void;
}
