<?php

namespace Kolossal\Multiplex\Tests;

use PHPUnit\Framework\Attributes\Test;
use Kolossal\Multiplex\MultiplexServiceProvider;

class MultiplexServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [];
    }

    #[Test]
    public function it_skips_migrations(): void
    {
        config(['multiplex.migrations' => false]);

        $provider = new MultiplexServiceProvider(app());
        $provider->boot();

        $this->assertEmpty(app('migrator')->paths());
    }

    #[Test]
    public function it_applies_migrations(): void
    {
        config()->set('multiplex.migrations', true);

        $provider = new MultiplexServiceProvider(app());
        $provider->boot();

        $expected = realpath(dirname(__DIR__) . '/database/migrations');

        $this->assertSame($expected, realpath(app('migrator')->paths()[0]));
    }
}
