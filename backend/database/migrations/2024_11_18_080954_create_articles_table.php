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
			$table->string("title_italian");
			$table->string("title_english")->nullable();
			$table->string("meta_title_italian")->nullable();
			$table->string("meta_title_english")->nullable();
			$table->string("subtitle_italian")->nullable();
			$table->string("subtitle_english")->nullable();
			$table->string("abstract_italian")->nullable();
			$table->string("abstract_english")->nullable();
			$table->longText("body_italian")->nullable();
			$table->longText("body_english")->nullable();
			$table->longText("meta_body_italian")->nullable();
			$table->longText("meta_body_english")->nullable();
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
