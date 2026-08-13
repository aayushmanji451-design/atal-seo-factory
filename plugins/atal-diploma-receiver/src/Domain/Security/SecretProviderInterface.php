<?php
/** HMAC secret boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Security;

interface SecretProviderInterface {
	public function secret(): string;
}
