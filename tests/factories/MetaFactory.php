<?php

namespace Kolossal\Meta\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Meta\Meta;

class MetaFactory extends Factory
{
    protected $model = Meta::class;

    public function definition()
    {
        return [];
    }
}
