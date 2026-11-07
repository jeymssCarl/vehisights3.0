<?php

namespace Tests\Feature;

use Tests\TestCase;

class CameraApiContractTest extends TestCase
{
    public function test_camera_registry_requires_an_api_token(): void
    {
        $this->getJson('/api/cameras')->assertUnauthorized();
        $this->postJson('/api/cameras', [])->assertUnauthorized();
    }

    public function test_api_login_requires_the_complete_credentials_payload(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }
}
