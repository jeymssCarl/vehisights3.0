<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('auto_session_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auto_session_id')->constrained('auto_sessions')->onDelete('cascade');
            $table->decimal('total_pcu', 10, 2);
            $table->decimal('vc_ratio', 5, 2);
            $table->char('los', 1);
            $table->json('counts');
            $table->json('pcu_totals');
            $table->string('source', 20)->default('auto');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('auto_session_details');
    }
};