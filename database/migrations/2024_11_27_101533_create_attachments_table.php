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
		Schema::create("attachments", function (Blueprint $table) {
			$table->id();

			$table->string("title");
			$table->string("description")->nullable();
			$table->string("file_path")->nullable();
			$table->foreignId("article_id")->nullable()->constrained("articles")->onDelete("cascade");
			$table->foreignId("institutional_id")->nullable()->constrained("institutionals")->onDelete("cascade");

			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("attachments");
	}
};
