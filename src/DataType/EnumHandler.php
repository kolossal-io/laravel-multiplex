<?php

namespace Kolossal\Multiplex\DataType;

use BackedEnum;
use Exception;
use ReflectionEnum;

/**
 * Handle serialization of backed enums.
 */
class EnumHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDataType(): string
    {
        return 'enum';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandleValue($value): bool
    {
        return $value instanceof BackedEnum && class_exists(ReflectionEnum::class);
    }

    /**
     * Convert the value to a string, so that it can be stored in the database.
     *
     * @param  BackedEnum  $enum
     */
    public function serializeValue($enum): string
    {
        if (!$this->canHandleValue($enum)) {
            return '';
        }

        return get_class($enum) . '::' . $enum->value;
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeValue(?string $value): mixed
    {
        // @codeCoverageIgnoreStart
        if (!class_exists(ReflectionEnum::class)) {
            throw new Exception('Cannot unserialize enum value since \ReflectionEnum is not available. This will only work in PHP >= 8.1.');
        }
        // @codeCoverageIgnoreEnd

        if (is_null($value)) {
            return $value;
        }

        if (strpos($value, '::') === false) {
            return null;
        }

        [$class, $value] = explode('::', $value, 2);

        if (!enum_exists($class)) {
            return null;
        }

        if (!(new ReflectionEnum($class))->isBacked()) {
            return null;
        }

        /**
         * @var \BackedEnum $class
         */
        return $class::tryFrom($value);
    }
}
