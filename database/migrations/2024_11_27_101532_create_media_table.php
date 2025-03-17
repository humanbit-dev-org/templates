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
		Schema::create("media", function (Blueprint $table) {
			$table->id();
			$table->string("title");
			$table->string("image_path")->nullable();
			$table->string("mp4_path")->nullable();
			$table->string("ogg_path")->nullable();
			$table->string("ogv_path")->nullable();
			$table->string("webm_path")->nullable();
			$table->string("mp3_path")->nullable();
			$table->string("caption")->nullable();
			$table->foreignId("page_id")->nullable()->constrained("pages")->onDelete("cascade");
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("media");
	}
};
