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
		Schema::create("seo_meta_information", function (Blueprint $table) {
			$table->id();
			$table->string("it");
			$table->string("en")->nullable();
			$table->string("image_path")->nullable();
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
		Schema::dropIfExists("seo_meta_information");
	}
};
