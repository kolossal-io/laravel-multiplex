<?php

use Carbon\Carbon;
use Kolossal\Multiplex\DataType\DateTimeHandler;

it('will parse to specified datetime format', function () {
    $handler = new DateTimeHandler;

    expect($handler->serializeValue('2022-04-01 14:00 Europe/Berlin'))->toBe('2022-04-01 14:00:00.000000+0200');
});

it('will unserialize using specified datetime format if possible', function () {
    $handler = new DateTimeHandler;

    expect($handler->unserializeValue('2022-04-01 14:00:00.000000+0200')->eq(Carbon::create(2022, 4, 1, 12)))->toBeTrue();
});

it('will fallback to carbon parse', function () {
    $handler = new DateTimeHandler;

    expect($handler->unserializeValue('01.04.2022 14:00 Europe/Berlin')->eq(Carbon::create(2022, 4, 1, 12)))->toBeTrue();
});
