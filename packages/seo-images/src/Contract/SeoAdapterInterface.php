<?php
/** Native SEO adapter boundary. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Contract;

use Atal\SeoImages\Domain\SeoMetadata;

interface SeoAdapterInterface {
	public function name(): string;
	public function detected(): bool;
	public function version(): ?string;
	/** @return array<string,mixed> */ public function snapshot( int $post_id ): array;
	/** @return array<string,mixed> */ public function apply_and_verify( int $post_id, SeoMetadata $metadata ): array;
	/** @return array<string,mixed> */ public function verify( int $post_id, SeoMetadata $metadata ): array;
	/** @param array<string,mixed> $snapshot */ public function restore( int $post_id, array $snapshot ): void;
}
