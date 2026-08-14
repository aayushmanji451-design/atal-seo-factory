<?php
/** Signed Diploma receiver boundary. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Domain\Canary;

interface DiplomaReceiverClientInterface {
	/** @return array<string,mixed> */ public function send( CanaryArticle $article ): array;
	/** @return array<string,mixed> */ public function rollback( string $article_key, string $recovery_token ): array;
	/** @return list<string> */ public function contacted_hosts(): array;
}
