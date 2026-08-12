<?php
/**
 * Core plugin runtime registration.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory;

use Atal\SeoFactory\Admin\HealthPage;
use Atal\SeoFactory\Cli\KnowledgeCommand;

/**
 * Registers only lightweight admin and command-line entry points.
 */
final class Plugin {

	public const VERSION = '0.2.0-dev';

	/**
	 * Create the runtime registrar.
	 *
	 * @param HealthPage       $health_page       Read-only health page.
	 * @param KnowledgeCommand $knowledge_command Canonical import command.
	 */
	public function __construct(
		private readonly HealthPage $health_page,
		private readonly KnowledgeCommand $knowledge_command
	) {
	}

	/**
	 * Register runtime hooks without performing migrations or imports.
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this->health_page, 'register' ) );

		if ( class_exists( '\\WP_CLI' ) ) {
			\WP_CLI::add_command( 'atal-seo-factory knowledge', $this->knowledge_command );
		}
	}
}
