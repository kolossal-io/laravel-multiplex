<?php

namespace Kolossal\Multiplex\Events;

use Kolossal\Multiplex\Meta;

class MetaHasBeenRemoved
{
    public string $type;

    /** @var \Illuminate\Database\Eloquent\Model */
    public $model;

    public function __construct(public Meta $meta)
    {
        $this->type = $meta->metable_type;

        /** @var \Illuminate\Database\Eloquent\Model */
        $model = $meta->metable;
        $this->model = $model;
    }
}
