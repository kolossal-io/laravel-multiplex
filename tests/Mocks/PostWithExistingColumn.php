<?php

namespace Kolossal\Multiplex\Tests\Mocks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Kolossal\Multiplex\HasMeta;
use Kolossal\Multiplex\MetaAttribute;

class PostWithExistingColumn extends Model
{
    use HasMeta;
    use HasFactory;

    protected $table = 'sample_posts';

    protected $appends = [
        'title',
        'body',
    ];

    protected $metaKeys = [
        '*',
        'integer_field' => 'integer',
        'boolean_field' => 'boolean',
        'float_field' => 'float',
    ];

    protected $casts = [
        'title' => MetaAttribute::class,
        'body' => MetaAttribute::class,
    ];
}
