<?php

namespace Kolossal\Meta\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kolossal\Meta\Tests\Mocks\Post;

class HasMetaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_set_any_keys_as_meta_by_default()
    {
        $this->assertDatabaseCount('meta', 0);

        $model = Post::factory()->create();

        $model->setMeta('foo', 'bar');
        $model->setMeta('another', 125.12);
        $model->save();

        $this->assertDatabaseCount('meta', 2);
    }
}
