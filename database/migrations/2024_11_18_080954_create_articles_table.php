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
		Schema::create("articles", function (Blueprint $table) {
			$table->id();
			$table->string("title_it");
			$table->string("title_en")->nullable();
			$table->string("meta_title_it")->nullable();
			$table->string("meta_title_en")->nullable();
			$table->string("subtitle_it")->nullable();
			$table->string("subtitle_en")->nullable();
			$table->string("abstract_it")->nullable();
			$table->string("abstract_en")->nullable();
			$table->longText("body_it")->nullable();
			$table->longText("body_en")->nullable();
			$table->longText("meta_body_it")->nullable();
			$table->longText("meta_body_en")->nullable();
			$table->string("author")->nullable();
			$table->boolean("published")->default(false);
			$table->boolean("strillo")->default(false);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("articles");
	}
};
