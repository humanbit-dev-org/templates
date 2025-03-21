<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderUser;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Nette\Utils\Random;

class OrderUserSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$orders = Order::all();

		foreach ($orders as $order) {
			$users = $order->group->users;
			$orderStatus = $order->status;
			$orderImport = $order->import;
			$orderConfirmDate = $order->confirm_date;
			$orderExpireDate = $order->expire_date;
			$userImport = 0;
			if ($users->count() > 0) {
				$userImport = $orderImport / $users->count();
				round($userImport, 2);
			}
			$paidUser = random_int(0, $users->count() - 1);
			$count = 0;

			foreach ($users as $user) {
				if ($orderStatus === "completed") {
					OrderUser::create([
						"import" => $userImport,
						"transaction_id" => fake()->uuid(),
						"order_id" => $order->id,
						"user_id" => $user->id,
						"complete_date" => fake()->dateTimeBetween($orderConfirmDate, $orderExpireDate),
						"status" => "completed",
					]);
				} elseif ($orderStatus === "expired" || $orderStatus === "pending") {
					if ($count < $paidUser) {
						OrderUser::create([
							"import" => $userImport,
							"transaction_id" => fake()->uuid(),
							"order_id" => $order->id,
							"user_id" => $user->id,
							"complete_date" => fake()->dateTimeBetween($orderConfirmDate, $orderExpireDate),
							"status" => "completed",
						]);
						$count++;
					} else {
						OrderUser::create([
							"order_id" => $order->id,
							"user_id" => $user->id,
							"status" => fake()->randomElement(["pending", "rejected"]),
						]);
					}
				} else {
					break;
				}
			}
		}
	}
}
