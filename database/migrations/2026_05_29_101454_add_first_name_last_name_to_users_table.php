<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable();
            }
        });

        // Migrate existing 'name' column to first_name/last_name if needed
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            if ($user->name && (!$user->first_name || !$user->last_name)) {
                $parts = explode(' ', $user->name, 2);
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'first_name' => $parts[0] ?? '',
                        'last_name' => $parts[1] ?? '',
                    ]);
            }
        }
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};