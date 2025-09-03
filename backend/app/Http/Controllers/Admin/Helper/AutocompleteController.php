<?php

namespace App\Http\Controllers\Admin\Helper;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;

class AutocompleteController extends Controller
{
	public function getValues(Request $request)
	{
		$column = $request->input("column"); // Column name
		$table = $request->input("table"); // Table name
		$term = $request->input("term"); // Search term

		// Validate that the table and column exist to prevent SQL injection
		if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
			return response()->json([]);
		}

		// Fetch unique values from the given table & column
		$values = DB::table($table)
			->select($column)
			->where($column, "LIKE", "%$term%")
			->distinct()
			->limit(10)
			->pluck($column);

		return response()->json($values);
	}
}
