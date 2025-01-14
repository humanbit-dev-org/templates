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
			$table->unsignedBigInteger("article_id")->nullable();
			$table->foreign("article_id")->references("id")->on("articles")->onDelete("cascade");

			$table->unsignedBigInteger("institutional_id")->nullable();
			$table->foreign("institutional_id")->references("id")->on("institutionals")->onDelete("cascade");
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
