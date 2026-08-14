<?php
/** Local Task 04 draft boundary. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoFactory\Domain\Canary;

interface CanaryPostServiceInterface {
	public function create_draft( CanaryArticle $article ): CanaryMutation;
	/** @return array<string,mixed> */ public function verify_draft( CanaryArticle $article, int $post_id ): array;
	/** @param array<string,mixed>|null $previous_state */ public function rollback( int $post_id, bool $created, ?array $previous_state ): void;
}
