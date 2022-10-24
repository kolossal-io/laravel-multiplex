<?php

namespace Kolossal\Multiplex\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;
use stdClass;

class HasMetaScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Post::travelBack();
    }

    /** @test */
    public function it_scopes_where_has_meta()
    {
        $this->seedModels();

        $this->testScope(Post::whereHasMeta('one'), ['a']);
        $this->testScope(Post::whereHasMeta('two'), ['a', 'b']);
        $this->testScope(Post::whereHasMeta('three'), ['c']);
        $this->testScope(Post::whereHasMeta('four'));
        $this->testScope(Post::whereHasMeta('one')->whereHasMeta('two'), 'a');
        $this->testScope(Post::whereHasMeta('one')->orWhereHasMeta('three'), 'a,c');
    }

    /** @test */
    public function it_scopes_where_has_meta_from_array()
    {
        $this->seedModels();

        $this->testScope(Post::whereHasMeta(['one', 'two']), ['a', 'b']);
        $this->testScope(Post::whereHasMeta(['two', 'three']), ['a', 'b', 'c']);
        $this->testScope(Post::whereHasMeta(['one', 'two'])->orWhereHasMeta('three'), 'a,b,c');
    }

    /** @test */
    public function it_scopes_where_doesnt_have_meta()
    {
        $this->seedModels();

        $this->testScope(Post::whereDoesntHaveMeta('one'), 'b,c');
        $this->testScope(Post::whereDoesntHaveMeta('two'), 'c');
        $this->testScope(Post::whereDoesntHaveMeta('three'), 'a,b');
        $this->testScope(Post::whereDoesntHaveMeta('four'), 'a,b,c');
        $this->testScope(Post::whereHasMeta('three')->orWhereDoesntHaveMeta('one'), 'b,c');
        $this->testScope(Post::whereDoesntHaveMeta(['one', 'two']), 'c');
        $this->testScope(Post::whereDoesntHaveMeta(['one', 'three']), 'b');
    }

    /** @test */
    public function it_scopes_where_doesnt_have_meta_from_array()
    {
        $this->seedModels();

        $this->testScope(Post::whereDoesntHaveMeta(['one']), 'b,c');
        $this->testScope(Post::whereDoesntHaveMeta(['two', 'three']));
    }

    /** @test */
    public function it_scopes_where_meta()
    {
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

        $this->testScope(Post::whereMeta('foo', false), 'a,c');
        $this->testScope(Post::whereMeta('foo', true), 'b');
        $this->testScope(Post::whereMeta('foo', true)->orWhereMeta('bar', 12), 'b,c');
    }

    /**
     * @test
     * @dataProvider datatypeProvider
     * */
    public function it_scopes_where_meta_with_datatype($type, $input, $another)
    {
        Post::factory()
            ->has(Meta::factory()->state(['key' => 'foo', 'value' => $another, 'published_at' => Carbon::now()->addDay()]))
            ->create(['title' => 'a']);

        Post::factory()
            ->has(Meta::factory()->state(['key' => 'foo', 'value' => $input]))
            ->create(['title' => 'b']);

        $this->testScope(Post::whereMeta('foo', $input), 'b');
        $this->testScope(Post::whereMeta('foo', $another));

        $this->travelTo('+1 day');

        $this->testScope(Post::whereMeta('foo', $another), 'a');
    }

    /** @test */
    public function it_scopes_where_meta_with_operators()
    {
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

        $this->testScope(Post::whereMeta('foo', '!=', 5), 'a');
        $this->testScope(Post::whereMeta('foo', '<', 5), 'a');
        $this->testScope(Post::whereMeta('foo', '>', Carbon::parse('2020-01-01')), 'b');
        $this->testScope(Post::whereMeta('foo', '>', Carbon::parse('2022-01-01')));
        $this->testScope(Post::whereMeta('foo', '>=', 4), 'a');
        $this->testScope(Post::whereMeta('foo', '<=', 0));
        $this->testScope(Post::whereMeta('bar', '<=', 0), 'b,d');
        $this->testScope(Post::whereMeta('foo', '<=', true), 'c');
        $this->testScope(Post::whereMeta('foo', '=', true));
        $this->testScope(Post::whereMeta('foo', '=', false), 'c');
        $this->testScope(Post::whereMeta('foo', '!=', true), 'c');
        $this->testScope(Post::whereMeta('foo', '!=', true)->orWhereMeta('bar', '<', 0), 'c,d');
    }

    /** @test */
    public function it_scopes_where_raw_meta()
    {
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

        $this->testScope(Post::whereRawMeta('foo', '0'), 'c');
        $this->testScope(Post::whereRawMeta('foo', '!=', ''), 'a,b,c');
        $this->testScope(Post::whereRawMeta('foo', '!=', 4), 'b,c');
        $this->testScope(Post::whereRawMeta('foo', '<', '2021-09-01 00:00:00'), 'c');
        $this->testScope(Post::whereRawMeta('foo', '<', '2022-02-01 00:00:00'), 'b,c');
        $this->testScope(Post::whereRawMeta('foo', '>=', 4), 'a');
        $this->testScope(Post::whereRawMeta('foo', '<=', 0), 'c');
        $this->testScope(Post::whereRawMeta('bar', '<=', 0), 'b,d');
        $this->testScope(Post::whereRawMeta('bar', '<', 0), 'd');
        $this->testScope(Post::whereRawMeta('foo', '<=', true), 'c');
        $this->testScope(Post::whereRawMeta('foo', '=', 'true'));
        $this->testScope(Post::whereRawMeta('foo', '=', ''));
        $this->testScope(Post::whereRawMeta('foo', '=', '0'), 'c');
        $this->testScope(Post::whereRawMeta('foo', '!=', ''), 'a,b,c');
        $this->testScope(Post::whereRawMeta('foo', '!=', '')->orWhereRawMeta('foo', '<', '0'), 'a,b,c');
    }

    /** @test */
    public function it_scopes_where_meta_of_type()
    {
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

        $this->testScope(Post::whereMeta('bar', 0), 'b');
        $this->testScope(Post::whereMetaOfType('boolean', 'bar', null));
        $this->testScope(Post::whereMetaOfType('integer', 'bar', '0'), 'b');
        $this->testScope(Post::whereMeta('foo', null), 'c');
        $this->testScope(Post::whereMetaOfType('boolean', 'foo', null));
        $this->testScope(Post::whereMetaOfType('null', 'foo', ''), 'c');
        $this->testScope(Post::whereMeta('bar', -4), 'd');
        $this->testScope(Post::whereMetaOfType('string', 'bar', -4));
        $this->testScope(Post::whereMetaOfType('integer', 'bar', -4), 'd');
        $this->testScope(Post::whereMetaOfType('integer', 'bar', -4)->orWhereMetaOfType('null', 'foo', ''), 'c,d');
    }

    /** @test */
    public function it_scopes_where_meta_in_array()
    {
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

        $this->testScope(Post::whereMetaIn('foo', ['one', 'three', 2]), 'a,b');
        $this->testScope(Post::whereMetaIn('foo', ['one', 'three', '2']), 'b');
        $this->testScope(Post::whereMetaIn('foo', [2, 4, 5]), 'a,c');
        $this->testScope(Post::whereMetaIn('foo', ['one', 2, 4.0]), 'a,b');
        $this->testScope(Post::whereMeta('foo', 'one')->orWhereMetaIn('foo', [2, 3, 4]), 'a,b,c');
    }

    /** @test */
    public function it_scopes_after_time_traveling()
    {
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

        $this->testScope(Post::whereMetaIn('foo', [false, 'bar']), 'a,c');
        $this->testScope(Post::travelTo('-23 hours')->whereMetaIn('foo', [false, 'bar']));
        $this->testScope(Post::travelTo('-23 hours')->whereMetaIn('foo', [true, 'history']), 'a,c');
        $this->testScope(Post::whereMetaIn('foo', [true, 'history'])->travelTo('-23 hours'), 'a,c');
        $this->testScope(Post::travelTo('-25 hours')->whereMetaIn('foo', [true, 'history']), 'c');
        $this->testScope(Post::travelTo('+1 day')->whereMetaIn('foo', [false, 'history']), 'c');
        $this->testScope(Post::travelTo('+2 days')->whereMetaIn('foo', [false, 'future']), 'a,c');

        $this->testScope(Post::travelTo('-2 days')->whereMeta('bar', 'foo'));
        $this->testScope(Post::travelBack()->whereMeta('bar', 'foo'), 'b');

        $this->testScope(Post::travelTo('-50 hours')->whereMeta('bar', '>=', 123));
        $this->testScope(Post::travelTo('+3 days')->whereMeta('bar', '>=', 123), 'c');
    }

    /** @test */
    public function it_scopes_where_meta_empty()
    {
        Post::factory()
            ->has(Meta::factory()->state(['key' => 'foo', 'value' => '']))
            ->has(Meta::factory()->state(['key' => 'foo', 'value' => 12]))
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

        $this->testScope(Post::whereMetaEmpty('foo'), 'b,c,e');
        $this->testScope(Post::whereMeta('foo', false)->orWhereMetaEmpty('bar'), 'a,b,c,d');
    }

    /** @test */
    public function it_scopes_where_meta_not_empty()
    {
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

        $this->testScope(Post::whereMetaNotEmpty('foo'), 'a,d');
        $this->testScope(Post::whereMeta('foo', false)->orWhereMetaNotEmpty('bar'), 'b,d,e');
        $this->testScope(Post::whereMetaNotEmpty(['foo', 'bar']), 'd');
        $this->testScope(Post::whereMetaNotEmpty(['foo', 'bar', 'another']));
    }

    protected function seedModels()
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

    protected function testScope(
        Builder $query,
        $expectedFields = [],
        string $fieldName = 'title'
    ): Collection {
        $result = $query->get();

        if (is_string($expectedFields)) {
            $expectedFields = explode(',', $expectedFields);
        }

        $this->assertCount(count($expectedFields), $result);
        $this->assertEquals($expectedFields, $result->pluck($fieldName)->toArray());

        return $result;
    }

    public function datatypeProvider()
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
                new stdClass(),
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
    }
}
