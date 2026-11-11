<?php

namespace Tests\Feature;

use App\Http\Controllers\AutoRecordsController;
use App\Models\AutoSession;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class AutoRecordDeletionLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('recurrence')->default('once');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('auto_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('schedule_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auto_session_id')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_deleting_a_scheduled_record_retires_its_one_time_schedule(): void
    {
        $session = $this->createSession('scheduled');

        $this->retire($session);

        $this->assertDatabaseHas('schedules', ['id' => 1, 'is_active' => false]);
        $this->assertDatabaseHas('schedule_logs', [
            'auto_session_id' => $session->id,
            'status' => 'failed',
        ]);
    }

    public function test_deleting_an_ended_record_also_retires_its_one_time_schedule(): void
    {
        $session = $this->createSession('ended');

        $this->retire($session);

        $this->assertDatabaseHas('schedules', ['id' => 1, 'is_active' => false]);
    }

    private function createSession(string $status): AutoSession
    {
        DB::table('schedules')->insert([
            'id' => 1,
            'recurrence' => 'once',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('auto_sessions')->insert([
            'id' => 1,
            'schedule_id' => 1,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('schedule_logs')->insert([
            'auto_session_id' => 1,
            'status' => $status === 'scheduled' ? 'pending' : 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AutoSession::query()->with('schedule')->findOrFail(1);
    }

    private function retire(AutoSession $session): void
    {
        $method = new ReflectionMethod(AutoRecordsController::class, 'retireDeletedSession');
        $method->setAccessible(true);
        $method->invoke(new AutoRecordsController(), $session);
    }
}
