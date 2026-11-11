<?php

namespace Tests\Feature;

use App\Http\Controllers\ScheduleController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class ScheduleConflictValidationTest extends TestCase
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

        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            $table->string('status');
        });
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('road_name');
            $table->string('barangay')->nullable();
            $table->unsignedBigInteger('camera_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('recurrence')->default('once');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('auto_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('status');
        });

        DB::table('cameras')->insert([
            ['id' => 1, 'status' => 'online'],
            ['id' => 2, 'status' => 'online'],
        ]);
        DB::table('schedules')->insert([
            'id' => 1,
            'road_name' => 'National Highway',
            'barangay' => 'Barangay One',
            'camera_id' => 1,
            'start_date' => '2099-01-10',
            'end_date' => null,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence' => 'once',
            'is_active' => true,
        ]);
        DB::table('auto_sessions')->insert([
            'id' => 1,
            'schedule_id' => 1,
            'status' => 'scheduled',
        ]);
    }

    public function test_matching_station_and_barangay_cannot_overlap(): void
    {
        try {
            $this->validate($this->payload([
                'start_time' => '09:30',
                'end_time' => '10:30',
            ]));
            $this->fail('Expected an overlapping schedule validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_time', $exception->errors());
        }
    }

    public function test_same_station_in_a_different_barangay_and_camera_is_allowed(): void
    {
        $validated = $this->validate($this->payload([
            'barangay' => 'Barangay Two',
            'camera_id' => 2,
            'start_time' => '09:30',
            'end_time' => '10:30',
        ]));

        $this->assertSame('Barangay Two', $validated['barangay']);
    }

    public function test_same_camera_cannot_be_double_booked_for_another_location(): void
    {
        try {
            $this->validate($this->payload([
                'road_name' => 'Another Road',
                'barangay' => 'Another Barangay',
                'camera_id' => 1,
                'start_time' => '09:30',
                'end_time' => '10:30',
            ]));
            $this->fail('Expected a camera overlap validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_time', $exception->errors());
        }
    }

    public function test_an_orphaned_one_time_schedule_does_not_block_a_new_session(): void
    {
        DB::table('auto_sessions')->where('schedule_id', 1)->delete();

        $validated = $this->validate($this->payload([
            'camera_id' => 1,
            'start_time' => '09:30',
            'end_time' => '10:30',
        ]));

        $this->assertSame('09:30', $validated['start_time']);
    }

    private function validate(array $payload): array
    {
        $method = new ReflectionMethod(ScheduleController::class, 'validateSchedule');
        $method->setAccessible(true);

        return $method->invoke(new ScheduleController(), Request::create('/schedules', 'POST', $payload));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'schedule_name' => 'Test schedule',
            'road_name' => 'National Highway',
            'barangay' => 'Barangay One',
            'city' => 'Sto. Tomas',
            'direction_from' => 'North',
            'direction_to' => 'South',
            'day_classification' => null,
            'lanes' => 2,
            'has_median' => false,
            'camera_id' => 2,
            'start_date' => '2099-01-10',
            'end_date' => null,
            'start_time' => '11:00',
            'end_time' => '12:00',
            'recurrence' => 'once',
            'recurrence_days' => null,
            'is_active' => true,
        ], $overrides);
    }
}
