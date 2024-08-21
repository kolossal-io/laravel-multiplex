<?php

use Kolossal\Multiplex\DataType\HandlerInterface;
use Kolossal\Multiplex\DataType\Registry;
use Kolossal\Multiplex\Exceptions\DataTypeException;
use Mockery\MockInterface;

it('can set a handler', function () {
    $registry = new Registry;
    $handler = mockHandlerWithType('foo');
    expect($registry->hasHandlerForType('foo'))->toBeFalse();

    $registry->addHandler($handler);

    expect($registry->hasHandlerForType('foo'))->toBeTrue();
    expect($registry->getHandlerForType('foo'))->toEqual($handler);
});

it('can remove a handler', function () {
    $registry = new Registry;
    $handler = mockHandlerWithType('foo');
    $registry->addHandler($handler);
    expect($registry->hasHandlerForType('foo'))->toBeTrue();

    $registry->removeHandlerForType('foo');

    expect($registry->hasHandlerForType('foo'))->toBeFalse();
});

it('throws an exception if no handler set', function () {
    $registry = new Registry;

    $this->expectException(DataTypeException::class);
    $registry->getHandlerForType('foo');
});

it('determines best handler for a value', function () {
    $stringHandler = mockHandlerWithType('str');
    $stringHandler->shouldReceive('canHandleValue')
        ->andReturnUsing(function ($value) {
            return is_string($value);
        });
    $integerHandler = mockHandlerWithType('int');
    $integerHandler->shouldReceive('canHandleValue')
        ->andReturnUsing(function ($value) {
            return is_int($value);
        });
    $registry = new Registry;
    $registry->addHandler($stringHandler);
    $registry->addHandler($integerHandler);

    $type1 = $registry->getTypeForValue(123);
    $type2 = $registry->getTypeForValue('abc');

    expect($type1)->toEqual('int');
    expect($type2)->toEqual('str');
});

it('throws an exception if no type matches value', function () {
    $registry = new Registry;

    $this->expectException(DataTypeException::class);

    $registry->getTypeForValue([]);
});

function mockHandlerWithType($type): MockInterface|HandlerInterface
{
    /**
     * @template TMock of HandlerInterface
     */
    $handler = Mockery::mock(HandlerInterface::class);
    $handler->shouldReceive('getDataType')->andReturn($type);

    return $handler;
}
