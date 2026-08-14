<?php
/**
 * Rotation cursor persistence.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Contract;

interface RotationStateStoreInterface {

	/**
	 * Read one site cursor.
	 *
	 * @param string $target_site Canonical target site.
	 */
	public function cursor( string $target_site ): int;

	/**
	 * Persist one site cursor.
	 *
	 * @param string $target_site Canonical target site.
	 * @param int    $cursor      Next schedule position.
	 */
	public function set_cursor( string $target_site, int $cursor ): void;
}
