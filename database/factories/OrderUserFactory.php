<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderUser>
 */
class OrderUserFactory extends Factory
{

    protected $model = \App\Models\OrderUser::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $users = User::pluck('id')->toArray();
        $orders = Order::pluck('id')->toArray();

        return [
            "import" => fake()->randomFloat(2),
            "percentage" => fake()->numberBetween(0, 100),
            'order_id' => fake()->randomElement($orders),
            'user_id' => fake()->randomElement($users),
        ];
    }
}
