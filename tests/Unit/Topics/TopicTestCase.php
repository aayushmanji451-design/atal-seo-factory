<?php
/**
 * Task 06 test case.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Topics;

use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\Tests\Support\Topics\InMemoryTopicRegistry;
use Atal\Topics\Application\CanonicalTopicPolicy;
use Atal\Topics\Application\Similarity;
use Atal\Topics\Application\TopicValidator;
use Atal\Topics\Domain\TopicIdentity;
use Atal\Topics\Domain\TopicProposal;
use PHPUnit\Framework\TestCase;

/**
 * Builds canonical proposals from the approved master package.
 */
abstract class TopicTestCase extends TestCase {

	protected string $root;

	protected CanonicalTopicPolicy $policy;

	protected InMemoryTopicRegistry $registry;

	protected TopicValidator $validator;

	protected function setUp(): void {
		$this->root      = dirname( __DIR__, 3 );
		$this->policy    = new CanonicalTopicPolicy( KnowledgePackage::from_directory( $this->root . '/data/master' ) );
		$this->registry  = new InMemoryTopicRegistry();
		$this->validator = new TopicValidator( $this->policy, KnowledgeValidator::create_default(), $this->registry, new Similarity(), $this->root . '/data/master', $this->root . '/data/schemas' );
	}

	/**
	 * Build a canonical, structurally valid proposal.
	 *
	 * @param string               $course_key Canonical course key.
	 * @param string               $intent     Intent key.
	 * @param array<string,mixed>  $changes    Field overrides.
	 */
	protected function proposal( string $course_key, string $intent = 'course_overview', array $changes = array() ): TopicProposal {
		$course = $this->policy->course( $course_key );
		self::assertIsArray( $course );
		$site     = $course['target_site'];
		$duration = $course['duration'];
		$fee      = $course['fee'];
		self::assertIsString( $site );
		self::assertIsArray( $duration );
		self::assertIsArray( $fee );
		self::assertIsString( $duration['display'] );
		self::assertIsString( $fee['display'] );
		$link = $this->policy->expected_internal_link( $course_key, $site );
		self::assertIsString( $link );
		$keyword = str_replace( '_', ' ', $course_key ) . ' course';
		$data    = array(
			'target_site'     => $site,
			'course_key'      => $course_key,
			'intent'          => $intent,
			'primary_keyword' => $keyword,
			'year'            => 2026,
			'title'           => $keyword . ' | Verified Guide',
			'slug'            => $course_key . '-' . $intent . '-2026',
			'headings'        => array( $keyword . ' Overview', 'Verified Course Facts', 'Conclusion' ),
			'paragraphs'      => array( 'This guide explains the approved course identity and learning focus.', 'Canonical duration and fee values are presented from the master data.' ),
			'faqs'            => array( 'Where are details listed? The mapped official course page contains current details.' ),
			'internal_links'  => array( $link ),
			'facts'           => array(
				'duration' => $duration['display'],
				'fee'      => $fee['display'],
			),
		);

		return TopicProposal::from_array( array_replace( $data, $changes ) );
	}
}
