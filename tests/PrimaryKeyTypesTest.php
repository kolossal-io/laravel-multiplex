<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Support\MetaBackfill;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\PostWithExistingColumn;

it('uses the configured column type', function (string $type, array $column_types) {
    $this->refreshDatabaseWithType($type);

    expect($column_types)->toContain(Schema::getColumnType('meta', 'id'));
    expect(config('multiplex.morph_type'))->toBe($type);

    $meta = Meta::factory()->make();

    if (config('multiplex.morph_type') === 'uuid') {
        expect(Str::isUuid($meta->newUniqueId()))->toBeTrue();
        expect($meta->getKeyType())->toEqual('string');
    } elseif (config('multiplex.morph_type') === 'ulid') {
        expect(Str::isUlid($meta->newUniqueId()))->toBeTrue();
        expect($meta->getKeyType())->toEqual('string');
    } else {
        expect($meta->getKeyType())->toEqual('int');
    }
})->with('morphTypes');

it('doesnt create a unique id only if confiugured', function (string $type) {
    $this->refreshDatabaseWithType($type);

    $id = Meta::factory()->make()->newUniqueId();

    switch ($type) {
        case 'uuid':
            expect($id)->toBeString();
            expect(Str::isUuid($id))->toBeTrue();
            break;
        case 'ulid':
            expect($id)->toBeString();
            expect(Str::isUlid($id))->toBeTrue();
            break;
        default:
            expect($id)->toBeNull();
            break;
    }
})->with('morphTypes');

it('resolves unique id models by key', function () {
    $this->refreshDatabaseWithType('integer');

    $meta = Post::factory()->create()->saveMeta('foo', 'bar');

    expect($meta->is($meta->resolveRouteBinding($meta->id)))->toBeTrue();
    expect($meta->is($meta->resolveRouteBinding($meta->id, 'id')))->toBeTrue();
});

it('backfills meta with ids matching the configured morph type', function (string $type) {
    $this->refreshDatabaseWithType($type);

    $post = PostWithExistingColumn::factory()->create();

    DB::table('sample_posts_existing_column')->where('id', $post->id)->update(['title' => 'Title']);

    expect(MetaBackfill::fromColumns(PostWithExistingColumn::class, ['title'])->run())->toBe(1);

    $id = Meta::first()->id;

    match ($type) {
        'uuid' => expect(Str::isUuid($id))->toBeTrue(),
        'ulid' => expect(Str::isUlid($id))->toBeTrue(),
        default => expect($id)->toBeInt(),
    };
})->with('morphTypes');

it('throws exception for invalid morph type configuration', function () {
    $this->expectException(Exception::class);

    $this->refreshDatabaseWithType(null);
});

dataset('morphTypes', function () {
    return [
        'integer' => ['integer', ['integer', 'int', 'int4']],
        'uuid' => ['uuid', ['varchar', 'char', 'bpchar', 'uuid']],
        'ulid' => ['ulid', ['varchar', 'char', 'bpchar']],
    ];
});
