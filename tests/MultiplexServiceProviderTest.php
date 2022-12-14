<?php

namespace Kolossal\Multiplex\Tests;

use Kolossal\Multiplex\MultiplexServiceProvider;

class MultiplexServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [];
    }

    /** @test */
    public function it_skips_migrations()
    {
        config(['multiplex.migrations' => false]);

        $provider = new MultiplexServiceProvider(app());
        $provider->boot();

        $this->assertEmpty(app('migrator')->paths());
    }

    /** @test */
    public function it_applies_migrations()
    {
        config()->set('multiplex.migrations', true);

        $provider = new MultiplexServiceProvider(app());
        $provider->boot();

        $expected = realpath(dirname(__DIR__) . '/database/migrations');

        $this->assertSame($expected, realpath(app('migrator')->paths()[0]));
    }
}
