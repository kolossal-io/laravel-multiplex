<?php

namespace Kolossal\Multiplex\Tests\Mocks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kolossal\Multiplex\HasMeta;
use Kolossal\Multiplex\MetaAttribute;

class Post extends Model
{
    use HasMeta;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'sample_posts';

    protected $casts = [
        'appendable_foo' => MetaAttribute::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function setTestHasMutatorMeta($value)
    {
        return "Test {$value}.";
    }

    public function getTestHasAccessorMeta($value)
    {
        return $value ? "Test {$value}." : 'Empty';
    }
}
