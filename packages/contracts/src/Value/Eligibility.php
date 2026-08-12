<?php
/**
 * Eligibility value object.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Value;

use InvalidArgumentException;

/**
 * Immutable course eligibility publication contract.
 */
final class Eligibility {

	public const OMIT = 'OMIT';

	public const SHOW = 'SHOW';

	/**
	 * Eligibility criteria.
	 *
	 * @var list<string>
	 */
	private readonly array $criteria;

	/**
	 * Create an eligibility contract.
	 *
	 * @param string       $publication_behavior Publication behavior.
	 * @param list<string> $criteria             Course-specific criteria.
	 *
	 * @throws InvalidArgumentException When behavior and criteria conflict.
	 */
	public function __construct(
		private readonly string $publication_behavior,
		array $criteria
	) {
		if ( ! in_array( $publication_behavior, array( self::OMIT, self::SHOW ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported eligibility publication behavior.' );
		}

		if ( self::OMIT === $publication_behavior && array() !== $criteria ) {
			throw new InvalidArgumentException( 'OMIT eligibility cannot expose criteria.' );
		}

		if ( self::SHOW === $publication_behavior && array() === $criteria ) {
			throw new InvalidArgumentException( 'SHOW eligibility requires course-specific criteria.' );
		}

		$this->criteria = $criteria;
	}

	/**
	 * Return the publication behavior.
	 */
	public function publication_behavior(): string {
		return $this->publication_behavior;
	}

	/**
	 * Return course-specific criteria.
	 *
	 * @return list<string>
	 */
	public function criteria(): array {
		return $this->criteria;
	}
}
