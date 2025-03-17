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
		Schema::create("group_user", function (Blueprint $table) {
			$table->id();
			$table->integer("percentage")->default(100);
			$table->boolean("send_invite")->default(false);

			// Defining foreign keys
			$table->foreignId("group_id")->constrained("groups")->onDelete("cascade");
			$table->foreignId("user_id")->constrained("users")->onDelete("cascade");

			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("group_user");
	}
};
