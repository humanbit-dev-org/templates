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
		Schema::create("groups", function (Blueprint $table) {
			$table->id();
			$table->string("name");
			$table->boolean("private")->default(false);
			$table->integer("max_members")->default(2);

			// Defining foreign keys
			$table->foreignId("creator_id")->constrained("users")->onDelete("cascade");
			$table->foreignId("category_id")->constrained("categories");

			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("groups");
	}
};
