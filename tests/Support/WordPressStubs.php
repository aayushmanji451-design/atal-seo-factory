<?php
/**
 * Minimal WordPress symbols for static analysis and isolated unit tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

if ( ! class_exists( 'AtalWordPressStubState' ) ) {
	final class AtalWordPressStubState {

		/** @var list<mixed> */
		public static array $calls = array();
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	class wpdb {

		public string $prefix = 'wp_';

		public function get_charset_collate(): string {
			return 'DEFAULT CHARACTER SET utf8mb4';
		}

		public function query( string $query ): int|false {
			unset( $query );
			return 0;
		}

		public function prepare( string $query, mixed ...$args ): string {
			unset( $args );
			return $query;
		}

		public function esc_like( string $text ): string {
			return $text;
		}

		public function get_var( string $query ): mixed {
			unset( $query );
			return null;
		}

		/**
		 * @param array<string,int|string|null> $data    Data.
		 * @param list<string>                  $formats Formats.
		 */
		public function insert( string $table, array $data, array $formats ): int|false {
			unset( $table, $data, $formats );
			return 1;
		}

		/**
		 * @param array<string,int|string|null> $data          Data.
		 * @param array<string,string>          $where         Where values.
		 * @param list<string>                  $formats       Data formats.
		 * @param list<string>                  $where_formats Where formats.
		 */
		public function update( string $table, array $data, array $where, array $formats, array $where_formats ): int|false {
			unset( $table, $data, $where, $formats, $where_formats );
			return 1;
		}
	}
}

if ( ! class_exists( 'WP_CLI' ) ) {
	final class WP_CLI {

		public static function add_command( string $name, object $command ): void {
			unset( $name, $command );
		}

		public static function log( string $message ): void {
			unset( $message );
		}

		public static function warning( string $message ): void {
			unset( $message );
		}

		public static function success( string $message ): void {
			unset( $message );
		}

		public static function error( string $message ): never {
			throw new RuntimeException( $message );
		}
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * @phpstan-impure
	 *
	 * @return list<string>
	 */
	function dbDelta( string $sql ): array {
		AtalWordPressStubState::$calls[] = 'dbDelta:' . $sql;
		return array();
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, mixed $default = false ): mixed {
		unset( $name );
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/** @phpstan-impure */
	function update_option( string $name, mixed $value, bool $autoload = false ): bool {
		AtalWordPressStubState::$calls[] = array( 'update_option', $name, $value, $autoload );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/** @phpstan-impure */
	function add_action( string $hook, callable $callback ): void {
		AtalWordPressStubState::$calls[] = array( 'add_action', $hook, $callback );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	/** @phpstan-impure */
	function register_activation_hook( string $file, callable $callback ): void {
		AtalWordPressStubState::$calls[] = array( 'register_activation_hook', $file, $callback );
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	/** @phpstan-impure */
	function register_deactivation_hook( string $file, callable $callback ): void {
		AtalWordPressStubState::$calls[] = array( 'register_deactivation_hook', $file, $callback );
	}
}

if ( ! function_exists( 'add_management_page' ) ) {
	/** @phpstan-impure */
	function add_management_page( string $page_title, string $menu_title, string $capability, string $slug, callable $callback ): string {
		AtalWordPressStubState::$calls[] = array( 'add_management_page', $page_title, $menu_title, $capability, $slug, $callback );
		return '';
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		unset( $capability );
		return true;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( string $message ): never {
		throw new RuntimeException( $message );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain ): string {
		unset( $domain );
		return esc_html( $text );
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( string $path = '' ): string {
		return 'https://example.test/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	function wp_get_environment_type(): string {
		return 'staging';
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show ): string {
		unset( $show );
		return '6.9';
	}
}
