<?php

namespace Kolossal\Multiplex;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * @implements CastsAttributes<mixed, mixed>
 */
class MetaAttribute implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<mixed>  $attributes
     */
    public function get($model, $key, $value, $attributes): mixed
    {
        if (method_exists($model, 'getMeta') && method_exists($model, 'getFallbackValue')) {
            return $model->getMeta($key, $value ?? $model->getFallbackValue($key));
        }

        return $value;
    }

    // @codeCoverageIgnoreStart
    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<mixed>  $attributes
     */
    public function set($model, $key, $value, $attributes): mixed
    {
        return $value;
    }
    // @codeCoverageIgnoreEnd
}
