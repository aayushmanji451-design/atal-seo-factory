<?php
/** Local image persistence boundary. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\SeoImages\Contract;

use Atal\SeoImages\Domain\ImageResult;
use Atal\SeoImages\Domain\ImageSpecification;

interface ImageManagerInterface {
	public function ensure( ImageSpecification $specification ): ImageResult;
	public function verify( ImageResult $result ): void;
	/** @param list<int> $protected_ids */ public function delete_if_orphan( ImageResult $result, array $protected_ids ): bool;
}
