<?php

namespace Kolossal\Meta\Tests\Mocks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kolossal\Meta\HasMeta;

class Post extends Model
{
    use HasMeta;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'sample_posts';

    public function setTestHasMutatorMeta($value)
    {
        return "Test {$value}.";
    }
}
