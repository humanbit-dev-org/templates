<?php

namespace App\Http\Controllers;

use App\Http\Resources\EcommerceResource;
use Illuminate\Http\Request;
use App\Models\Ecommerce;

class EcommerceController extends Controller
{
	public function index()
	{
		$ecommerce = Ecommerce::where("showcase", true)->get();

		return response()->json([
			"ecommerce" => EcommerceResource::collection($ecommerce),
		]);
	}
}
