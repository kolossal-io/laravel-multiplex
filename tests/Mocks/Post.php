<?php

namespace Kolossal\Meta\Tests\Mocks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kolossal\Meta\HasMeta;
use Kolossal\Meta\MetaAttribute;

class Post extends Model
{
    use HasMeta;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'sample_posts';

    protected $casts = [
        'appendable_foo' => MetaAttribute::class,
    ];

    public function setTestHasMutatorMeta($value)
    {
        return "Test {$value}.";
    }

    public function getTestHasAccessorMeta($value)
    {
        return $value ? "Test {$value}." : 'Empty';
    }
}
