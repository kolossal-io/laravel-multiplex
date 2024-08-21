<?php

namespace Kolossal\Multiplex\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Tests\Mocks\BackedEnum;
use Kolossal\Multiplex\Tests\Mocks\Dummy;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;
use Kolossal\Multiplex\Tests\Traits\AccessesProtectedProperties;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

final class MetaTest extends TestCase
{
    use AccessesProtectedProperties;
    use RefreshDatabase;

    public static function handlerProvider(): array
    {
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
    }

    /** @test */
    public function it_can_get_and_set_value(): void
    {
        $meta = Meta::factory()->make();

        $meta->value = 'foo';

        $this->assertEquals('foo', $meta->value);
        $this->assertEquals('string', $meta->type);
    }

    /** @test */
    public function it_exposes_its_serialized_value(): void
    {
        $meta = Meta::factory()->make();
        $meta->value = 123;

        $this->assertEquals('123', $meta->rawValue);
        $this->assertEquals('123', $meta->raw_value);
    }

    /** @test */
    public function it_caches_unserialized_value(): void
    {
        $meta = Meta::factory()->make();
        $meta->value = 'foo';

        $this->assertEquals('foo', $meta->value);

        $meta->setRawAttributes(['value' => 'bar'], true);

        $this->assertEquals('foo', $meta->value);
        $this->assertEquals('bar', $meta->rawValue);
        $this->assertEquals('bar', $meta->raw_value);
    }

    /** @test */
    public function it_clears_cache_on_set(): void
    {
        $meta = Meta::factory()->make();

        $meta->value = 'foo';

        $this->assertEquals('foo', $meta->value);

        $meta->value = 'bar';

        $this->assertEquals('bar', $meta->value);
    }

    /** @test */
    public function it_can_get_its_model_relation(): void
    {
        $meta = Meta::factory()->make();

        $relation = $meta->metable();

        $this->assertInstanceOf(MorphTo::class, $relation);
        $this->assertEquals('metable_type', $relation->getMorphType());
        $this->assertEquals('metable_id', $relation->getForeignKeyName());
    }

    /** @test */
    public function it_can_determine_if_it_is_current(): void
    {
        $model = Post::factory()->create();

        $model->saveMeta('foo', 1);
        $model->saveMeta('foo', 2);
        $model->saveMeta('foo', 3);
        $model->saveMetaAt('foo', 4, '+1 day');

        $this->assertCount(4, $model->allMeta);

        $this->assertFalse(Meta::whereValue(1)->first()->is_current);
        $this->assertFalse(Meta::whereValue(2)->first()->is_current);
        $this->assertTrue(Meta::whereValue(3)->first()->is_current);
        $this->assertFalse(Meta::whereValue(4)->first()->is_current);
    }

    /** @test */
    public function it_can_determine_if_it_is_planned(): void
    {
        $model = Post::factory()->create();

        $model->setMetaTimestamp(now());

        $model->saveMetaAt('foo', 1, '-1 day');
        $model->saveMeta('foo', 2);
        $model->saveMetaAt('foo', 3, '+1 day');

        $meta = Meta::orderBy('id')->get();

        $this->assertFalse($meta->get(0)->is_planned);
        $this->assertFalse($meta->get(1)->is_planned);
        $this->assertTrue($meta->get(2)->is_planned);
    }

    /** @test */
    public function it_can_query_published_meta(): void
    {
        $model = Post::factory()->create();

        $model->setMetaTimestamp(now());

        Post::factory()->create()->saveMeta('foo', 'another');

        $model->saveMetaAt('bar', 'foo', '-2 days');
        $model->saveMetaAt('foo', 1, '-1 day');
        $model->saveMeta('foo', 2);
        $model->saveMetaAt('foo', 3, '+1 day');

        $meta = Meta::published()->whereMetableId($model->id)->get()->pluck('value');

        $this->assertCount(3, $meta);
        $this->assertContains('foo', $meta);
        $this->assertContains(1, $meta);
        $this->assertContains(2, $meta);
        $this->assertNotContains(3, $meta);
    }

    /** @test */
    public function it_can_query_unpublished_meta(): void
    {
        $model = Post::factory()->create();

        $model->setMetaTimestamp(now());

        Post::factory()->create()->saveMeta('foo', 'another');

        $model->saveMetaAt('foo', 1, '-1 day');
        $model->saveMeta('foo', 2);
        $model->saveMetaAt('foo', 3, '+1 day');
        $model->saveMetaAt('bar', 'foo', '+2 days');

        $meta = Meta::planned()->whereMetableId($model->id)->get()->pluck('value');

        $this->assertCount(2, $meta);
        $this->assertContains(3, $meta);
        $this->assertContains('foo', $meta);
    }

    /** @test */
    public function it_can_query_published_meta_by_date(): void
    {
        $model = Post::factory()->create();

        Post::factory()->create()->saveMeta('foo', 'another');

        $model->saveMetaAt('bar', 'foo', '-2 days');
        $model->saveMetaAt('foo', 1, '-1 day');
        $model->saveMeta('foo', 2);
        $model->saveMetaAt('foo', 3, '+1 day');

        $meta = Meta::publishedBefore('-1 minute')->whereMetableId($model->id)->get()->pluck('value');

        $this->assertCount(2, $meta);
        $this->assertContains('foo', $meta);
        $this->assertContains(1, $meta);
        $this->assertNotContains(2, $meta);
        $this->assertNotContains(3, $meta);
    }

    /** @test */
    public function it_can_exclude_current(): void
    {
        $model = Post::factory()->create();

        Post::factory()->create()->saveMeta('foo', 'another');

        $model->setMetaTimestamp(now());

        $model->saveMetaAt('bar', 'old', '-3 days');
        $model->saveMetaAt('bar', 'foo', '-2 days');
        $model->saveMetaAt('foo', 1, '-1 day');
        $model->saveMeta('foo', 2);
        $model->saveMetaAt('foo', 3, '+1 day');

        $meta = Meta::withoutCurrent()->whereMetableId($model->id)->get()->pluck('value');

        $this->assertCount(3, $meta);
        $this->assertContains('old', $meta);
        $this->assertNotContains('foo', $meta);
        $this->assertContains(1, $meta);
        $this->assertNotContains(2, $meta);
        $this->assertContains(3, $meta);

        $meta = Meta::withoutCurrent('-15 minutes')
            ->whereMetableId($model->id)->get()->pluck('value');

        $this->assertCount(3, $meta);
        $this->assertContains('old', $meta);
        $this->assertNotContains('foo', $meta);
        $this->assertNotContains(1, $meta);
        $this->assertContains(2, $meta);
        $this->assertContains(3, $meta);

        $meta = Meta::withoutCurrent('-50 hours')
            ->whereMetableId($model->id)->get()->pluck('value');

        $this->assertCount(4, $meta);
        $this->assertNotContains('old', $meta);
        $this->assertContains('foo', $meta);
        $this->assertContains(1, $meta);
        $this->assertContains(2, $meta);
        $this->assertContains(3, $meta);
    }

    /** @test */
    public function it_can_exclude_history(): void
    {
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

        $this->assertCount(3, $meta);
        $this->assertContains('foo', $meta);
        $this->assertContains(2, $meta);
        $this->assertContains(3, $meta);

        $meta = Meta::withoutHistory('-15 minutes')
            ->whereMetableId($model->id)->get()->pluck('value');

        $this->assertCount(4, $meta);
        $this->assertContains('foo', $meta);
        $this->assertContains(1, $meta);
        $this->assertContains(2, $meta);
        $this->assertContains(3, $meta);

        $meta = Meta::withoutHistory('-50 hours')
            ->whereMetableId($model->id)->get()->pluck('value');

        $this->assertCount(5, $meta);
        $this->assertContains('old', $meta);
        $this->assertContains('foo', $meta);
        $this->assertContains(1, $meta);
        $this->assertContains(2, $meta);
        $this->assertContains(3, $meta);
    }

    /** @test */
    public function it_can_include_only_current(): void
    {
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

        $this->assertCount(3, $meta, print_r($meta, true) . ' does not match a count of 3. Values were plucked from ' . print_r($metaModels->toArray(), true));

        $this->assertContains('another', $meta);
        $this->assertContains('foo', $meta);
        $this->assertContains(2, $meta);

        $this->assertCount(2, $modelMeta);
        $this->assertContains('foo', $meta);
        $this->assertContains(2, $meta);

        $meta = Meta::onlyCurrent(Carbon::now()->subMinutes(15))->get()->pluck('value');
        $modelMeta = $model->allMeta()->onlyCurrent(Carbon::now()->subMinutes(15))->get()->pluck('value');

        $this->assertCount(1, $meta);
        $this->assertContains(1, $meta);

        $this->assertCount(1, $modelMeta);
        $this->assertContains(1, $meta);
    }

    /**
     * @test
     *
     * @dataProvider handlerProvider
     */
    public function it_can_store_and_retrieve_datatypes($type, $input): void
    {
        $meta = Meta::factory()->make([
            'metable_type' => 'Foo\Bar\Model',
            'metable_id' => 1,
            'key' => 'dummy',
        ]);

        $meta->value = $input;
        $meta->save();

        $meta->refresh();

        $this->assertEquals($type, $meta->type);
        $this->assertEquals($input, $meta->value);
        $this->assertTrue(is_string($meta->raw_value) || is_null($meta->raw_value));
    }

    /**
     * @test
     *
     * @dataProvider handlerProvider
     */
    public function it_can_query_by_value($type, $input): void
    {
        $meta = Meta::factory()->make([
            'metable_type' => 'Foo\Bar\Model',
            'metable_id' => 1,
            'key' => 'dummy',
        ]);

        $meta->value = $input;
        $meta->save();

        $result = Meta::whereValue($input)->first();

        $this->assertEquals($input, $result->value);
        $this->assertEquals($type, $result->type);
    }

    /** @test */
    public function it_will_return_null_for_undefined_value(): void
    {
        $meta = new Meta;

        $this->assertNull($meta->value);
        $this->assertNull($meta->type);
    }

    /** @test */
    public function it_can_return_value_and_type_once_defined(): void
    {
        $meta = new Meta;

        $meta->value = 123.0;

        $this->assertSame(123.0, $meta->value);
        $this->assertSame('float', $meta->type);
    }

    /** @test */
    public function it_will_cache_value_when_accessing(): void
    {
        $meta = new Meta;
        $meta->value = 123.0;

        $this->assertNull($this->getProtectedProperty($meta, 'cachedValue'));

        $this->assertSame(123.0, $meta->value);
        $this->assertSame(123.0, $this->getProtectedProperty($meta, 'cachedValue'));
    }

    /** @test */
    public function it_will_reset_cache_when_setting_value(): void
    {
        $meta = new Meta;
        $meta->value = 123.0;

        $this->assertSame(123.0, $meta->value);
        $this->assertSame(123.0, $this->getProtectedProperty($meta, 'cachedValue'));

        $meta->value = 123;

        $this->assertNull($this->getProtectedProperty($meta, 'cachedValue'));

        $this->assertSame(123, $meta->value);
        $this->assertSame(123, $this->getProtectedProperty($meta, 'cachedValue'));
    }
}
