<?php

namespace Kolossal\Multiplex\Tests;

use PHPUnit\Framework\Attributes\Test;
use Kolossal\Multiplex\DataType\HandlerInterface;
use Kolossal\Multiplex\DataType\Registry;
use Kolossal\Multiplex\Exceptions\DataTypeException;

class RegistryTest extends TestCase
{
    #[Test]
    public function it_can_set_a_handler()
    {
        $registry = new Registry();
        $handler = $this->mockHandlerWithType('foo');
        $this->assertFalse($registry->hasHandlerForType('foo'));

        $registry->addHandler($handler);

        $this->assertTrue($registry->hasHandlerForType('foo'));
        $this->assertEquals($handler, $registry->getHandlerForType('foo'));
    }

    #[Test]
    public function it_can_remove_a_handler()
    {
        $registry = new Registry();
        $handler = $this->mockHandlerWithType('foo');
        $registry->addHandler($handler);
        $this->assertTrue($registry->hasHandlerForType('foo'));

        $registry->removeHandlerForType('foo');

        $this->assertFalse($registry->hasHandlerForType('foo'));
    }

    #[Test]
    public function it_throws_an_exception_if_no_handler_set()
    {
        $registry = new Registry();

        $this->expectException(DataTypeException::class);
        $registry->getHandlerForType('foo');
    }

    #[Test]
    public function it_determines_best_handler_for_a_value()
    {
        $stringHandler = $this->mockHandlerWithType('str');
        $stringHandler->method('canHandleValue')
            ->will($this->returnCallback(function ($value) {
                return is_string($value);
            }));
        $integerHandler = $this->mockHandlerWithType('int');
        $integerHandler->method('canHandleValue')
            ->will($this->returnCallback(function ($value) {
                return is_int($value);
            }));
        $registry = new Registry();
        $registry->addHandler($stringHandler);
        $registry->addHandler($integerHandler);

        $type1 = $registry->getTypeForValue(123);
        $type2 = $registry->getTypeForValue('abc');

        $this->assertEquals('int', $type1);
        $this->assertEquals('str', $type2);
    }

    #[Test]
    public function it_throws_an_exception_if_no_type_matches_value()
    {
        $registry = new Registry();

        $this->expectException(DataTypeException::class);

        $registry->getTypeForValue([]);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|HandlerInterface
     */
    protected function mockHandlerWithType($type): HandlerInterface
    {
        $handler = $this->createMock(HandlerInterface::class);
        $handler->method('getDataType')->willReturn($type);

        return $handler;
    }
}
