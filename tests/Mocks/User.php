<?php

namespace Kolossal\Multiplex\Tests\Mocks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Kolossal\Multiplex\HasConfigurableMorphType;

class User extends Model
{
    use HasConfigurableMorphType;
    use HasFactory;

    protected $table = 'users';

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
