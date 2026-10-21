<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('google_verified_at')->nullable();
        });

        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_sent_at');
            $table->timestamps();
        });

        // Accounts that used Google before this feature was introduced should
        // not unexpectedly be challenged on their next sign-in.
        DB::table('users')
            ->whereIn('id', DB::table('social_accounts')
                ->where('provider', 'google')
                ->select('user_id'))
            ->update(['google_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_verified_at');
        });
    }
};
