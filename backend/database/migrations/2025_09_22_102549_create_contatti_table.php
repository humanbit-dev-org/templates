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
		Schema::create("contatti", function (Blueprint $table) {
			$table->id();
			$table->string("email")->nullable();
			// Label of the subject chosen in the contact form (see Contatto::OGGETTI).
			$table->string("oggetto")->nullable();
			$table->text("messaggio")->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("contatti");
	}
};
