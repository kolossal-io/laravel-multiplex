<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Kolossal\Multiplex\Tests\Mocks\Post;

it('uses the configured column type', function (string $type, string $column_type) {
    $this->refreshDatabaseWithType($type);

    if (version_compare(app()->version(), '10.0.0', '>')) {
        expect(Schema::getColumnType('meta', 'id'))->toBe($column_type);
    }

    expect(config('multiplex.morph_type'))->toBe($type);

    $meta = Post::factory()->create()->saveMeta('foo', 'bar');

    if (config('multiplex.morph_type') === 'uuid') {
        expect(Str::isUuid($meta->id))->toBeTrue();
        expect($meta->getKeyType())->toEqual('string');
    } elseif (config('multiplex.morph_type') === 'ulid') {
        expect(Str::isUlid($meta->id))->toBeTrue();
        expect($meta->getKeyType())->toEqual('string');
    } else {
        expect($meta->id)->toBeInt();
        expect($meta->getKeyType())->toEqual('int');
    }
})->with('morphTypes');

it('throws model not found exception for invalid id', function (string $type) {
    $this->refreshDatabaseWithType($type);

    $meta = Post::factory()->create()->saveMeta('foo', 'bar');

    $this->expectException(ModelNotFoundException::class);

    $meta->resolveRouteBinding('abc-123', 'id');
})->with('stringMorphTypes');

it('throws model not found exception if morph type mismatches', function (string $type) {
    $this->refreshDatabaseWithType($type);

    $meta = Post::factory()->create()->saveMeta('foo', 'bar');

    $this->expectException(ModelNotFoundException::class);

    $meta->resolveRouteBinding('abc-123', 'id');
})->with('stringMorphTypes');

it('doesnt create a unique id only if confiugured', function (string $type) {
    $this->refreshDatabaseWithType($type);

    $meta = Post::factory()->create()->saveMeta('foo', 'bar');

    $id = $meta->newUniqueId();

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
            expect($meta->newUniqueId())->toBeNull();
            break;
    }
})->with('morphTypes');

it('throws error for invalid unique ids with implicit key name', function (string $type) {
    $this->refreshDatabaseWithType($type);

    $meta = Post::factory()->create()->saveMeta('foo', 'bar');

    $this->expectException(ModelNotFoundException::class);

    $meta->resolveRouteBinding('abc-123');
})->with('stringMorphTypes');

it('resolves unique id models by key', function () {
    $this->refreshDatabaseWithType('integer');

    $meta = Post::factory()->create()->saveMeta('foo', 'bar');

    expect($meta->is($meta->resolveRouteBinding($meta->id)))->toBeTrue();
    expect($meta->is($meta->resolveRouteBinding($meta->id, 'id')))->toBeTrue();
});

it('resolves integer id models by key', function (string $type) {
    $this->refreshDatabaseWithType($type);

    $meta = Post::factory()->create()->saveMeta('foo', 'bar');

    expect($meta->is($meta->resolveRouteBinding($meta->id)))->toBeTrue();
    expect($meta->is($meta->resolveRouteBinding($meta->id, 'id')))->toBeTrue();
})->with('stringMorphTypes');

it('throws exception for invalid morph type configuration', function () {
    $this->expectException(Exception::class);

    $this->refreshDatabaseWithType(null);
});

dataset('morphTypes', function () {
    return [
        'integer' => ['integer', 'integer'],
        'uuid' => ['uuid', 'varchar'],
        'ulid' => ['ulid', 'varchar'],
    ];
});

dataset('stringMorphTypes', function () {
    return [
        'uuid' => ['uuid'],
        'ulid' => ['ulid'],
    ];
});
