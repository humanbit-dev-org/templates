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
		Schema::create("contacts", function (Blueprint $table) {
			$table->id();
			$table->string("name");
			$table->string("surname");
			$table->string("phone");
			$table->string("email");
			$table->integer("order_time")->nullable();
			$table->string("company")->nullable();
			$table->string("url")->nullable();
			$table->string("unit_type")->nullable();
			$table->string("type")->default("request");
			$table->mediumText("message")->nullable();
			$table->string("status")->nullable();
			$table->string("reject_reason")->nullable();
			$table->string("lang")->default("en");
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("contacts");
	}
};
