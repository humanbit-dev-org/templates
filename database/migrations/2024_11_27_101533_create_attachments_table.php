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

			// $table->foreignId("thought_id")->nullable()->constrained("thoughts")->onDelete("cascade");
			// $table->foreignId("institutional_id")->nullable()->constrained("institutionals")->onDelete("cascade");
			// $table->foreignId("chapter_id")->nullable()->constrained("chapters")->onDelete("cascade");
			// $table->foreignId("president_id")->nullable()->constrained("presidents")->onDelete("cascade");

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
