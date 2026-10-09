<?php
/**
 * Summary of a single bounded job execution step.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Application;

use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobResult;

/**
 * Immutable report of one step run by the queue worker.
 */
final class JobExecutionSummary {

	/**
	 * Create summary.
	 *
	 * @param Job       $job     Job that was processed.
	 * @param JobResult $result  Outcome of handler.
	 * @param int       $started Start epoch.
	 * @param int       $ended   End epoch.
	 * @param string    $status  Execution outcome (succeeded, retrying, dead, deferred, paused).
	 */
	public function __construct(
		public readonly Job $job,
		public readonly JobResult $result,
		public readonly int $started,
		public readonly int $ended,
		public readonly string $status
	) {
	}

	/**
	 * Export to array.
	 *
	 * @return array{job_key:string,item_key:string,stage:string,status:string,duration_ms:int,result_code:string}
	 */
	public function to_array(): array {
		return array(
			'job_key'     => $this->job->job_key,
			'item_key'    => $this->job->item_key,
			'stage'       => $this->job->stage,
			'status'      => $this->status,
			'duration_ms' => max( 0, ( $this->ended - $this->started ) * 1000 ),
			'result_code' => $this->result->code,
		);
	}
}
