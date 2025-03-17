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
		Schema::create("translates", function (Blueprint $table) {
			$table->id();
			$table->string("it")->nullable();
			// $table->string("en")->nullable();
			$table->text("text_it")->nullable();
			// $table->text("text_en")->nullable();
			$table->string("code");
			$table->foreignId("page_id")->nullable()->constrained("pages")->onDelete("cascade");
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("translates");
	}
};
