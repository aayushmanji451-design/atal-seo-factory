<?php
/** WordPress fixture repository idempotency tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\SeoImages;

use Atal\SeoImages\Domain\AcceptanceFixture;
use Atal\SeoImages\Infrastructure\WordPress\WordPressFixtureRepository;
use PHPUnit\Framework\TestCase;

final class WordPressFixtureRepositoryTest extends TestCase {
	public function test_assigning_existing_featured_image_is_successful_no_op(): void {
		$fixture                                    = new AcceptanceFixture(
			'atal_institute',
			'liveup2.atalinstitute.com',
			14557,
			'article_task04_atal_institute_general_duty_assistant_course_overview_v1',
			'institute_general_duty_assistant',
			'course_overview',
			'General Duty Assistant Course: Duration and Fees | ATAL Institute',
			'Explore the General Duty Assistant (GDA) course at ATAL Institute, with verified duration, fee, learning focus, and approved course information.',
			'General Duty Assistant course'
		);
		\AtalWordPressStubState::$post_meta[14557]  = array(
			'_atal_article_key' => $fixture->article_key(),
			'_atal_course_key'  => $fixture->course_key(),
		);
		\AtalWordPressStubState::$thumbnails[14557] = 14561;
		\AtalWordPressStubState::$post_types[14557] = 'post';
		$repository                                 = new WordPressFixtureRepository( '_atal_article_key', '_atal_course_key' );

		try {
			$repository->assign_featured_image( $fixture, 14561 );
			self::assertSame( 14561, \AtalWordPressStubState::$thumbnails[14557] );
		} finally {
			unset(
				\AtalWordPressStubState::$post_meta[14557],
				\AtalWordPressStubState::$thumbnails[14557],
				\AtalWordPressStubState::$post_types[14557]
			);
		}
	}
}
