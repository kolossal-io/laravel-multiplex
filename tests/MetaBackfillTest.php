<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Kolossal\Multiplex\Events\MetaHasBeenAdded;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Support\MetaBackfill;
use Kolossal\Multiplex\Tests\Mocks\Dummy;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\PostWithExistingColumn;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-03 12:00:00');
});

function insertBackfillRow(string $table, string $model, array $data = []): mixed
{
    $id = $model::make()->newUniqueId();

    $insertId = DB::table($table)->insertGetId(array_merge(
        $id ? ['id' => $id] : [],
        ['created_at' => '2020-01-01 10:00:00', 'updated_at' => '2020-01-01 10:00:00'],
        $data,
    ));

    return $id ?? $insertId;
}

function insertBackfillPost(array $data = []): mixed
{
    return insertBackfillRow('sample_posts_existing_column', PostWithExistingColumn::class, $data);
}

it('creates meta from typecast column values', function () {
    insertBackfillPost([
        'title' => 'Title',
        'body' => 'Body',
        'integer_field' => '120',
        'float_field' => 120,
        'boolean_field' => 1,
    ]);

    $keys = ['title', 'body', 'integer_field', 'float_field', 'boolean_field'];

    expect(PostWithExistingColumn::first()->only($keys))->each->toBeNull();

    expect(MetaBackfill::fromColumns(PostWithExistingColumn::class, $keys)->run())->toBe(5);

    /**
     * Change the columns to prove the values are read from meta now.
     */
    DB::table('sample_posts_existing_column')->update([
        'title' => 'Changed',
        'body' => 'Changed',
        'integer_field' => 1,
        'float_field' => 1,
        'boolean_field' => 0,
    ]);

    $post = PostWithExistingColumn::first();

    expect($post->only($keys))->toBe([
        'title' => 'Title',
        'body' => 'Body',
        'integer_field' => 120,
        'float_field' => 120.0,
        'boolean_field' => true,
    ])
        ->and($post->meta)->toHaveCount(5)
        ->and($post->findMeta('integer_field')->type)->toBe('integer')
        ->and($post->findMeta('boolean_field')->type)->toBe('boolean');
});

it('publishes meta at the time the row was created', function () {
    insertBackfillPost(['title' => 'Old', 'created_at' => '2020-01-01 10:00:00']);
    insertBackfillPost(['title' => 'Unknown', 'created_at' => null]);

    MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title'])->run();

    expect(Meta::orderBy('id')->pluck('published_at')->map->toDateTimeString()->all())->toBe([
        '2020-01-01 10:00:00',
        '2026-11-03 12:00:00',
    ]);
});

it('can publish meta at a given column or time', function () {
    insertBackfillPost(['title' => 'Title', 'updated_at' => '2021-06-01 08:00:00']);

    MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title'])->publishedAt('updated_at')->run();

    expect(Meta::first()->published_at->toDateTimeString())->toBe('2021-06-01 08:00:00');

    Meta::query()->delete();

    MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title'])
        ->publishedAt(Carbon::parse('2019-03-04 05:06:07'))
        ->run();

    expect(Meta::first()->published_at->toDateTimeString())->toBe('2019-03-04 05:06:07');

    Meta::query()->delete();

    MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title'])->publishedAt(null)->run();

    expect(Meta::first()->published_at->toDateTimeString())->toBe('2026-11-03 12:00:00');
});

it('skips null values', function () {
    insertBackfillPost(['title' => 'Title', 'body' => null]);
    insertBackfillPost(['title' => null, 'body' => '']);

    expect(MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title', 'body'])->run())->toBe(2);

    expect(Meta::orderBy('id')->get()->map->only('key', 'value')->all())->toBe([
        ['key' => 'title', 'value' => 'Title'],
        ['key' => 'body', 'value' => ''],
    ]);
});

it('will not create duplicates when running again', function () {
    insertBackfillPost(['title' => 'A']);
    insertBackfillPost(['title' => 'B']);

    $backfill = MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title']);

    expect($backfill->run())->toBe(2)
        ->and($backfill->run())->toBe(0)
        ->and(Meta::count())->toBe(2);

    insertBackfillPost(['title' => 'C']);

    expect($backfill->run())->toBe(1)
        ->and(Meta::count())->toBe(3);
});

it('keeps newer meta current and adds the column value to the history', function () {
    insertBackfillPost(['title' => 'Column']);

    $post = PostWithExistingColumn::first();
    $post->saveMetaAt('title', 'Meta', '2024-01-01 00:00:00');
    $post->saveMetaAt('title', 'Planned', '+1 week');

    MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title'])->run();

    $post = PostWithExistingColumn::first();

    expect($post->title)->toBe('Meta')
        ->and($post->withMetaAt('2022-01-01')->title)->toBe('Column')
        ->and($post->withMetaAt('+2 weeks')->title)->toBe('Planned')
        ->and($post->withCurrentMeta()->historicMeta->pluck('value')->all())->toBe(['Column']);
});

it('uses the column value until planned meta is published', function () {
    insertBackfillPost(['title' => 'Column']);

    PostWithExistingColumn::first()->saveMetaAt('title', 'Planned', '+1 week');

    expect(PostWithExistingColumn::first()->title)->toBeNull();

    MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title'])->run();

    expect(PostWithExistingColumn::first()->title)->toBe('Column')
        ->and(PostWithExistingColumn::first()->withMetaAt('+2 weeks')->title)->toBe('Planned');
});

it('includes soft deleted rows', function () {
    $id = insertBackfillRow('sample_posts', Post::class, ['body' => 'Deleted', 'deleted_at' => '2022-01-01 00:00:00']);

    expect(Post::count())->toBe(0);

    expect(MetaBackfill::fromColumns(Post::class, ['body'])->run())->toBe(1);

    expect(Meta::first())
        ->metable_type->toBe(Post::class)
        ->metable_id->toEqual($id)
        ->key->toBe('body')
        ->value->toBe('Deleted');
});

it('processes rows in chunks', function () {
    foreach (range(1, 5) as $i) {
        insertBackfillPost(['title' => "Title {$i}"]);
    }

    expect(MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title'])->chunk(2)->run())->toBe(5);

    expect(PostWithExistingColumn::orderBy('created_at')->get()->pluck('title')->sort()->values()->all())
        ->toBe(['Title 1', 'Title 2', 'Title 3', 'Title 4', 'Title 5']);
});

it('does not dispatch events', function () {
    Event::fake([MetaHasBeenAdded::class]);

    insertBackfillPost(['title' => 'Title']);

    MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title'])->run();

    Event::assertNotDispatched(MetaHasBeenAdded::class);
});

it('creates ids matching the configured morph type', function () {
    insertBackfillPost(['title' => 'Title']);

    MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title'])->run();

    $id = Meta::first()->id;

    match (config('multiplex.morph_type')) {
        'uuid' => expect(Str::isUuid($id))->toBeTrue(),
        'ulid' => expect(Str::isUlid($id))->toBeTrue(),
        default => expect($id)->toBeInt(),
    };
});

it('only counts records in dry run mode', function () {
    insertBackfillPost(['title' => 'A', 'body' => 'Body']);
    insertBackfillPost(['title' => 'B', 'body' => null]);

    $backfill = MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title', 'body'])->chunk(1);

    expect($backfill->dryRun()->run())->toBe(3)
        ->and(Meta::count())->toBe(0);

    expect($backfill->dryRun(false)->run())->toBe(3)
        ->and(Meta::count())->toBe(3);

    insertBackfillPost(['title' => 'C']);

    expect($backfill->dryRun()->run())->toBe(1)
        ->and(Meta::count())->toBe(3);
});

it('throws for models not using HasMeta', function () {
    MetaBackfill::fromColumns(Dummy::class, ['title']);
})->throws(InvalidArgumentException::class, 'must be an Eloquent model using the `HasMeta` trait');

it('throws for invalid chunk sizes', function () {
    MetaBackfill::fromColumns(Post::class, ['title'])->chunk(0);
})->throws(InvalidArgumentException::class, 'Chunk size must be at least 1.');
