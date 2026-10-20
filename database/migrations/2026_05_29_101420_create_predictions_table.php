<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camera_id')->nullable()->constrained('cameras')->onDelete('set null');
            $table->string('road_name')->nullable();
            $table->string('direction')->nullable();
            $table->enum('prediction_type', ['hourly', 'daily', 'weekly'])->default('hourly');
            $table->date('prediction_date');
            $table->integer('prediction_hour')->nullable();
            $table->decimal('predicted_pcu', 10, 2);
            $table->decimal('predicted_vc_ratio', 5, 2);
            $table->char('predicted_los', 1);
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->decimal('actual_pcu', 10, 2)->nullable();
            $table->string('model_version')->default('v1.0');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('predictions');
    }
};