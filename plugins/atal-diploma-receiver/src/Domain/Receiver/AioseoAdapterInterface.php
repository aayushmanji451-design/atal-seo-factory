<?php
/** AIOSEO environment boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Receiver;

interface AioseoAdapterInterface {
	public function detected(): bool;
	public function version(): ?string;
	/**
	 * @param array{title:string,description:string,focus_keyphrase:string} $payload Validated SEO payload.
	 *
	 * @return array{status:string,title:string,description:string,focus_keyphrase:string}
	 */
	public function write_and_verify( int $post_id, array $payload ): array;
	/** @return array<string,mixed> */
	public function snapshot( int $post_id ): array;
	/** @param array<string,mixed> $snapshot Native AIOSEO state. */
	public function restore( int $post_id, array $snapshot ): void;
}
