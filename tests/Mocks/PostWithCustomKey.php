<?php

namespace Kolossal\Multiplex\Tests\Mocks;

use Illuminate\Database\Eloquent\Model;
use Kolossal\Multiplex\HasConfigurableMorphType;
use Kolossal\Multiplex\HasMeta;

class PostWithCustomKey extends Model
{
    use HasConfigurableMorphType;
    use HasMeta;

    protected $table = 'sample_posts_custom_key';

    protected $primaryKey = 'post_id';
}
