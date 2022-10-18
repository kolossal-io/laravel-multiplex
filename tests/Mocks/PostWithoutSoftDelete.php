<?php

namespace Kolossal\Meta\Tests\Mocks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Kolossal\Meta\HasMeta;

class PostWithoutSoftDelete extends Model
{
    use HasMeta;
    use HasFactory;

    protected $table = 'sample_posts';
}
