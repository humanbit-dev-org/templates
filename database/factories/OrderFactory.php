<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Ecommerce;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{

    protected $model = \App\Models\Order::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "id_transaction" => fake()->uuid(),
            "import" => fake()->randomFloat(2),
            "confirm_date" => fake()->dateTimeBetween('+1 week', '+1 month'),
            'group_id' => Group::inRandomOrder()->first()->id,
            'ecommerce_id' => Ecommerce::inRandomOrder()->first()->id,
        ];
    }
}
