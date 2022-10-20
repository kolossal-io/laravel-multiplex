<?php

namespace Kolossal\Multiplex\Tests\Mocks;

use Illuminate\Database\Eloquent\Model;
use Kolossal\Multiplex\MetaAttribute;

class Dummy extends Model
{
    protected $casts = [
        'appendable_foo' => MetaAttribute::class,
    ];
}
