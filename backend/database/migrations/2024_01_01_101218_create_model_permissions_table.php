<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create("model_permissions", function (Blueprint $table) {
			$table->id();
			$table->foreignId("backpack_role_id")->nullable()->constrained("backpack_roles");
			$table->foreignId("role_id")->nullable()->constrained("roles");
			$table->json("model_name");
			$table->boolean("can_read")->default(false);
			$table->boolean("can_create")->default(false);
			$table->boolean("can_update")->default(false);
			$table->boolean("can_delete")->default(false);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("model_permissions");
	}
};
