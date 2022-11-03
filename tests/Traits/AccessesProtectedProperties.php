<?php

namespace Kolossal\Multiplex\Tests\Traits;

use ReflectionClass;

trait AccessesProtectedProperties
{
    protected function getProtectedProperty($model, string $property)
    {
        $reflectedClass = new ReflectionClass($model);
        $reflection = $reflectedClass->getProperty($property);
        $reflection->setAccessible(true);

        return $reflection->getValue($model);
    }
}
