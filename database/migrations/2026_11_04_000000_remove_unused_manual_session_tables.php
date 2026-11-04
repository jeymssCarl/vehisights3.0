<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove the unused legacy manual-session tables.
     */
    public function up(): void
    {
        Schema::dropIfExists('manual_session_details');
        Schema::dropIfExists('manual_sessions');
    }

    /**
     * Restore the legacy table structures if this migration is rolled back.
     */
    public function down(): void
    {
        Schema::create('manual_sessions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('manual_session_details', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
