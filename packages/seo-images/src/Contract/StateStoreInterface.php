<?php
/** Task 05 snapshot state boundary. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Contract;

interface StateStoreInterface {
	/** @return array<string,mixed>|null */ public function load( string $article_key ): ?array;
	/** @param array<string,mixed> $state */ public function save( string $article_key, array $state ): void;
}
