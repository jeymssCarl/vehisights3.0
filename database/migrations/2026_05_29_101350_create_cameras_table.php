<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address')->unique();
            $table->string('location');
            $table->string('stream_url')->nullable();
            $table->enum('status', ['online', 'offline'])->default('offline');
            $table->timestamp('last_detection_at')->nullable();
            $table->integer('detection_sensitivity')->default(50);
            $table->json('roi')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cameras');
    }
};