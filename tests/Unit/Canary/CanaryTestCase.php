<?php
/** Shared Task 04 canary test construction. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Canary;

use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\SeoFactory\Application\Canary\CanaryContentValidator;
use Atal\SeoFactory\Application\Canary\CanaryCoordinator;
use Atal\SeoFactory\Application\Canary\CanaryJsonImporter;
use Atal\SeoFactory\Application\Canary\CanonicalCanaryArticleBuilder;
use Atal\SeoFactory\Domain\Canary\CanaryArticle;
use Atal\Tests\Support\Canary\CanaryTransactions;
use Atal\Tests\Support\Canary\FakeDiplomaClient;
use Atal\Tests\Support\Canary\InMemoryCanaryAudit;
use Atal\Tests\Support\Canary\InMemoryCanaryPosts;
use Atal\Tests\Support\Canary\InMemoryCanaryState;
use Atal\Tests\Support\Canary\PassingCanaryRuntime;
use PHPUnit\Framework\TestCase;

abstract class CanaryTestCase extends TestCase {
	protected function importer(): CanaryJsonImporter {
		return new CanaryJsonImporter(); }
	protected function builder(): CanonicalCanaryArticleBuilder {
		$root = dirname( __DIR__, 3 );
		return new CanonicalCanaryArticleBuilder( $root . '/data/master', $root . '/data/schemas', KnowledgeValidator::create_default() );
	}
	protected function json( string $site, int $image_id = 501 ): string {
		$value = json_decode( $this->importer()->template( $site ), true );
		self::assertIsArray( $value );
		$value['featured_image_id'] = $image_id;
		return (string) json_encode( $value, JSON_UNESCAPED_SLASHES );
	}
	/** @return array{coordinator:CanaryCoordinator,posts:InMemoryCanaryPosts,states:InMemoryCanaryState,audit:InMemoryCanaryAudit,client:FakeDiplomaClient,runtime:PassingCanaryRuntime,transactions:CanaryTransactions} */
	protected function coordinator(): array {
		$posts        = new InMemoryCanaryPosts();
		$states       = new InMemoryCanaryState();
		$audit        = new InMemoryCanaryAudit();
		$client       = new FakeDiplomaClient();
		$runtime      = new PassingCanaryRuntime();
		$transactions = new CanaryTransactions();
		$coordinator  = new CanaryCoordinator( $this->importer(), $this->builder(), new CanaryContentValidator(), $runtime, $posts, $states, $audit, $client, $transactions );
		return compact( 'coordinator', 'posts', 'states', 'audit', 'client', 'runtime', 'transactions' );
	}
	protected function with_content( CanaryArticle $article, string $content ): CanaryArticle {
		return new CanaryArticle(
			$article->article_key(),
			$article->course_key(),
			$article->target_site(),
			$article->intent_key(),
			$article->option_key(),
			$article->title(),
			$article->h1(),
			$article->slug(),
			$article->excerpt(),
			$content,
			$article->seo_title(),
			$article->meta_description(),
			$article->focus_keyword(),
			$article->duration(),
			$article->fee(),
			$article->internal_link(),
			$article->image_asset_key(),
			$article->featured_image_id(),
			$article->source_refs()
		);
	}
}
