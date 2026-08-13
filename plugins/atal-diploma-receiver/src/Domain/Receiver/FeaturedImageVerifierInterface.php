<?php
/** Featured-image verification boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Receiver;

interface FeaturedImageVerifierInterface {
	public function verify( ?int $attachment_id ): void;
}
