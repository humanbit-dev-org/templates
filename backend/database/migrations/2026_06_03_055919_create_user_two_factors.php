<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_two_factors', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 30);
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->uuid('device_id');
            // A hash, never the six digits that were mailed out.
            $table->string('token', 255)->nullable()->comment('Hash of the one-time code');
            // Two clocks, deliberately apart: token_expire covers the code
            // (minutes), trusted_until covers the device (days). One field
            // doing both is why a code used to stay valid for a month.
            $table->timestamp('token_expire')->nullable()->comment('One-time code expiry');
            $table->timestamp('trusted_until')->nullable()->comment('Device stays trusted until this moment');
            $table->boolean('token_verified')->default(false)->comment('Whether 2FA was completed on this device');
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_two_factors');
    }
};
