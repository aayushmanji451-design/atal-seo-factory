<?php
/**
 * Task 06 admin safety tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Topics;

use Atal\SeoFactory\Admin\Task06Panel;
use Atal\Tests\Support\Topics\InMemoryRotationStateStore;
use Atal\Topics\Application\DeterministicRotation;
use AtalWordPressStubState;
use RuntimeException;

/**
 * Proves both capability and nonce checks run before preview work.
 */
final class Task06PanelSecurityTest extends TopicTestCase {

	protected function tearDown(): void {
		AtalWordPressStubState::$current_user_can = true;
		AtalWordPressStubState::$nonce_valid      = true;
	}

	public function test_preview_rejects_user_without_manage_options(): void {
		AtalWordPressStubState::$current_user_can = false;
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'permission' );
		$this->panel()->preview();
	}

	public function test_preview_rejects_invalid_nonce(): void {
		AtalWordPressStubState::$nonce_valid = false;
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'could not be verified' );
		$this->panel()->preview();
	}

	private function panel(): Task06Panel {
		return new Task06Panel( new DeterministicRotation( new InMemoryRotationStateStore() ), $this->policy, $this->validator );
	}
}
