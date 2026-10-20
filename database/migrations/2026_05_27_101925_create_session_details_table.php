<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Create the session_details table
        Schema::create('session_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions')->onDelete('cascade');
            $table->decimal('total_pcu', 10, 2);
            $table->decimal('vc_ratio', 5, 2);
            $table->char('los', 1);
            $table->json('counts');
            $table->json('pcu_totals');
            $table->string('source', 20)->default('manual');
            $table->timestamps();
        });

        // Migrate existing data from sessions.details (JSON) to session_details
        $sessions = DB::table('sessions')->whereNotNull('details')->get();
        
        foreach ($sessions as $session) {
            $details = json_decode($session->details, true);
            if ($details && is_array($details)) {
                DB::table('session_details')->insert([
                    'session_id'   => $session->id,
                    'total_pcu'    => $details['total_pcu'] ?? 0,
                    'vc_ratio'     => $details['vc_ratio'] ?? 0,
                    'los'          => $details['los'] ?? 'N/A',
                    'counts'       => json_encode($details['counts'] ?? []),
                    'pcu_totals'   => json_encode($details['pcu_totals'] ?? []),
                    'source'       => $details['source'] ?? 'manual',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('session_details');
    }
};