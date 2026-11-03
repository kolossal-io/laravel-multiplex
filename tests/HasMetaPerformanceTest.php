<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Tests\Mocks\BackedEnum;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\PostCountingMetaLookups;
use Kolossal\Multiplex\Tests\Mocks\PostWithoutSoftDelete;
use Kolossal\Multiplex\Tests\Traits\AccessesProtectedProperties;
use Mattiasgeniar\PhpunitQueryCountAssertions\AssertsQueryCounts;

uses(RefreshDatabase::class);
uses(AssertsQueryCounts::class);
uses(AccessesProtectedProperties::class);

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

it('will not query again when reading the same meta repeatedly', function () {
    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => 'bar']))
        ->create();

    $this->assertQueryCountMatches(2, function () {
        $post = Post::first();

        for ($i = 0; $i < 50; $i++) {
            expect($post->foo)->toBe('bar');
            expect($post->getMeta('foo'))->toBe('bar');
            expect($post->hasMeta('foo'))->toBeTrue();
            expect($post->hasMeta('undefined'))->toBeFalse();
        }
    });
});

it('will not query again when reading eager loaded meta repeatedly', function () {
    Post::factory(5)
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => 'bar']))
        ->create();

    $this->assertQueryCountMatches(2, function () {
        Post::with('meta')->get()->each(function (Post $post) {
            for ($i = 0; $i < 10; $i++) {
                expect($post->foo)->toBe('bar');
                expect($post->undefined)->toBeNull();
            }
        });
    });
});

it('returns the first matching meta if a key occurs more than once', function () {
    $post = Post::factory()->create();

    $post->setRelation('meta', new Collection([
        Meta::make(['key' => 'foo', 'value' => 'first']),
        Meta::make(['key' => 'foo', 'value' => 'second']),
    ]));

    expect($post->foo)->toBe('first')
        ->and($post->getMeta('foo'))->toBe('first')
        ->and($post->findMeta('foo')->value)->toBe('first');
});

it('updates the meta lookup when the relation is replaced', function () {
    $post = Post::factory()->create();

    $post->setRelation('meta', new Collection([Meta::make(['key' => 'foo', 'value' => 'old'])]));

    expect($post->foo)->toBe('old');

    $post->setRelation('meta', new Collection([Meta::make(['key' => 'foo', 'value' => 'new'])]));

    expect($post->foo)->toBe('new');

    $post->unsetRelation('meta');

    expect($post->foo)->toBeNull();
});

it('updates the meta lookup when items are added to the loaded relation', function () {
    $post = Post::factory()->create();

    expect($post->foo)->toBeNull();

    $post->meta->push(Meta::make(['key' => 'foo', 'value' => 'pushed']));

    expect($post->foo)->toBe('pushed');
});

it('updates the meta lookup when the relation is reloaded', function () {
    $post = Post::factory()->create();
    $post->saveMeta('foo', 'old');

    expect($post->foo)->toBe('old');

    Meta::query()->update(['value' => 'new']);

    expect($post->foo)->toBe('old')
        ->and($post->load('meta')->foo)->toBe('new');

    Meta::query()->update(['value' => 'newer']);

    expect($post->refreshMetaRelations()->foo)->toBe('newer');
});

it('updates the meta lookup when traveling in time', function () {
    $post = Post::factory()->create();

    $post->saveMetaAt('foo', 'past', '-2 days');
    $post->saveMetaAt('foo', 'present', '-1 hour');
    $post->saveMetaAt('foo', 'future', '+1 day');

    expect($post->foo)->toBe('present')
        ->and($post->withMetaAt('-1 day')->foo)->toBe('past')
        ->and($post->withMetaAt('+2 days')->foo)->toBe('future')
        ->and($post->setMetaTimestamp(Carbon::now()->subDays(3))->foo)->toBeNull()
        ->and($post->withCurrentMeta()->foo)->toBe('present');
});

it('updates the meta lookup when meta is saved, deleted or purged', function () {
    $post = Post::factory()->create();

    $post->saveMeta('foo', 'old');
    $post->saveMeta('bar', 'baz');

    expect($post->foo)->toBe('old');

    $post->foo = 'new';
    $post->save();

    expect($post->foo)->toBe('new');

    $post->saveMeta('foo', 'newer');

    expect($post->foo)->toBe('newer');

    $post->deleteMeta('foo');

    expect($post->foo)->toBeNull()
        ->and($post->bar)->toBe('baz');

    $post->purgeMeta();

    expect($post->bar)->toBeNull()
        ->and($post->hasMeta('bar'))->toBeFalse();
});

it('looks up meta only once per attribute access', function (string $key, mixed $expected) {
    $post = PostCountingMetaLookups::query()->forceCreate([]);
    $post->saveMeta('foo', 'bar');
    $post->saveMeta('empty', null);

    DB::table('sample_posts')->update(['title' => 'Column']);

    $post = PostCountingMetaLookups::first();

    expect($post->{$key})->toBe($expected)
        ->and($post->metaLookups)->toBe(1);
})->with([
    'existing meta' => ['foo', 'bar'],
    'existing meta with null value' => ['empty', null],
    'missing meta' => ['undefined', null],
    'missing meta with accessor' => ['defaulted', 'default'],
    'missing meta with fallback value' => ['title', 'Column'],
]);

it('does not look up meta for model attributes', function () {
    $post = PostCountingMetaLookups::query()->forceCreate(['body' => 'Body']);

    expect($post->body)->toBe('Body')
        ->and($post->id)->not->toBeNull()
        ->and($post->metaLookups)->toBe(0);
});

it('determines model attributes per instance casts', function () {
    $post = Post::factory()->create();
    $other = Post::factory()->create();

    expect($post->isModelAttribute('status'))->toBeFalse()
        ->and($post->isValidMetaKey('status'))->toBeTrue();

    $post->mergeCasts(['status' => BackedEnum::class]);

    expect($post->isModelAttribute('status'))->toBeTrue()
        ->and($post->isValidMetaKey('status'))->toBeFalse()
        ->and($other->isModelAttribute('status'))->toBeFalse()
        ->and($other->isValidMetaKey('status'))->toBeTrue()
        ->and($post->isModelAttribute('status'))->toBeTrue();
});

it('determines model attributes per instance attributes', function () {
    $post = Post::factory()->create();
    $other = Post::factory()->create();

    expect($post->isModelAttribute('dynamic'))->toBeFalse();

    $post->setRawAttributes(array_merge($post->getAttributes(), ['dynamic' => 'value']));

    expect($post->isModelAttribute('dynamic'))->toBeTrue()
        ->and($post->isValidMetaKey('dynamic'))->toBeFalse()
        ->and($other->isModelAttribute('dynamic'))->toBeFalse()
        ->and($other->isValidMetaKey('dynamic'))->toBeTrue();
});

it('respects unguarded meta and changed meta keys for model attributes', function () {
    $post = Post::factory()->create();

    expect($post->isModelAttribute('title'))->toBeTrue()
        ->and($post->isValidMetaKey('title'))->toBeFalse();

    Post::unguardMeta();

    expect($post->isValidMetaKey('title'))->toBeTrue();

    Post::reguardMeta();

    expect($post->isValidMetaKey('title'))->toBeFalse();

    $post->metaKeys(['title']);

    expect($post->isModelAttribute('title'))->toBeTrue()
        ->and($post->isValidMetaKey('title'))->toBeTrue()
        ->and(Post::factory()->create()->isValidMetaKey('title'))->toBeFalse();
});

it('caches an empty list of explicitly allowed meta keys', function () {
    $post = PostWithoutSoftDelete::factory()->create();

    expect($post->getExplicitlyAllowedMetaKeys())->toBe([])
        ->and($this->getProtectedProperty($post, 'explicitlyAllowedMetaKeys'))->toBe([])
        ->and($post->isExplicitlyAllowedMetaKey('title'))->toBeFalse();

    $post->metaKeys(['title']);

    expect($post->isExplicitlyAllowedMetaKey('title'))->toBeTrue();
});
