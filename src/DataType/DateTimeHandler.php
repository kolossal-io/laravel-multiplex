<?php

namespace Kolossal\Meta\DataType;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Handle serialization of DateTimeInterface objects.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
class DateTimeHandler implements HandlerInterface
{
    /**
     * The date format to use for serializing.
     *
     * @var string
     */
    protected $format = 'Y-m-d H:i:s.uO';

    /**
     * {@inheritdoc}
     */
    public function getDataType(): string
    {
        return 'datetime';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandleValue($value): bool
    {
        return $value instanceof DateTimeInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function serializeValue($value): string
    {
        return $value->format($this->format);
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeValue(string $value)
    {
        return Carbon::createFromFormat($this->format, $value);
    }
}
