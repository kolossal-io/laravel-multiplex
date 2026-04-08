<?php

namespace Kolossal\Multiplex\Events;

use Illuminate\Database\Eloquent\Model;
use Kolossal\Multiplex\Meta;

class MetaHasBeenRemoved
{
    public string $type;

    /** @var Model */
    public $model;

    public function __construct(public Meta $meta)
    {
        $this->type = $meta->metable_type;

        /** @var Model */
        $model = $meta->metable;
        $this->model = $model;
    }
}
