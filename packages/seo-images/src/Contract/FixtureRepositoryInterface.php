<?php
/** Controlled staging draft boundary. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Contract;

use Atal\SeoImages\Domain\AcceptanceFixture;

interface FixtureRepositoryInterface {
	/** @return array<string,mixed> */ public function snapshot( AcceptanceFixture $fixture ): array;
	public function assign_featured_image( AcceptanceFixture $fixture, int $attachment_id ): void;
	public function verify_featured_image( AcceptanceFixture $fixture, int $attachment_id ): void;
	/** @param array<string,mixed> $snapshot */ public function restore_featured_image( AcceptanceFixture $fixture, array $snapshot ): void;
}
