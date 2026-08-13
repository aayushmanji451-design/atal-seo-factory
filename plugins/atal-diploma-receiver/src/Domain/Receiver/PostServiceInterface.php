<?php
/** Receiver-owned draft boundary. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Domain\Receiver;

interface PostServiceInterface {
	public function upsert_draft( ArticlePayload $payload ): MutationResult;
	/** @param array<string,mixed>|null $previous_state */ public function recover( int $post_id, bool $created, ?array $previous_state ): void;
}
