<?php
/** Exact REST error response tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Receiver;

use Atal\DiplomaReceiver\Application\Health\HealthDataProvider;
use Atal\DiplomaReceiver\Application\Migration\ReceiverStateStoreInterface;
use Atal\DiplomaReceiver\Application\Migration\SchemaDatabaseInterface;
use Atal\DiplomaReceiver\Application\Receiver\ArticleReceiver;
use Atal\DiplomaReceiver\Application\Receiver\RollbackReceiver;
use Atal\DiplomaReceiver\Application\Validation\PayloadValidator;
use Atal\DiplomaReceiver\Infrastructure\Database\TableNames;
use Atal\DiplomaReceiver\Rest\JsonPayloadDecoder;
use Atal\DiplomaReceiver\Rest\ReceiverController;
use Atal\Tests\Support\Receiver\{InMemoryReceiptStore, TestAioseo, TestAudit, TestCourseCatalog, TestImages, TestPosts, TestTransactions};
use WP_REST_Request;
final class ControllerErrorModelTest extends ReceiverTestCase {
	public function test_unsigned_request_has_exact_machine_readable_body(): void {
		$receipts     = new InMemoryReceiptStore();
		$transactions = new TestTransactions();
		$posts        = new TestPosts();
		$audit        = new TestAudit();
		$validator    = new PayloadValidator( new TestCourseCatalog() );
		$articles     = new ArticleReceiver( $this->authenticator, $validator, $receipts, $transactions, $posts, new TestAioseo(), new TestImages(), $audit );
		$rollback     = new RollbackReceiver( $this->authenticator, $receipts, $transactions, $posts, $audit );
		$health       = new HealthDataProvider( new ControllerDatabase(), new ControllerState(), new TableNames( 'wp_' ), new TestAioseo() );
		$controller   = new ReceiverController( $articles, $rollback, $this->authenticator, new JsonPayloadDecoder(), $health );
		$response     = $controller->receive( new WP_REST_Request( 'POST', '/atal-diploma-receiver/v1/articles', $this->body( $this->payload() ) ) );
		self::assertSame( 401, $response->get_status() );
		self::assertSame(
			array(
				'code'    => 'receiver_missing_auth',
				'message' => 'Required receiver authentication headers are missing.',
				'data'    => array(
					'status'  => 401,
					'details' => array(),
				),
			),
			$response->get_data()
		); }
}
final class ControllerDatabase implements SchemaDatabaseInterface {
	public function prefix(): string {
		return 'wp_';
	} public function charset_collate(): string {
		return '';
	} public function create_or_update( string $sql ): void {
		unset( $sql );
	} public function drop( string $table ): void {
		unset( $table );
	} public function exists( string $table ): bool {
		unset( $table );
		return true; }
}
final class ControllerState implements ReceiverStateStoreInterface {
	public function database_version(): int {
		return 1;
	} public function set_database_version( int $version ): void {
		unset( $version );
	} public function record_plugin_version( string $version ): void {
		unset( $version ); } public function ensure_secret(): void {}
}
