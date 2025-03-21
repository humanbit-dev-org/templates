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
		Schema::create("ecommerce", function (Blueprint $table) {
			$table->id();
			$table->string("name");
			$table->string("url");
			$table->string("image_path")->nullable();
			$table->boolean("showcase");
			$table->foreignId("user_id")->nullable()->constrained("users")->onDelete("cascade");
			$table->string("public_key")->nullable();
			$table->string("unit_type")->nullable();
			$table->integer("order_time")->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("ecommerce");
	}
};
