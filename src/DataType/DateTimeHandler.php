<?php

namespace Kolossal\Multiplex\DataType;

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
     * {@inheritDoc}
     *
     * @param  DateTimeInterface|string|null  $value
     */
    public function serializeValue($value): string
    {
        if ($value === '') {
            return '';
        }

        return Carbon::parse($value)->format($this->format);
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeValue(?string $value): mixed
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat($this->format, $value);
        } catch (\Exception $e) {
            return Carbon::parse($value);
        }
    }
}
