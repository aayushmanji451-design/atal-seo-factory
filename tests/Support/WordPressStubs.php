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

		/** @var array<string,mixed> */
		public static array $options = array();

		/** @var array<int,array<string,mixed>> */
		public static array $post_meta = array();

		/** @var array<int,int> */
		public static array $thumbnails = array();
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	class wpdb {

		public string $prefix = 'wp_';

		public string $posts = 'wp_posts';

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

		/** @return array<string,mixed>|object|null */
		public function get_row( string $query, string $output = 'OBJECT' ): array|object|null {
			unset( $query, $output );
			return null;
		}

		/**
		 * @return list<array<string,mixed>>
		 */
		public function get_results( string $query, string $output = 'OBJECT' ): array|object|null {
			unset( $query, $output );
			return array();
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

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID = 101;
		public string $post_type = 'post';
		public string $post_status = 'draft';
		public string $post_title = '';
		public string $post_name = '';
		public string $post_content = '';
		public string $post_excerpt = '';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @param array<string,string> $headers */
		public function __construct( private string $method = 'GET', private string $route = '/', private string $body = '', private array $headers = array() ) {
		}
		public function get_method(): string { return $this->method; }
		public function get_route(): string { return $this->route; }
		public function get_body(): string { return $this->body; }
		public function get_header( string $name ): string { return $this->headers[ strtolower( $name ) ] ?? ''; }
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public function __construct( private mixed $data = null, private int $status = 200 ) {
		}
		public function get_data(): mixed { return $this->data; }
		public function get_status(): int { return $this->status; }
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		/** @return array<string,mixed> */ public function get_routes(): array { return array(); }
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
		return AtalWordPressStubState::$options[ $name ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/** @phpstan-impure */
	function update_option( string $name, mixed $value, bool $autoload = false ): bool {
		AtalWordPressStubState::$calls[] = array( 'update_option', $name, $value, $autoload );
		AtalWordPressStubState::$options[ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/** @phpstan-impure */
	function add_action( string $hook, callable $callback ): void {
		AtalWordPressStubState::$calls[] = array( 'add_action', $hook, $callback );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/** @phpstan-impure */
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		AtalWordPressStubState::$calls[] = array( 'add_filter', $hook, $callback, $priority, $accepted_args );
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/** @phpstan-impure */
	function remove_filter( string $hook, callable $callback, int $priority = 10 ): bool {
		AtalWordPressStubState::$calls[] = array( 'remove_filter', $hook, $callback, $priority );
		return true;
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

if ( ! function_exists( 'register_rest_route' ) ) {
	/** @param array<string,mixed> $args */
	function register_rest_route( string $namespace, string $route, array $args, bool $override = false ): bool {
		AtalWordPressStubState::$calls[] = array( 'register_rest_route', $namespace, $route, $args, $override );
		return true;
	}
}

if ( ! function_exists( '__return_true' ) ) {
	function __return_true(): bool { return true; }
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

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain ): string {
		unset( $domain );
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( string $value ): string { return stripslashes( $value ); }
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( string $path = '' ): string {
		return 'https://example.test/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string { return 'https://example.test/' . ltrim( $path, '/' ); }
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/** @return string|int|array<string,mixed>|false|null */
	function wp_parse_url( string $url, int $component = -1 ): string|int|array|false|null { return parse_url( $url, $component ); }
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

if ( ! function_exists( 'wp_raise_memory_limit' ) ) {
	function wp_raise_memory_limit( string $context = 'admin' ): string|false {
		if ( 'invalid' === $context ) {
			return false;
		}
		return '256M';
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( string $action = '-1', string $query_arg = '_wpnonce' ): int|false {
		unset( $query_arg );
		if ( 'invalid' === $action ) {
			return false;
		}
		return 1;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( mixed ...$args ): string {
		$last = end( $args );
		return is_string( $last ) ? $last : '';
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( string $location, int $status = 302, string $x_redirect_by = 'WordPress' ): bool {
		unset( $location, $status, $x_redirect_by );
		return true;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true ): string {
		unset( $action, $name, $referer, $display );
		return '';
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( string $text = 'Save Changes', string $type = 'primary', string $name = 'submit', bool $wrap = true ): void {
		AtalWordPressStubState::$calls[] = array( 'submit_button', $text, $type, $name, $wrap );
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( string $action_url, int|string $action = -1, string $name = '_wpnonce' ): string {
		unset( $action, $name );
		return $action_url;
	}
}

if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers(): void {
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, max( 1, $depth ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
		$text = strip_tags( $text );
		return $remove_breaks ? preg_replace( '/[\r\n\t ]+/', ' ', $text ) ?? $text : $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $text ): string { return trim( strip_tags( $text ) ); }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $text ): string { return trim( strip_tags( $text ) ); }
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( string $plugin ): bool { unset( $plugin ); return false; }
}

if ( ! function_exists( 'get_plugin_data' ) ) {
	/** @return array<string,string> */ function get_plugin_data( string $file, bool $markup = true, bool $translate = true ): array { unset( $file, $markup, $translate ); return array(); }
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( int $post_id ): string|false { return 0 < $post_id ? 'attachment' : false; }
}

if ( ! function_exists( 'wp_attachment_is_image' ) ) {
	function wp_attachment_is_image( int $post_id = 0 ): bool { unset( $post_id ); return true; }
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * @param array<string,mixed> $args Query arguments.
	 * @return list<int>
	 */
	function get_posts( array $args = array() ): array { unset( $args ); return array(); }
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/** @param array<string,mixed> $postarr */ function wp_insert_post( array $postarr = array(), bool $wp_error = false ): int|WP_Error { return $wp_error && true === ( $postarr['synthetic_error'] ?? false ) ? new WP_Error() : 101; }
}

if ( ! function_exists( 'wp_update_post' ) ) {
	/** @param array<string,mixed> $postarr */ function wp_update_post( array $postarr = array(), bool $wp_error = false ): int|WP_Error { if ( $wp_error && true === ( $postarr['synthetic_error'] ?? false ) ) { return new WP_Error(); } return isset( $postarr['ID'] ) && is_numeric( $postarr['ID'] ) ? (int) $postarr['ID'] : 101; }
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = '' ): int|bool { AtalWordPressStubState::$calls[]=array('update_post_meta',$post_id,$meta_key,$meta_value); AtalWordPressStubState::$post_meta[ $post_id ][ $meta_key ] = $meta_value; return 'return-int'===$prev_value?1:true; }
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed { unset( $single ); if ( '' === $key ) { return AtalWordPressStubState::$post_meta[ $post_id ] ?? array(); } return AtalWordPressStubState::$post_meta[ $post_id ][ $key ] ?? ''; }
}

if ( ! function_exists( 'metadata_exists' ) ) {
	function metadata_exists( string $meta_type, int $object_id, string $meta_key ): bool { unset( $meta_type ); return array_key_exists( $meta_key, AtalWordPressStubState::$post_meta[ $object_id ] ?? array() ); }
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( int $post_id, string $meta_key, mixed $meta_value = '' ): bool { unset( $meta_value ); if ( ! isset( AtalWordPressStubState::$post_meta[ $post_id ] ) || ! array_key_exists( $meta_key, AtalWordPressStubState::$post_meta[ $post_id ] ) ) { return false; } unset( AtalWordPressStubState::$post_meta[ $post_id ][ $meta_key ] ); return true; }
}

if ( ! function_exists( 'set_post_thumbnail' ) ) {
	function set_post_thumbnail( int $post_id, int $thumbnail_id ): int|bool { AtalWordPressStubState::$calls[]=array('set_post_thumbnail',$post_id,$thumbnail_id); AtalWordPressStubState::$thumbnails[ $post_id ] = $thumbnail_id; return PHP_INT_MAX===$thumbnail_id?1:true; }
}

if ( ! function_exists( 'delete_post_thumbnail' ) ) {
	function delete_post_thumbnail( int $post_id ): bool { AtalWordPressStubState::$calls[]=array('delete_post_thumbnail',$post_id); unset( AtalWordPressStubState::$thumbnails[ $post_id ] ); return 0 <= $post_id; }
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( int $post_id = 0 ): int|false { return 0 > $post_id ? false : ( AtalWordPressStubState::$thumbnails[ $post_id ] ?? 0 ); }
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( int $post_id = 0 ): ?WP_Post { return 0 > $post_id?null:new WP_Post(); }
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	/** @param string|array<int|string,string> $post_type */
	function get_page_by_path( string $page_path, string $output = 'OBJECT', string|array $post_type = 'page' ): ?WP_Post { unset( $output, $post_type ); return '' === $page_path ? new WP_Post() : null; }
}

if ( ! function_exists( 'wp_safe_remote_get' ) ) {
	/**
	 * @param array<string,mixed> $args Request arguments.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	function wp_safe_remote_get( string $url, array $args = array() ): array|WP_Error { unset( $args ); return '' === $url ? new WP_Error() : array( 'response' => array( 'code' => 200 ), 'body' => '{}' ); }
}

if ( ! function_exists( 'wp_safe_remote_post' ) ) {
	/**
	 * @param array<string,mixed> $args Request arguments.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	function wp_safe_remote_post( string $url, array $args = array() ): array|WP_Error { unset( $args ); return '' === $url ? new WP_Error() : array( 'response' => array( 'code' => 200 ), 'body' => '{}' ); }
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool { return $thing instanceof WP_Error; }
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/** @param array<string,mixed>|WP_Error $response */
	function wp_remote_retrieve_response_code( array|WP_Error $response ): int {
		if ( ! is_array( $response ) ) { return 0; }
		$metadata = $response['response'] ?? null;
		if ( ! is_array( $metadata ) ) { return 0; }
		$code = $metadata['code'] ?? null;
		return is_numeric( $code ) ? (int) $code : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/** @param array<string,mixed>|WP_Error $response */
	function wp_remote_retrieve_body( array|WP_Error $response ): string { return is_array( $response ) && is_string( $response['body'] ?? null ) ? $response['body'] : ''; }
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( int $post_id = 0, bool $force_delete = false ): WP_Post|false|null { unset( $force_delete ); if ( 0 > $post_id ) { return false; } return 0===$post_id?null:new WP_Post(); }
}

if ( ! function_exists( 'wp_delete_attachment' ) ) {
	function wp_delete_attachment( int $post_id, bool $force_delete = false ): WP_Post|false|null { return wp_delete_post( $post_id, $force_delete ); }
}

if ( ! function_exists( 'wp_tempnam' ) ) {
	function wp_tempnam( string $filename = '', string $dir = '' ): string|false { unset( $filename ); return tempnam( '' === $dir ? sys_get_temp_dir() : $dir, 'atal-' ); }
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	/** @return array{path:string,url:string,subdir:string,basedir:string,baseurl:string,error:false|string} */
	function wp_upload_dir(): array { $path = sys_get_temp_dir(); return array( 'path' => $path, 'url' => 'https://example.test/wp-content/uploads', 'subdir' => '', 'basedir' => $path, 'baseurl' => 'https://example.test/wp-content/uploads', 'error' => false ); }
}

if ( ! function_exists( 'wp_upload_bits' ) ) {
	/** @return array{file:string,url:string,type:string,error:false|string} */
	function wp_upload_bits( string $name, ?string $deprecated, string $bits, ?string $time = null ): array { unset( $deprecated, $bits, $time ); return array( 'file' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name, 'url' => 'https://example.test/wp-content/uploads/' . $name, 'type' => 'image/webp', 'error' => false ); }
}

if ( ! function_exists( 'wp_insert_attachment' ) ) {
	/** @param array<string,mixed> $args */
	function wp_insert_attachment( array $args, string|false $file = false, int $parent_post_id = 0, bool $wp_error = false, bool $fire_after_hooks = true ): int|WP_Error { unset( $file, $parent_post_id, $fire_after_hooks ); return wp_insert_post( $args, $wp_error ); }
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/** @phpstan-impure */ function wp_delete_file( string $file ): void { AtalWordPressStubState::$calls[] = array( 'wp_delete_file', $file ); if ( is_file( $file ) ) { unlink( $file ); } }
}

if ( ! function_exists( 'get_post_mime_type' ) ) {
	function get_post_mime_type( int|WP_Post|null $post = null ): string|false { return is_int( $post ) && 0 > $post ? false : 'image/webp'; }
}

if ( ! function_exists( 'get_attached_file' ) ) {
	function get_attached_file( int $attachment_id, bool $unfiltered = false ): string|false { unset( $unfiltered ); return 0 > $attachment_id ? false : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'image.webp'; }
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( int $attachment_id = 0 ): string|false { return 0 < $attachment_id ? 'https://example.test/wp-content/uploads/image.webp' : false; }
}

if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
	/** @return array<string,mixed> */
	function wp_generate_attachment_metadata( int $attachment_id, string $file ): array { unset( $attachment_id, $file ); return array( 'width' => 1200, 'height' => 630, 'file' => 'image.webp' ); }
}

if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
	/** @param array<string,mixed> $data */
	function wp_update_attachment_metadata( int $attachment_id, array $data ): int|bool { unset( $attachment_id ); return true === ( $data['return_int'] ?? false ) ? 1 : true; }
}

if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( string $type = 'post', string $perm = '' ): object { unset( $type, $perm ); return (object) array( 'publish' => 0, 'draft' => 0 ); }
}

if ( ! function_exists( 'rest_get_server' ) ) {
	function rest_get_server(): WP_REST_Server { return new WP_REST_Server(); }
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
