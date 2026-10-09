<?php
/**
 * Bounded job stages.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

/**
 * Each stage is one bounded, idempotent unit of work for exactly one article.
 */
final class JobStage {

	public const VALIDATE     = 'validate';
	public const RENDER_IMAGE = 'render_image';
	public const SAVE_SEO     = 'save_seo';
	public const DELIVER      = 'deliver_draft';
	public const VERIFY       = 'verify';
	public const PUBLISH      = 'publish';
	public const ROLLBACK     = 'rollback';

	public const GROUP_GENERATION = 'generation';
	public const GROUP_PUBLISHING = 'publishing';
	public const GROUP_ROLLBACK   = 'rollback';

	/**
	 * Every valid stage.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::VALIDATE, self::RENDER_IMAGE, self::SAVE_SEO, self::DELIVER, self::VERIFY, self::PUBLISH, self::ROLLBACK );
	}

	/**
	 * Ordered stages that run after an approved item is activated.
	 *
	 * @return list<string>
	 */
	public static function pipeline(): array {
		return array( self::RENDER_IMAGE, self::SAVE_SEO, self::DELIVER, self::VERIFY, self::PUBLISH );
	}

	/**
	 * Return the stage that follows a completed stage.
	 *
	 * @param string $stage Completed stage.
	 */
	public static function next( string $stage ): ?string {
		$pipeline = self::pipeline();
		$index    = array_search( $stage, $pipeline, true );
		if ( false === $index || ! isset( $pipeline[ $index + 1 ] ) ) {
			return null;
		}

		return $pipeline[ $index + 1 ];
	}

	/**
	 * Classify a stage for quota and visibility purposes.
	 *
	 * @param string $stage Stage.
	 */
	public static function group( string $stage ): string {
		if ( self::PUBLISH === $stage ) {
			return self::GROUP_PUBLISHING;
		}

		return self::ROLLBACK === $stage ? self::GROUP_ROLLBACK : self::GROUP_GENERATION;
	}

	/**
	 * Static-only class.
	 */
	private function __construct() {
	}
}
