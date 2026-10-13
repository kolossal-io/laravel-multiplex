<?php

namespace Kolossal\Multiplex\Tests\PHPStan;

use Illuminate\Database\Eloquent\Model;
use Kolossal\Multiplex\HasMeta;

/**
 * PHPStan only analyses traits in the context of a class using them,
 * so this model exists to get HasMeta analysed.
 */
class MetableModel extends Model
{
    use HasMeta;
}
