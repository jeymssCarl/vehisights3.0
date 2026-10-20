<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }

    public function down()
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->json('details')->nullable();
        });
    }
};