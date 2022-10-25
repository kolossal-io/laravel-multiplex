<?php

namespace Kolossal\Multiplex\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Tests\Mocks\Dummy;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;
use stdClass;

class MetaTest extends TestCase
{
    use RefreshDatabase;

    public function handlerProvider()
    {
        $timestamp = '2017-01-01 00:00:00.000000+0000';
        $datetime = Carbon::createFromFormat('Y-m-d H:i:s.uO', $timestamp);

        $object = new stdClass();
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
    public function it_can_get_and_set_value()
    {
        $meta = Meta::factory()->make();

        $meta->value = 'foo';

        $this->assertEquals('foo', $meta->value);
        $this->assertEquals('string', $meta->type);
    }

    /** @test */
    public function it_exposes_its_serialized_value()
    {
        $meta = Meta::factory()->make();
        $meta->value = 123;

        $this->assertEquals('123', $meta->rawValue);
        $this->assertEquals('123', $meta->raw_value);
    }

    /** @test */
    public function it_caches_unserialized_value()
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
    public function it_clears_cache_on_set()
    {
        $meta = Meta::factory()->make();

        $meta->value = 'foo';

        $this->assertEquals('foo', $meta->value);

        $meta->value = 'bar';

        $this->assertEquals('bar', $meta->value);
    }

    /** @test */
    public function it_can_get_its_model_relation()
    {
        $meta = Meta::factory()->make();

        $relation = $meta->metable();

        $this->assertInstanceOf(MorphTo::class, $relation);
        $this->assertEquals('metable_type', $relation->getMorphType());
        $this->assertEquals('metable_id', $relation->getForeignKeyName());
    }

    /** @test */
    public function it_can_determine_if_it_is_current()
    {
        $model = Post::factory()->create();

        $model->saveMeta('foo', 1);
        $model->saveMeta('foo', 2);
        $model->saveMeta('foo', 3);
        $model->saveMetaAt('foo', 4, '+1 day');

        $this->assertCount(4, $model->allMeta);

        $meta = Meta::orderBy('id')->get();

        $this->assertFalse($meta->get(0)->is_current);
        $this->assertFalse($meta->get(1)->is_current);
        $this->assertTrue($meta->get(2)->is_current);
        $this->assertFalse($meta->get(3)->is_current);
    }

    /** @test */
    public function it_can_determine_if_it_is_planned()
    {
        $model = Post::factory()->create();

        $model->saveMetaAt('foo', 1, '-1 day');
        $model->saveMeta('foo', 2);
        $model->saveMetaAt('foo', 3, '+1 day');

        $meta = Meta::orderBy('id')->get();

        $this->assertFalse($meta->get(0)->is_planned);
        $this->assertFalse($meta->get(1)->is_planned);
        $this->assertTrue($meta->get(2)->is_planned);
    }

    /**
     * @test
     * @dataProvider handlerProvider
     */
    public function it_can_store_and_retrieve_datatypes($type, $input)
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
     * @dataProvider handlerProvider
     */
    public function it_can_query_by_value($type, $input)
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
}
