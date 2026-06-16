<?php

namespace Kolossal\Multiplex\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Multiplex\Meta;

class MetaFactory extends Factory
{
    protected $model = Meta::class;

    protected function getValue(): mixed
    {
        return $this->faker->randomElement([
            $this->faker->word(),
            $this->faker->numberBetween(),
            $this->faker->dateTime(),
        ]);
    }

    public function definition()
    {
        return [
            'key' => $this->faker->unique()->domainWord(),
            'value' => $this->getValue(),
        ];
    }
}
