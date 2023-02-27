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
class DateHandler implements HandlerInterface
{
    /**
     * The date format to use for serializing.
     *
     * @var string
     */
    protected $format = 'Y-m-d';

    /**
     * {@inheritdoc}
     */
    public function getDataType(): string
    {
        return 'date';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandleValue($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return (bool) preg_match('/\d{4}-\d{2}-\d{2}/i', $value);
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
    public function unserializeValue(?string $value)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        return tap(Carbon::createFromFormat($this->format, $value), function ($date) {
            if ($date instanceof Carbon) {
                $date->startOfDay();
            }
        });
    }
}
