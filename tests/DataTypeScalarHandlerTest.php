<?php

use Kolossal\Multiplex\DataType\ScalarHandler;
use Kolossal\Multiplex\DataType\StringHandler;

it('will throw an exception for invalid values', function () {
    $handler = new StringHandler;

    expect($handler)->toBeInstanceOf(ScalarHandler::class);

    expect($handler->serializeValue('Test'))->toBe('Test');
    expect($handler->serializeValue(null))->toBe('');
    expect($handler->serializeValue(145.12))->toBe('145.12');
    expect($handler->serializeValue(145))->toBe('145');
    expect($handler->serializeValue(true))->toBe('1');
    expect($handler->serializeValue(false))->toBe('');
    expect($handler->serializeValue(stream_context_create()))->toStartWith('Resource id #');

    $this->expectException(Exception::class);

    $handler->serializeValue([1, 2, 3]);
});

it('will resolve null as null', function () {
    $handler = new StringHandler;

    expect($handler)->toBeInstanceOf(ScalarHandler::class);

    expect($handler->unserializeValue(null))->toBeNull();
});
