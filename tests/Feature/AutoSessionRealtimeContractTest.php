<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AutoSessionRealtimeContractTest extends TestCase
{
    public function test_live_count_updates_require_an_api_token(): void
    {
        $this->patchJson('/api/auto-sessions/999999/counts', [
            'counts' => array_fill(0, 8, 0),
        ])->assertUnauthorized();
    }

    public function test_the_full_camera_session_lifecycle_requires_an_api_token(): void
    {
        $this->getJson('/api/schedules/pending')->assertUnauthorized();
        $this->postJson('/api/auto-sessions/start', ['schedule_id' => 999999])->assertUnauthorized();
        $this->postJson('/api/auto-sessions/999999/complete', [
            'counts' => array_fill(0, 8, 0),
        ])->assertUnauthorized();
    }

    public function test_camera_session_api_routes_require_the_write_ability(): void
    {
        foreach ([
            'api/schedules/pending',
            'api/auto-sessions/start',
            'api/auto-sessions/{autoSession}/counts',
            'api/auto-sessions/{autoSession}/complete',
        ] as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($route) => $route->uri() === $uri);

            $this->assertNotNull($route, "Missing API route: {$uri}");
            $this->assertContains('ability:auto-session:write', $route->gatherMiddleware());
        }
    }

    public function test_website_start_and_live_data_require_a_login(): void
    {
        $this->postJson('/auto-sessions/start', ['schedule_id' => 999999])
            ->assertUnauthorized();

        $this->postJson('/auto-sessions/999999/stop')
            ->assertUnauthorized();

        $this->get('/auto-records/data')
            ->assertRedirect('/login');
    }
}
