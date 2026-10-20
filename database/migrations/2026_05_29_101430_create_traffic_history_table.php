<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('traffic_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camera_id')->nullable()->constrained('cameras')->onDelete('set null');
            $table->string('road_name');
            $table->string('direction')->nullable();
            $table->timestamp('hour_timestamp');
            $table->decimal('total_pcu', 10, 2);
            $table->decimal('vc_ratio', 5, 2);
            $table->char('los', 1);
            $table->json('counts');
            $table->enum('source', ['manual', 'auto'])->default('manual');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('traffic_history');
    }
};