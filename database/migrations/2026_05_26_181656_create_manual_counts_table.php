<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('manual_counts', function (Blueprint $table) {
            $table->id();
            $table->string('road_name');
            $table->string('barangay')->nullable();
            $table->string('city');
            $table->string('direction_from')->nullable();
            $table->string('direction_to')->nullable();
            $table->date('count_date');
            $table->time('hour_start');
            $table->time('hour_end');
            $table->boolean('has_median');
            $table->integer('lanes');
            $table->json('counts'); // vehicle type => count
            $table->json('pcu_totals'); // vehicle type => pcu contribution
            $table->float('total_pcu');
            $table->float('vc_ratio');
            $table->char('los', 1);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('manual_counts');
    }
};