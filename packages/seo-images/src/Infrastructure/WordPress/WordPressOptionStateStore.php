<?php
/** Non-secret Task 05 option state. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Infrastructure\WordPress;

use Atal\SeoImages\Contract\StateStoreInterface;
use Atal\SeoImages\Exception\PipelineException;

final class WordPressOptionStateStore implements StateStoreInterface {
	public function __construct( private readonly string $option_name ) {}
	public function load( string $article_key ): ?array {
		$all = get_option( $this->option_name, array() );
		if ( ! is_array( $all ) || array_is_list( $all ) || ! is_array( $all[ $article_key ] ?? null ) || array_is_list( $all[ $article_key ] ) ) {
			return null;
		} /** @var array<string,mixed> $state */ $state = $all[ $article_key ];
		return $state; }
	public function save( string $article_key, array $state ): void {
		$all = get_option( $this->option_name, array() );
		if ( ! is_array( $all ) || array_is_list( $all ) ) {
			$all = array();
		} $all[ $article_key ] = $state;
		if ( false === update_option( $this->option_name, $all, false ) && get_option( $this->option_name, array() ) !== $all ) {
			throw new PipelineException( 'Task 05 state could not be stored.' ); } }
}
