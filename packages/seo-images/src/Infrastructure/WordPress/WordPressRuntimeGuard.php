<?php
/** Exact staging hostname and indexing guard. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Infrastructure\WordPress;

use Atal\SeoImages\Contract\RuntimeGuardInterface;
use Atal\SeoImages\Domain\AcceptanceFixture;
use Atal\SeoImages\Exception\PipelineException;

final class WordPressRuntimeGuard implements RuntimeGuardInterface {
	/** @param list<string> $legacy_plugin_needles */
	public function __construct( private readonly array $legacy_plugin_needles ) {}
	public function assert_ready( AcceptanceFixture $fixture ): void {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( $fixture->expected_host() !== $host ) {
			throw new PipelineException( 'Task 05 blocked a non-approved hostname.' ); }
		if ( ! in_array( get_option( 'blog_public', '1' ), array( 0, '0' ), true ) ) {
			throw new PipelineException( 'Search indexing must remain disabled for Task 05.' ); }
		$active = get_option( 'active_plugins', array() );
		if ( is_array( $active ) ) {
			foreach ( $active as $plugin ) {
				if ( ! is_string( $plugin ) ) {
					continue;
				} foreach ( $this->legacy_plugin_needles as $needle ) {
					if ( str_contains( strtolower( $plugin ), strtolower( $needle ) ) ) {
						throw new PipelineException( 'A legacy ATAL connector is active.' ); }
				}
			}
		}
	}
}
