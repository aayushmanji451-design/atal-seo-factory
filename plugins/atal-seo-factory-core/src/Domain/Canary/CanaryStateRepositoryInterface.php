<?php
/** Core canary state boundary. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Domain\Canary;

interface CanaryStateRepositoryInterface {
	/** @return array{status:string,payload:array<string,mixed>}|null */ public function find( string $article_key ): ?array;
	/** @param array<string,mixed> $payload */ public function save( CanaryArticle $article, string $status, array $payload ): void;
}
