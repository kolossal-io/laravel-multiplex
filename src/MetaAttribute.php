<?php

namespace Kolossal\Multiplex;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<mixed, mixed>
 */
class MetaAttribute implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array<mixed>  $attributes
     */
    public function get($model, $key, $value, $attributes): mixed
    {
        if (method_exists($model, 'getMeta')) {
            return $model->getMeta($key);
        }

        return $value;
    }

    // @codeCoverageIgnoreStart
    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  Model  $model
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
