<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Post::travelBack();
});

it('scopes where has meta', function () {
    seedRandomModels();
    seedModels();

    testScope(Post::whereHasMeta('one'), ['a']);
    testScope(Post::whereHasMeta('two'), ['a', 'b']);
    testScope(Post::whereHasMeta('three'), ['c']);
    testScope(Post::whereHasMeta('four'));
    testScope(Post::whereHasMeta('one')->whereHasMeta('two'), 'a');
    testScope(Post::whereHasMeta('one')->orWhereHasMeta('three'), 'a,c');
});

it('scopes where has meta from array', function () {
    seedRandomModels();
    seedModels();

    testScope(Post::whereHasMeta(['one', 'two']), ['a', 'b']);
    testScope(Post::whereHasMeta(['two', 'three']), ['a', 'b', 'c']);
    testScope(Post::whereHasMeta(['one', 'two'])->orWhereHasMeta('three'), 'a,b,c');
});

it('scopes where doesnt have meta', function () {
    seedModels();

    testScope(Post::whereDoesntHaveMeta('one'), 'b,c');
    testScope(Post::whereDoesntHaveMeta('two'), 'c');
    testScope(Post::whereDoesntHaveMeta('three'), 'a,b');
    testScope(Post::whereDoesntHaveMeta('four'), 'a,b,c');
    testScope(Post::whereHasMeta('three')->orWhereDoesntHaveMeta('one'), 'b,c');
    testScope(Post::whereDoesntHaveMeta(['one', 'two']), 'c');
    testScope(Post::whereDoesntHaveMeta(['one', 'three']), 'b');
});

it('scopes where doesnt have meta from array', function () {
    seedModels();

    testScope(Post::whereDoesntHaveMeta(['one']), 'b,c');
    testScope(Post::whereDoesntHaveMeta(['two', 'three']));
});

it('scopes where meta', function () {
    seedRandomModels();

    Post::factory()
        ->has(Meta::factory(3)->state(['key' => 'foo', 'value' => true]))
        ->has(Meta::factory(2)->state(['key' => 'foo', 'value' => false]))
        ->create(['title' => 'a']);

    Post::factory()
        ->has(Meta::factory(5)->state(['key' => 'foo', 'value' => true]))
        ->create(['title' => 'b']);

    Post::factory()
        ->has(Meta::factory(3)->state(['key' => 'foo', 'value' => false]))
        ->has(Meta::factory()->state(['key' => 'bar', 'value' => 12]))
        ->create(['title' => 'c']);

    testScope(Post::whereMeta('foo', false), 'a,c');
    testScope(Post::whereMeta('foo', true), 'b');
    testScope(Post::whereMeta('foo', true)->orWhereMeta('bar', 12), 'b,c');
});

it('scopes where meta with datatype', function ($type, $input, $another) {
    seedRandomModels();

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => $another, 'published_at' => Carbon::now()->addDay()]))
        ->create(['title' => 'a']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => $input]))
        ->create(['title' => 'b']);

    testScope(Post::whereMeta('foo', $input), 'b');
    testScope(Post::whereMeta('foo', $another));

    $this->travelTo(Carbon::now()->addDay());

    testScope(Post::whereMeta('foo', $another), 'a');
})->with('datatypeProvider');

it('scopes where meta with operators', function () {
    seedRandomModels();

    Post::factory()
        ->has(Meta::factory(2)->state(['key' => 'foo', 'value' => 4]))
        ->create(['title' => 'a']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'bar', 'value' => 0]))
        ->has(Meta::factory(2)->state(['key' => 'foo', 'value' => Carbon::parse('2022-01-01')]))
        ->create(['title' => 'b']);

    Post::factory()
        ->has(Meta::factory(3)->state(['key' => 'foo', 'value' => false]))
        ->create(['title' => 'c']);

    Post::factory()
        ->has(Meta::factory(2)->state(['key' => 'bar', 'value' => -4]))
        ->create(['title' => 'd']);

    testScope(Post::whereMeta('foo', '!=', 5), 'a');
    testScope(Post::whereMeta('foo', '<', 5), 'a');
    testScope(Post::whereMeta('foo', '>', Carbon::parse('2020-01-01')), 'b');
    testScope(Post::whereMeta('foo', '>', Carbon::parse('2022-01-01')));
    testScope(Post::whereMeta('foo', '>=', 4), 'a');
    testScope(Post::whereMeta('foo', '<=', 0));
    testScope(Post::whereMeta('bar', '<=', 0), 'b,d');
    testScope(Post::whereMeta('foo', '<=', true), 'c');
    testScope(Post::whereMeta('foo', '=', true));
    testScope(Post::whereMeta('foo', '=', false), 'c');
    testScope(Post::whereMeta('foo', '!=', true), 'c');
    testScope(Post::whereMeta('foo', '!=', true)->orWhereMeta('bar', '<', 0), 'c,d');
});

it('scopes where raw meta', function () {
    seedRandomModels();

    Post::factory()
        ->has(Meta::factory(2)->state(['key' => 'foo', 'value' => 4]))
        ->create(['title' => 'a']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'bar', 'value' => 0]))
        ->has(Meta::factory(2)->state(['key' => 'foo', 'value' => Carbon::parse('2022-01-01')]))
        ->create(['title' => 'b']);

    Post::factory()
        ->has(Meta::factory(3)->state(['key' => 'foo', 'value' => false]))
        ->create(['title' => 'c']);

    Post::factory()
        ->has(Meta::factory(2)->state(['key' => 'bar', 'value' => -4]))
        ->create(['title' => 'd']);

    testScope(Post::whereRawMeta('foo', '0'), 'c');
    testScope(Post::whereRawMeta('foo', '!=', ''), 'a,b,c');
    testScope(Post::whereRawMeta('foo', '!=', 4), 'b,c');
    testScope(Post::whereRawMeta('foo', '<', '2021-09-01 00:00:00'), 'c');
    testScope(Post::whereRawMeta('foo', '<', '2022-02-01 00:00:00'), 'b,c');
    testScope(Post::whereRawMeta('foo', '>=', 4), 'a');
    testScope(Post::whereRawMeta('foo', '<=', 0), 'c');
    testScope(Post::whereRawMeta('bar', '<=', 0), 'b,d');
    testScope(Post::whereRawMeta('bar', '<', 0), 'd');
    testScope(Post::whereRawMeta('foo', '<=', true), 'c');
    testScope(Post::whereRawMeta('foo', '=', 'true'));
    testScope(Post::whereRawMeta('foo', '=', ''));
    testScope(Post::whereRawMeta('foo', '=', '0'), 'c');
    testScope(Post::whereRawMeta('foo', '!=', ''), 'a,b,c');
    testScope(Post::whereRawMeta('foo', '!=', '')->orWhereRawMeta('foo', '<', '0'), 'a,b,c');
});

it('scopes where meta of type', function () {
    seedRandomModels();

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'bar', 'value' => 0]))
        ->has(Meta::factory(2)->state(['key' => 'foo', 'value' => Carbon::parse('2022-01-01')]))
        ->create(['title' => 'b']);

    Post::factory()
        ->has(Meta::factory(3)->state(['key' => 'foo', 'value' => null]))
        ->create(['title' => 'c']);

    Post::factory()
        ->has(Meta::factory(2)->state(['key' => 'bar', 'value' => -4]))
        ->create(['title' => 'd']);

    testScope(Post::whereMeta('bar', 0), 'b');
    testScope(Post::whereMetaOfType('boolean', 'bar', null));
    testScope(Post::whereMetaOfType('integer', 'bar', '0'), 'b');
    testScope(Post::whereMeta('foo', null), 'c');
    testScope(Post::whereMetaOfType('boolean', 'foo', null));
    testScope(Post::whereMetaOfType('null', 'foo', null), 'c');
    testScope(Post::whereMeta('bar', -4), 'd');
    testScope(Post::whereMetaOfType('string', 'bar', -4));
    testScope(Post::whereMetaOfType('integer', 'bar', -4), 'd');
    testScope(Post::whereMetaOfType('integer', 'bar', -4)->orWhereMetaOfType('null', 'foo', null), 'c,d');
});

it('scopes where meta in array', function () {
    seedRandomModels();

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => 'one']))
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => 2]))
        ->create(['title' => 'a']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => 'one']))
        ->create(['title' => 'b']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => 'three']))
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => 4]))
        ->create(['title' => 'c']);

    testScope(Post::whereMetaIn('foo', ['one', 'three', 2]), 'a,b');
    testScope(Post::whereMetaIn('foo', ['one', 'three', '2']), 'b');
    testScope(Post::whereMetaIn('foo', [2, 4, 5]), 'a,c');
    testScope(Post::whereMetaIn('foo', ['one', 2, 4.0]), 'a,b');
    testScope(Post::whereMeta('foo', 'one')->orWhereMetaIn('foo', [2, 3, 4]), 'a,b,c');
});

it('scopes after time traveling', function () {
    seedRandomModels();

    $a = Post::factory()->create(['title' => 'a']);
    $b = Post::factory()->create(['title' => 'b']);
    $c = Post::factory()->create(['title' => 'c']);

    $a->saveMeta('foo', 'bar');
    $a->saveMetaAt('foo', 'history', '-1 day');
    $a->saveMetaAt('foo', 'future', '+1 day');

    $b->saveMeta('bar', 'foo');
    $b->saveMetaAt('bar', 'future', '+2 days');

    $c->saveMetaAt('bar', 120, '-3 days');
    $c->saveMetaAt('foo', true, '-2 days');
    $c->saveMeta('bar', false);
    $c->saveMeta('foo', false);
    $c->saveMetaAt('bar', 123, '+2 days');

    testScope(Post::whereMetaIn('foo', [false, 'bar']), 'a,c');
    testScope(Post::travelTo(Carbon::now()->subHours(23))->whereMetaIn('foo', [false, 'bar']));
    testScope(Post::travelTo(Carbon::now()->subHours(23))->whereMetaIn('foo', [true, 'history']), 'a,c');
    testScope(Post::whereMetaIn('foo', [true, 'history'])->travelTo(Carbon::now()->subHours(23)), 'a,c');
    testScope(Post::travelTo(Carbon::now()->subHours(25))->whereMetaIn('foo', [true, 'history']), 'c');
    testScope(Post::travelTo(Carbon::now()->addDay())->whereMetaIn('foo', [false, 'history']), 'c');
    testScope(Post::travelTo(Carbon::now()->addDays(2))->whereMetaIn('foo', [false, 'future']), 'a,c');

    testScope(Post::travelTo(Carbon::now()->subDays(2))->whereMeta('bar', 'foo'));
    testScope(Post::travelBack()->whereMeta('bar', 'foo'), 'b');

    testScope(Post::travelTo(Carbon::now()->subHours(50))->whereMeta('bar', '>=', 123));
    testScope(Post::travelTo(Carbon::now()->addDays(3))->whereMeta('bar', '>=', 123), 'c');
});

it('scopes where meta empty', function () {
    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => '']))
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => 0]))
        ->create(['title' => 'a']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => null]))
        ->create(['title' => 'b']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => '']))
        ->create(['title' => 'c']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => true]))
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => false]))
        ->create(['title' => 'd']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'bar', 'value' => true]))
        ->create(['title' => 'e']);

    testScope(Post::whereMetaEmpty('foo'), 'b,c,e');
    testScope(Post::whereMeta('foo', false)->orWhereMetaEmpty('bar'), 'a,b,c,d');
});

it('scopes where meta not empty', function () {
    seedRandomModels();

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => '']))
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => 12]))
        ->create(['title' => 'a']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => null]))
        ->has(Meta::factory()->state(['key' => 'bar', 'value' => false]))
        ->create(['title' => 'b']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => '']))
        ->create(['title' => 'c']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => true]))
        ->has(Meta::factory()->state(['key' => 'foo', 'value' => false]))
        ->has(Meta::factory()->state(['key' => 'bar', 'value' => false]))
        ->create(['title' => 'd']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'bar', 'value' => true]))
        ->create(['title' => 'e']);

    testScope(Post::whereMetaNotEmpty('foo'), 'a,d');
    testScope(Post::whereMeta('foo', false)->orWhereMetaNotEmpty('bar'), 'b,d,e');
    testScope(Post::whereMetaNotEmpty(['foo', 'bar']), 'd');
    testScope(Post::whereMetaNotEmpty(['foo', 'bar', 'another']));
});

function seedRandomModels()
{
    Post::factory(10)->has(Meta::factory(3))->create();
}

function seedModels()
{
    Post::factory()
        ->has(Meta::factory(2)->state(['key' => 'one']))
        ->has(Meta::factory()->state(['key' => 'two']))
        ->has(Meta::factory()->state(['key' => 'five']))
        ->create(['title' => 'a']);

    Post::factory()
        ->has(Meta::factory(3)->state(['key' => 'two']))
        ->create(['title' => 'b']);

    Post::factory()
        ->has(Meta::factory()->state(['key' => 'three']))
        ->create(['title' => 'c']);
}

function testScope(Builder $query, $expectedFields = [], string $fieldName = 'title')
{
    $result = $query->get();

    if (is_string($expectedFields)) {
        $expectedFields = explode(',', $expectedFields);
    }

    expect($result)->toHaveCount(count($expectedFields));
    expect($result->pluck($fieldName)->toArray())->toEqual($expectedFields);

    return $result;
}

dataset('datatypeProvider', function () {
    $timestamp = '2017-01-01 00:00:00.000000+0000';
    $datetime = Carbon::createFromFormat('Y-m-d H:i:s.uO', $timestamp);

    $object = new stdClass;
    $object->foo = 'bar';
    $object->baz = 3;

    return [
        'array' => [
            'array',
            ['foo' => ['bar'], 'baz'],
            ['another' => ['123'], 'baz'],
        ],
        'boolean' => [
            'boolean',
            true,
            false,
        ],
        'datetime' => [
            'datetime',
            $datetime,
            Carbon::now(),
        ],
        'float' => [
            'float',
            1.1,
            1.12,
        ],
        'integer' => [
            'integer',
            3,
            4,
        ],
        'null' => [
            'null',
            null,
            0,
        ],
        'object' => [
            'object',
            $object,
            new stdClass,
        ],
        'serializable' => [
            'serializable',
            new SampleSerializable(['foo' => 'bar']),
            new SampleSerializable(['bar' => 'foo']),
        ],
        'string' => [
            'string',
            'foo',
            'bar',
        ],
    ];
});
