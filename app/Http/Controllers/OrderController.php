<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{
	public function index($id)
	{
		$user = Auth::user();

		try {
			$order = Order::where("id", $id)
				->whereHas("group", function ($query) use ($user) {
					$query->whereIn("groups.id", $user->groups->pluck("id"));
				})
				->with("details")
				->first();

			if (!$order) {
				return response()->json(
					[
						"message" => "Order not found for this user.",
					],
					404
				);
			}

			$expireDate = Carbon::parse($order->expire_date);

			if ($order->status === "pending" && $expireDate->isPast()) {
				$order->status = "expired";
				$order->save();
			}

			return new OrderResource($order);
		} catch (\Exception $e) {
			return response()->json(
				[
					"message" => "Error retrieving order",
					"error" => $e->getMessage(),
				],
				500
			);
		}
	}
}
