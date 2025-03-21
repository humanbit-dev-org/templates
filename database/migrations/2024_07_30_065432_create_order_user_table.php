<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create("order_user", function (Blueprint $table) {
			$table->id();
			$table->string("transaction_id")->nullable();
			$table->float("import")->default(0);
			$table->string("status");
			$table->dateTime("complete_date")->nullable();

			// Defining foreign keys
			$table->foreignId("order_id")->constrained("orders")->onDelete("cascade");
			$table->foreignId("user_id")->constrained("users")->onDelete("cascade");

			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("order_user");
	}
};
