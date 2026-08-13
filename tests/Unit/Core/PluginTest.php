<?php
/**
 * Lightweight plugin runtime tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Core;

use Atal\SeoFactory\Admin\HealthPage;
use Atal\SeoFactory\Cli\KnowledgeCommand;
use Atal\SeoFactory\Plugin;
use AtalWordPressStubState;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verifies that browser requests do not eagerly construct acceptance services.
 */
final class PluginTest extends TestCase {

	protected function setUp(): void {
		AtalWordPressStubState::$calls = array();
	}

	public function test_boot_defers_health_and_cli_service_construction(): void {
		$health_calls  = 0;
		$command_calls = 0;
		$plugin        = new Plugin(
			static function () use ( &$health_calls ): HealthPage {
				++$health_calls;
				throw new RuntimeException( 'Health factory must remain lazy during bootstrap.' );
			},
			static function () use ( &$command_calls ): KnowledgeCommand {
				++$command_calls;
				throw new RuntimeException( 'CLI factory must remain lazy outside WP-CLI.' );
			}
		);

		$plugin->boot();

		self::assertSame( 0, $health_calls );
		self::assertSame( 0, $command_calls );
		self::assertCount( 1, AtalWordPressStubState::$calls );
		$hook_call = AtalWordPressStubState::$calls[0];
		self::assertIsArray( $hook_call );
		self::assertSame( 'admin_menu', $hook_call[1] );
	}

	public function test_health_page_factory_failure_is_contained_and_readable(): void {
		$plugin = new Plugin(
			static function (): HealthPage {
				throw new RuntimeException( 'bounded staging failure' );
			},
			static function (): KnowledgeCommand {
				throw new RuntimeException( 'not used' );
			}
		);

		ob_start();
		$plugin->render_health_page();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Task 02 staging acceptance could not start', $output );
		self::assertStringContainsString( 'bounded staging failure', $output );
	}
}
