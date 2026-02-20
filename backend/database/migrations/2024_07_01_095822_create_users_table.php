<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create("users", function (Blueprint $table) {
			$table->string("username");
			$table->string("email")->unique();
			$table->string("name");
			$table->string("surname");
			$table->string("address")->nullable();
			$table->string("phone")->nullable();
			$table->foreignId("role_id")->constrained("roles");
			$table->foreignId("backpack_role_id")->nullable()->constrained("backpack_roles");
			$table->string("token", 6)->nullable()->comment("Two-factor authentication token");
			$table->timestamp("token_expire")->nullable()->comment("Token expiration date");
			$table->boolean("token_verified")->default(false)->comment("Whether the 2FA token has been verified");
			$table->timestamp("email_verified_at")->nullable();
			$table->string("password");
			$table->rememberToken();
			$table->timestamps();
		});

		Schema::create("password_reset_tokens", function (Blueprint $table) {
			$table->string("email")->primary();
			$table->string("token");
			$table->timestamp("created_at")->nullable();
		});

		Schema::create("sessions", function (Blueprint $table) {
			$table->string("id")->primary();
			$table->string("user_id")->nullable()->index();
			$table->string("ip_address", 45)->nullable();
			$table->text("user_agent")->nullable();
			$table->longText("payload");
			$table->integer("last_activity")->index();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("users");
		Schema::dropIfExists("password_reset_tokens");
		Schema::dropIfExists("sessions");
	}
};
