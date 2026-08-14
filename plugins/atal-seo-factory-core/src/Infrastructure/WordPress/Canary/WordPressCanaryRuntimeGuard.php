<?php
/** Exact staging and SEO runtime guard. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Infrastructure\WordPress\Canary;

use Atal\SeoFactory\Application\Canary\CanaryException;
use Atal\SeoFactory\Config\Identifiers;
use Atal\SeoFactory\Domain\Canary\CanaryRuntimeGuardInterface;

final class WordPressCanaryRuntimeGuard implements CanaryRuntimeGuardInterface {
	public const INSTITUTE_HOST  = 'liveup2.atalinstitute.com';
	public const SECRET_CONSTANT = 'ATAL_SEO_FACTORY_DIPLOMA_HMAC_SECRET';

	public function assert_institute_ready(): void {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( self::INSTITUTE_HOST !== $host ) {
			throw new CanaryException( 'Task 04 can run only on the approved ATAL Institute staging hostname.' );
		}
		if ( ! in_array( get_option( 'blog_public', '1' ), array( 0, '0' ), true ) ) {
			throw new CanaryException( 'Search indexing must remain disabled before a canary draft is created.' );
		}
		if ( ! $this->rank_math_active() ) {
			throw new CanaryException( 'Rank Math must be active before the Institute canary runs.' );
		}
		if ( $this->old_connector_active() ) {
			throw new CanaryException( 'A legacy ATAL publishing connector is active.' );
		}
	}

	public function assert_diploma_send_ready(): void {
		$this->assert_institute_ready();
		if ( 32 > strlen( self::shared_secret() ) ) {
			throw new CanaryException( 'Configure the Task 04 Diploma HMAC secret on Institute staging before the Diploma canary.' );
		}
	}

	public static function shared_secret(): string {
		$value = defined( self::SECRET_CONSTANT ) ? constant( self::SECRET_CONSTANT ) : get_option( Identifiers::OPTION_DIPLOMA_HMAC_SECRET, '' );
		return is_string( $value ) ? $value : '';
	}

	private function rank_math_active(): bool {
		if ( defined( 'RANK_MATH_VERSION' ) || function_exists( 'rank_math' ) ) {
			return true;
		}
		$active = get_option( 'active_plugins', array() );
		return is_array( $active ) && in_array( 'seo-by-rank-math/rank-math.php', $active, true );
	}

	private function old_connector_active(): bool {
		$active = get_option( 'active_plugins', array() );
		if ( ! is_array( $active ) ) {
			return false;
		}
		foreach ( $active as $plugin ) {
			if ( is_string( $plugin ) && str_contains( strtolower( $plugin ), 'atal-seo-connector' ) ) {
				return true;
			}
		}
		return false;
	}
}
