<?php
/** Exact staging safety boundary. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Contract;

use Atal\SeoImages\Domain\AcceptanceFixture;

interface RuntimeGuardInterface {
	public function assert_ready( AcceptanceFixture $fixture ): void;
}
