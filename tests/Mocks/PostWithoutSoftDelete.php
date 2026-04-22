<?php

namespace Kolossal\Multiplex\Tests\Mocks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Kolossal\Multiplex\HasConfigurableMorphType;
use Kolossal\Multiplex\HasMeta;

class PostWithoutSoftDelete extends Model
{
    use HasConfigurableMorphType;
    use HasFactory;
    use HasMeta;

    protected $table = 'sample_posts_without_soft_delete';

    protected $metaKeys = ['*'];
}
