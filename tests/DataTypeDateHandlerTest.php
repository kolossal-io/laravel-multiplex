<?php

use Carbon\Carbon;
use Kolossal\Multiplex\DataType\DateHandler;

it('will parse to specified date format', function () {
    $handler = new DateHandler;

    expect($handler->serializeValue('2022-04-01 14:00 Europe/Berlin'))->toBe('2022-04-01');
});

it('will unserialize using specified date format if possible', function () {
    $handler = new DateHandler;

    expect($handler->unserializeValue('2022-04-01')->eq(Carbon::create(2022, 4, 1)))->toBeTrue();
});

it('will fallback to carbon parse', function () {
    $handler = new DateHandler;

    expect($handler->unserializeValue('01.04.2022 14:00 Europe/Berlin')->eq(Carbon::create(2022, 3, 31, 22)))->toBeTrue();
});

it('can handle handle date strings', function () {
    $handler = new DateHandler;

    expect($handler->canHandleValue('2024-03-29'))->toBeTrue();
    expect($handler->canHandleValue('1970-01-01'))->toBeTrue();
});

it('cannot handle handle invalid values', function () {
    $handler = new DateHandler;

    expect($handler->canHandleValue(now()))->toBeFalse();
    expect($handler->canHandleValue('2024-3-29'))->toBeFalse();
    expect($handler->canHandleValue('20240329'))->toBeFalse();
    expect($handler->canHandleValue(true))->toBeFalse();
});
