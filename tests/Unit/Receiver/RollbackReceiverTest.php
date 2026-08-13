<?php
/** Remote recovery contract tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Receiver;

use Atal\DiplomaReceiver\Application\Receiver\ArticleReceiver;
use Atal\DiplomaReceiver\Application\Receiver\RollbackReceiver;
use Atal\DiplomaReceiver\Application\Validation\PayloadValidator;
use Atal\Tests\Support\Receiver\{InMemoryReceiptStore, TestAioseo, TestAudit, TestCourseCatalog, TestImages, TestPosts, TestTransactions};
final class RollbackReceiverTest extends ReceiverTestCase {
	public function test_authenticated_recovery_removes_fixture_mutation_once(): void {
		$receipts     = new InMemoryReceiptStore();
		$transactions = new TestTransactions();
		$posts        = new TestPosts();
		$audit        = new TestAudit();
		$receiver     = new ArticleReceiver( $this->authenticator, new PayloadValidator( new TestCourseCatalog() ), $receipts, $transactions, $posts, new TestAioseo(), new TestImages(), $audit );
		$body         = $this->body( $this->payload() );
		$accepted     = $receiver->receive( $this->signed( $body ), $this->payload() );
		$recovery     = $accepted['recovery'] ?? null;
		self::assertIsArray( $recovery );
		$token = $recovery['token'] ?? null;
		self::assertIsString( $token );
		$payload       = array(
			'article_key'    => 'article_task03_unit_0001',
			'recovery_token' => $token,
			'schema_version' => '1.0',
			'target_site'    => 'atal_diploma',
		);
		$rollback_body = $this->body( $payload );
		$rollback      = new RollbackReceiver( $this->authenticator, $receipts, $transactions, $posts, $audit );
		$response      = $rollback->recover( $this->signed( $rollback_body, 'nonce_task03_rollback_123456', 'idem_task03_rollback_123456', '/atal-diploma-receiver/v1/articles/rollback' ), $payload );
		self::assertSame( 'recovered', $response['status'] );
		self::assertSame( 1, $posts->recoveries );
		self::assertSame( 0, $posts->writes ); }
}
