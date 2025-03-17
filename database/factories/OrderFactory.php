<?php

namespace Database\Factories;

use Carbon\Carbon;
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
		$groupId = Group::inRandomOrder()->first()->id;
		$groupCreatedAt = Group::find($groupId)->created_at;
		$confirmDate = Carbon::instance(fake()->dateTimeBetween($groupCreatedAt, now()));
		$expireDate = Carbon::instance(
			fake()->dateTimeBetween((clone $confirmDate)->modify("+15 minutes"), (clone $confirmDate)->modify("+7 days"))
		);

		$orderAge = $confirmDate->diffInDays(now());
		$status = "expired";
		if ($orderAge >= 7) {
			$status = fake()->randomElement(["completed", "expired", "rejected"]);
		} else {
			if ($expireDate > now()) {
				$status = fake()->randomElement(["completed", "pending", "rejected"]);
			}
		}
		$completeDate =
			$status === "completed" ? Carbon::instance(fake()->dateTimeBetween($confirmDate, $expireDate)) : null;
		$transactionId = $completeDate != null ? fake()->uuid() : null;
		return [
			"name" => fake()->word(),
			"import" => fake()->randomFloat(2, 25, 5000),
			"confirm_date" => $confirmDate,
			"expire_date" => $expireDate,
			"status" => $status,
			"complete_date" => $completeDate,
			"transaction_id" => $transactionId,
			"description" => fake()->sentence(),
			"ecommerce_id" => ($ecommerceId = Ecommerce::inRandomOrder()->first()->id),
			"ecommerce_description" => fake()->sentence(),
			"ecommerce_url" => Ecommerce::find($ecommerceId)->url . "/product/" . fake()->uuid(),
			"group_id" => $groupId,
		];
	}
}
