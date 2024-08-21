<?php

use Illuminate\Support\Str;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Mattiasgeniar\PhpunitQueryCountAssertions\AssertsQueryCounts;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);
uses(Mattiasgeniar\PhpunitQueryCountAssertions\AssertsQueryCounts::class);

function getRawQueriesExecuted(): array
{
    return collect(AssertsQueryCounts::getQueriesExecuted())
        ->map(function ($item) {
            return Str::replaceArray('?', $item['bindings'], $item['query']);
        })
        ->toArray();
}

it('will not load meta relations by default', function () {
    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo']))
        ->create();

    $this->assertQueryCountMatches(1, function () {
        Post::first();
    });
});

it('will load meta relation if meta value is used', function () {
    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo']))
        ->create();

    $this->assertQueryCountMatches(2, function () {
        Post::first()->foo;
    });
});

it('will used cache meta on subsequent meta calls', function () {
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
});

it('will lazy load meta relations by default', function () {
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
});

it('can eager load meta relations', function () {
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
});
