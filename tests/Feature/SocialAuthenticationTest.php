<?php

namespace Tests\Feature;

use App\Mail\GoogleVerificationCodeMail;
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

    public function test_google_verification_page_requires_an_oauth_session(): void
    {
        $this->get('/auth/google/verify')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');
    }

    public function test_google_verification_submission_requires_an_oauth_session(): void
    {
        $this->post('/auth/google/verify', ['code' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');
    }

    public function test_google_verification_email_contains_the_code(): void
    {
        $html = (new GoogleVerificationCodeMail('123456'))->render();

        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('expires in 10 minutes', $html);
    }
}
