<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Tests\Mocks\BackedEnum;
use Kolossal\Multiplex\Tests\Mocks\Dummy;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;

uses(\Kolossal\Multiplex\Tests\Traits\AccessesProtectedProperties::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

dataset('metaHandlerProvider', function () {
    $timestamp = '2017-01-01 00:00:00.000000+0000';
    $datetime = Carbon::createFromFormat('Y-m-d H:i:s.uO', $timestamp);

    $object = new stdClass;
    $object->foo = 'bar';
    $object->baz = 3;

    return [
        'array' => [
            'array',
            ['foo' => ['bar'], 'baz'],
        ],
        'boolean' => [
            'boolean',
            true,
        ],
        'datetime' => [
            'datetime',
            $datetime,
        ],
        'enum' => [
            'enum',
            BackedEnum::Two,
        ],
        'float' => [
            'float',
            1.1,
        ],
        'integer' => [
            'integer',
            3,
        ],
        'model' => [
            'model',
            new Dummy,
        ],
        'model collection' => [
            'collection',
            new Collection([new Dummy]),
        ],
        'null' => [
            'null',
            null,
        ],
        'object' => [
            'object',
            $object,
        ],
        'serializable' => [
            'serializable',
            new SampleSerializable(['foo' => 'bar']),
        ],
        'string' => [
            'string',
            'foo',
        ],
    ];
});

it('can get and set value', function () {
    $meta = Meta::factory()->make();

    $meta->value = 'foo';

    expect($meta->value)->toEqual('foo');
    expect($meta->type)->toEqual('string');
});

it('exposes its serialized value', function () {
    $meta = Meta::factory()->make();
    $meta->value = 123;

    expect($meta->rawValue)->toEqual('123');
    expect($meta->raw_value)->toEqual('123');
});

it('caches unserialized value', function () {
    $meta = Meta::factory()->make();
    $meta->value = 'foo';

    expect($meta->value)->toEqual('foo');

    $meta->setRawAttributes(['value' => 'bar'], true);

    expect($meta->value)->toEqual('foo');
    expect($meta->rawValue)->toEqual('bar');
    expect($meta->raw_value)->toEqual('bar');
});

it('clears cache on set', function () {
    $meta = Meta::factory()->make();

    $meta->value = 'foo';

    expect($meta->value)->toEqual('foo');

    $meta->value = 'bar';

    expect($meta->value)->toEqual('bar');
});

it('can get its model relation', function () {
    $meta = Meta::factory()->make();

    $relation = $meta->metable();

    expect($relation)->toBeInstanceOf(MorphTo::class);
    expect($relation->getMorphType())->toEqual('metable_type');
    expect($relation->getForeignKeyName())->toEqual('metable_id');
});

it('can determine if it is current', function () {
    $model = Post::factory()->create();

    $model->saveMeta('foo', 1);
    $model->saveMeta('foo', 2);
    $model->saveMeta('foo', 3);
    $model->saveMetaAt('foo', 4, '+1 day');

    expect($model->allMeta)->toHaveCount(4);

    expect(Meta::whereValue(1)->first()->is_current)->toBeFalse();
    expect(Meta::whereValue(2)->first()->is_current)->toBeFalse();
    expect(Meta::whereValue(3)->first()->is_current)->toBeTrue();
    expect(Meta::whereValue(4)->first()->is_current)->toBeFalse();
});

it('can determine if it is planned', function () {
    $model = Post::factory()->create();

    $model->setMetaTimestamp(now());

    $model->saveMetaAt('foo', 1, '-1 day');
    $model->saveMeta('foo', 2);
    $model->saveMetaAt('foo', 3, '+1 day');

    $meta = Meta::orderBy('id')->get();

    expect($meta->get(0)->is_planned)->toBeFalse();
    expect($meta->get(1)->is_planned)->toBeFalse();
    expect($meta->get(2)->is_planned)->toBeTrue();
});

it('can query published meta', function () {
    $model = Post::factory()->create();

    $model->setMetaTimestamp(now());

    Post::factory()->create()->saveMeta('foo', 'another');

    $model->saveMetaAt('bar', 'foo', '-2 days');
    $model->saveMetaAt('foo', 1, '-1 day');
    $model->saveMeta('foo', 2);
    $model->saveMetaAt('foo', 3, '+1 day');

    $meta = Meta::published()->whereMetableId($model->id)->get()->pluck('value');

    expect($meta)->toHaveCount(3);
    expect($meta)->toContain('foo');
    expect($meta)->toContain(1);
    expect($meta)->toContain(2);
    expect($meta)->not->toContain(3);
});

it('can query unpublished meta', function () {
    $model = Post::factory()->create();

    $model->setMetaTimestamp(now());

    Post::factory()->create()->saveMeta('foo', 'another');

    $model->saveMetaAt('foo', 1, '-1 day');
    $model->saveMeta('foo', 2);
    $model->saveMetaAt('foo', 3, '+1 day');
    $model->saveMetaAt('bar', 'foo', '+2 days');

    $meta = Meta::planned()->whereMetableId($model->id)->get()->pluck('value');

    expect($meta)->toHaveCount(2);
    expect($meta)->toContain(3);
    expect($meta)->toContain('foo');
});

it('can query published meta by date', function () {
    $model = Post::factory()->create();

    Post::factory()->create()->saveMeta('foo', 'another');

    $model->saveMetaAt('bar', 'foo', '-2 days');
    $model->saveMetaAt('foo', 1, '-1 day');
    $model->saveMeta('foo', 2);
    $model->saveMetaAt('foo', 3, '+1 day');

    $meta = Meta::publishedBefore('-1 minute')->whereMetableId($model->id)->get()->pluck('value');

    expect($meta)->toHaveCount(2);
    expect($meta)->toContain('foo');
    expect($meta)->toContain(1);
    expect($meta)->not->toContain(2);
    expect($meta)->not->toContain(3);
});

it('can exclude current', function () {
    $model = Post::factory()->create();

    Post::factory()->create()->saveMeta('foo', 'another');

    $model->setMetaTimestamp(now());

    $model->saveMetaAt('bar', 'old', '-3 days');
    $model->saveMetaAt('bar', 'foo', '-2 days');
    $model->saveMetaAt('foo', 1, '-1 day');
    $model->saveMeta('foo', 2);
    $model->saveMetaAt('foo', 3, '+1 day');

    $meta = Meta::withoutCurrent()->whereMetableId($model->id)->get()->pluck('value');

    expect($meta)->toHaveCount(3);
    expect($meta)->toContain('old');
    expect($meta)->not->toContain('foo');
    expect($meta)->toContain(1);
    expect($meta)->not->toContain(2);
    expect($meta)->toContain(3);

    $meta = Meta::withoutCurrent('-15 minutes')
        ->whereMetableId($model->id)->get()->pluck('value');

    expect($meta)->toHaveCount(3);
    expect($meta)->toContain('old');
    expect($meta)->not->toContain('foo');
    expect($meta)->not->toContain(1);
    expect($meta)->toContain(2);
    expect($meta)->toContain(3);

    $meta = Meta::withoutCurrent('-50 hours')
        ->whereMetableId($model->id)->get()->pluck('value');

    expect($meta)->toHaveCount(4);
    expect($meta)->not->toContain('old');
    expect($meta)->toContain('foo');
    expect($meta)->toContain(1);
    expect($meta)->toContain(2);
    expect($meta)->toContain(3);
});

it('can exclude history', function () {
    $model = Post::factory()->create();

    $model->setMetaTimestamp(now());

    Post::factory()->create()->saveMeta('foo', 'another');

    $model->saveMetaAt('bar', 'old', '-3 days');
    $model->saveMetaAt('bar', 'foo', '-2 days');
    $model->saveMetaAt('foo', 1, '-1 day');
    $model->saveMeta('foo', 2);
    $model->saveMetaAt('foo', 3, '+1 day');

    $meta = Meta::withoutHistory()
        ->whereMetableId($model->id)->get()->pluck('value');

    expect($meta)->toHaveCount(3);
    expect($meta)->toContain('foo');
    expect($meta)->toContain(2);
    expect($meta)->toContain(3);

    $meta = Meta::withoutHistory('-15 minutes')
        ->whereMetableId($model->id)->get()->pluck('value');

    expect($meta)->toHaveCount(4);
    expect($meta)->toContain('foo');
    expect($meta)->toContain(1);
    expect($meta)->toContain(2);
    expect($meta)->toContain(3);

    $meta = Meta::withoutHistory('-50 hours')
        ->whereMetableId($model->id)->get()->pluck('value');

    expect($meta)->toHaveCount(5);
    expect($meta)->toContain('old');
    expect($meta)->toContain('foo');
    expect($meta)->toContain(1);
    expect($meta)->toContain(2);
    expect($meta)->toContain(3);
});

it('can include only current', function () {
    $this->travelBack();

    $model = Post::factory()->create();

    $model->setMetaTimestamp(Carbon::now());

    $this->travelTo(Carbon::now());

    Post::factory()->create()->saveMetaAt('foo', 'another', Carbon::now());

    $model->saveMeta('bar', 'foo');
    $model->saveMetaAt('foo', 1, Carbon::now()->subDay());
    $model->saveMeta('foo', 2);
    $model->saveMetaAt('foo', 3, Carbon::now()->addDay());

    $this->travelTo(Carbon::now()->addSeconds(10));

    $metaModels = Meta::onlyCurrent()->get();
    $meta = $metaModels->pluck('value');
    $modelMeta = $model->allMeta()->onlyCurrent()->get()->pluck('value');

    expect($meta)->toHaveCount(3, print_r($meta, true) . ' does not match a count of 3. Values were plucked from ' . print_r($metaModels->toArray(), true));

    expect($meta)->toContain('another');
    expect($meta)->toContain('foo');
    expect($meta)->toContain(2);

    expect($modelMeta)->toHaveCount(2);
    expect($meta)->toContain('foo');
    expect($meta)->toContain(2);

    $meta = Meta::onlyCurrent(Carbon::now()->subMinutes(15))->get()->pluck('value');
    $modelMeta = $model->allMeta()->onlyCurrent(Carbon::now()->subMinutes(15))->get()->pluck('value');

    expect($meta)->toHaveCount(1);
    expect($meta)->toContain(1);

    expect($modelMeta)->toHaveCount(1);
    expect($meta)->toContain(1);
});

it('can store and retrieve datatypes', function ($type, $input) {
    $meta = Meta::factory()->make([
        'metable_type' => 'Foo\Bar\Model',
        'metable_id' => 1,
        'key' => 'dummy',
    ]);

    $meta->value = $input;
    $meta->save();

    $meta->refresh();

    expect($meta->type)->toEqual($type);
    expect($meta->value)->toEqual($input);
    expect(is_string($meta->raw_value) || is_null($meta->raw_value))->toBeTrue();
})->with('metaHandlerProvider');

it('can query by value', function ($type, $input) {
    $meta = Meta::factory()->make([
        'metable_type' => 'Foo\Bar\Model',
        'metable_id' => 1,
        'key' => 'dummy',
    ]);

    $meta->value = $input;
    $meta->save();

    $result = Meta::whereValue($input)->first();

    expect($result->value)->toEqual($input);
    expect($result->type)->toEqual($type);
})->with('metaHandlerProvider');

it('will return null for undefined value', function () {
    $meta = new Meta;

    expect($meta->value)->toBeNull();
    expect($meta->type)->toBeNull();
});

it('can return value and type once defined', function () {
    $meta = new Meta;

    $meta->value = 123.0;

    expect($meta->value)->toBe(123.0);
    expect($meta->type)->toBe('float');
});

it('will cache value when accessing', function () {
    $meta = new Meta;
    $meta->value = 123.0;

    expect($this->getProtectedProperty($meta, 'cachedValue'))->toBeNull();

    expect($meta->value)->toBe(123.0);
    expect($this->getProtectedProperty($meta, 'cachedValue'))->toBe(123.0);
});

it('will reset cache when setting value', function () {
    $meta = new Meta;
    $meta->value = 123.0;

    expect($meta->value)->toBe(123.0);
    expect($this->getProtectedProperty($meta, 'cachedValue'))->toBe(123.0);

    $meta->value = 123;

    expect($this->getProtectedProperty($meta, 'cachedValue'))->toBeNull();

    expect($meta->value)->toBe(123);
    expect($this->getProtectedProperty($meta, 'cachedValue'))->toBe(123);
});
