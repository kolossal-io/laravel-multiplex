<?php

namespace Kolossal\Multiplex\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Mattiasgeniar\PhpunitQueryCountAssertions\AssertsQueryCounts;

class HasMetaPerformanceTest extends TestCase
{
    use AssertsQueryCounts;
    use RefreshDatabase;

    protected function getRawQueriesExecuted(): array
    {
        return collect(AssertsQueryCounts::getQueriesExecuted())
            ->map(function ($item) {
                return Str::replaceArray('?', $item['bindings'], $item['query']);
            })
            ->toArray();
    }

    /** @test */
    public function it_will_not_load_meta_relations_by_default()
    {
        Post::factory()
            ->has(Meta::factory()->state(['key' => 'foo']))
            ->create();

        $this->assertQueryCountMatches(1, function () {
            Post::first();
        });
    }

    /** @test */
    public function it_will_load_meta_relation_if_meta_value_is_used()
    {
        Post::factory()
            ->has(Meta::factory()->state(['key' => 'foo']))
            ->create();

        $this->assertQueryCountMatches(2, function () {
            Post::first()->foo;
        });
    }

    /** @test */
    public function it_will_used_cache_meta_on_subsequent_meta_calls()
    {
        Post::factory()
            ->has(Meta::factory()->state(['key' => 'foo']))
            ->has(Meta::factory()->state(['key' => 'bar']))
            ->has(Meta::factory()->state(['key' => 'another']))
            ->create();

        $this->assertQueryCountMatches(2, function () {
            $post = Post::first();

            $post->foo;
            $post->getMeta('bar');
            $post->getMeta('undefined');
            $post->another;
        });
    }

    /** @test */
    public function it_will_lazy_load_meta_relations_by_default()
    {
        Post::factory(20)
            ->has(Meta::factory(3))
            ->create();

        $this->assertDatabaseCount('sample_posts', 20);
        $this->assertDatabaseCount('meta', 60);

        $this->assertQueryCountMatches(1, function () {
            Post::get()->each(function ($post) {
                $post->title;
            });
        });

        $this->assertQueryCountMatches(21, function () {
            Post::get()->each(function ($post) {
                $post->foo;
                $post->getMeta('bar');
                $post->getMeta('undefined');
                $post->another;
            });
        });
    }

    /** @test */
    public function it_can_eager_load_meta_relations()
    {
        Post::factory(20)
            ->has(Meta::factory(3))
            ->create();

        $this->assertDatabaseCount('sample_posts', 20);
        $this->assertDatabaseCount('meta', 60);

        $this->assertQueryCountMatches(2, function () {
            Post::with('meta')->get()->each(function ($post) {
                $post->title;
            });
        });

        $this->assertQueryCountMatches(2, function () {
            Post::with('meta')->get()->each(function ($post) {
                $post->foo;
                $post->getMeta('bar');
                $post->getMeta('undefined');
                $post->another;
            });
        });
    }
}
