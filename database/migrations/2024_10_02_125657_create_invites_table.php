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
		Schema::create("invites", function (Blueprint $table) {
			$table->id();
			$table->string("status")->default("pending");
			$table->date("expire_date")->default(now()->addDays(7));
			$table->timestamps();

			$table->string("type")->default("invite");
			$table->string("email");

			$table->foreignId("sender_id")->nullable()->constrained("users")->onDelete("cascade");
			$table->foreignId("receiver_id")->nullable()->constrained("users")->onDelete("cascade");

			$table->foreignId("group_id")->constrained("groups")->onDelete("cascade");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("invites");
	}
};
