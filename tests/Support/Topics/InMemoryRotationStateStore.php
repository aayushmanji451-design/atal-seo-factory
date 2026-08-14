<?php
/**
 * In-memory topic rotation cursor.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Support\Topics;

use Atal\Topics\Contract\RotationStateStoreInterface;

/**
 * Preserves cursors across reconstructed services in a unit test.
 */
final class InMemoryRotationStateStore implements RotationStateStoreInterface {

	/** @var array<string,int> */
	private array $cursors = array();

	/** {@inheritDoc} */
	public function cursor( string $target_site ): int {
		return $this->cursors[ $target_site ] ?? 0;
	}

	/** {@inheritDoc} */
	public function set_cursor( string $target_site, int $cursor ): void {
		$this->cursors[ $target_site ] = $cursor;
	}
}
