<?php

namespace Kolossal\Multiplex;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class MetaAttribute implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, $key, $value, $attributes)
    {
        if (method_exists($model, 'getMeta')) {
            return $model->getMeta($key, $value);
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
     * @param  array  $attributes
     * @return mixed
     */
    public function set($model, $key, $value, $attributes)
    {
        return $value;
    }
    // @codeCoverageIgnoreEnd
}
