<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    public function test_login_displays_google_and_not_apple(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in with Google')
            ->assertDontSee('Sign in with Apple');
    }

    public function test_unconfigured_google_redirect_returns_to_login_with_a_message(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get('/auth/google/redirect')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');
    }

    public function test_authenticated_user_can_logout_with_post(): void
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_active' => true,
        ]);
        $user->id = 999;

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
