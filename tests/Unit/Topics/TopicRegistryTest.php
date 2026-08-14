<?php
/**
 * Transactional topic registry tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Topics;

use Atal\Tests\Support\Topics\InMemoryTopicRegistry;
use Atal\Topics\Application\TopicRegistry;
use Atal\Topics\Domain\PublishedTopic;
use Atal\Topics\Domain\QualityState;
use Atal\Topics\Domain\RegistryResult;
use Atal\Topics\Domain\ValidationFinding;
use Atal\Topics\Domain\ValidationReport;
use RuntimeException;

/**
 * Proves deterministic insert/update/no-op and rollback behavior.
 */
final class TopicRegistryTest extends TopicTestCase {

	public function test_insert_and_identical_retry_are_idempotent(): void {
		$proposal = $this->proposal( 'institute_general_duty_assistant' );
		$topic    = new PublishedTopic( $proposal );
		$service  = new TopicRegistry( $this->registry );
		$report   = $this->validator->validate( $proposal );

		self::assertSame( RegistryResult::INSERTED, $service->register( $topic, $report )->status() );
		self::assertSame( RegistryResult::UNCHANGED, $service->register( $topic, $report )->status() );
		self::assertCount( 1, $this->registry->all() );
		self::assertSame( array( 'BEGIN', 'COMMIT' ), $this->registry->transactions );
	}

	public function test_same_identity_is_updated_without_a_duplicate_row(): void {
		$first   = $this->proposal( 'institute_general_duty_assistant' );
		$service = new TopicRegistry( $this->registry );
		self::assertSame( RegistryResult::INSERTED, $service->register( new PublishedTopic( $first ), $this->validator->validate( $first ) )->status() );

		$updated = $this->proposal( 'institute_general_duty_assistant', 'course_overview', array( 'title' => $first->title() . ' 2026' ) );
		self::assertSame( RegistryResult::UPDATED, $service->register( new PublishedTopic( $updated ), $this->validator->validate( $updated ) )->status() );
		self::assertCount( 1, $this->registry->all() );
	}

	public function test_failed_save_rolls_back_transaction(): void {
		$repository            = new InMemoryTopicRegistry();
		$repository->fail_save = true;
		$service               = new TopicRegistry( $repository );
		$proposal              = $this->proposal( 'institute_general_duty_assistant' );

		try {
			$service->register( new PublishedTopic( $proposal ), $this->validator->validate( $proposal ) );
			self::fail( 'Expected the synthetic registry failure.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'Synthetic registry failure.', $exception->getMessage() );
		}
		self::assertSame( array( 'BEGIN', 'ROLLBACK' ), $repository->transactions );
	}

	public function test_nonpass_report_is_never_written(): void {
		$report = new ValidationReport(
			array( new ValidationFinding( 'synthetic.reject', QualityState::REJECTED, 'title', 'safe', 'unsafe', 'Synthetic rejection.', 'Use a safe title.' ) )
		);
		$this->expectException( RuntimeException::class );
		( new TopicRegistry( $this->registry ) )->register( new PublishedTopic( $this->proposal( 'institute_general_duty_assistant' ) ), $report );
	}
}
