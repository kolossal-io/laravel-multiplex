<?php

use Kolossal\Multiplex\MultiplexServiceProvider;
use Mockery\MockInterface;

it('skips migrations if disabled', function () {
    config()->set('multiplex.migrations', false);

    /**
     * @var MultiplexServiceProvider|MockInterface
     */
    $mock = Mockery::mock(MultiplexServiceProvider::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $mock->shouldReceive('runningInConsole')->andReturn(true);

    $mock->shouldNotReceive('loadMultiplexMigrations');

    $mock->boot();
});

it('applies migrations if enabled', function () {
    config()->set('multiplex.migrations', true);

    /**
     * @var MultiplexServiceProvider|MockInterface
     */
    $mock = Mockery::mock(MultiplexServiceProvider::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $mock->shouldReceive('runningInConsole')->andReturn(true);

    $mock->expects('loadMultiplexMigrations');

    $mock->boot();
});
