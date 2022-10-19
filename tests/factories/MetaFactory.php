<?php

namespace Kolossal\Multiplex\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Multiplex\Meta;

class MetaFactory extends Factory
{
    protected $model = Meta::class;

    public function definition()
    {
        return [
            'key' => $this->faker->domainWord(),
            'value' => $this->faker->randomNumber(5),
        ];
    }
}
