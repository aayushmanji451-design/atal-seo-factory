<?php
/** Featured-image attachment guard. @package AtalDiplomaReceiver */
declare(strict_types=1);
namespace Atal\DiplomaReceiver\Infrastructure\WordPress;

use Atal\DiplomaReceiver\Application\Error\ReceiverException;
use Atal\DiplomaReceiver\Domain\Receiver\FeaturedImageVerifierInterface;
final class WordPressFeaturedImageVerifier implements FeaturedImageVerifierInterface {
	public function verify( ?int $attachment_id ): void {
		if ( null === $attachment_id ) {
			return;
		} if ( 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
			throw new ReceiverException( 'receiver_featured_image_invalid', 'The featured image is not a valid image attachment.', 422, array( 'field' => 'featured_image_id' ) ); } }
}
