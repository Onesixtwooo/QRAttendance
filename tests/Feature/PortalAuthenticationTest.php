<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_displays_local_credentials_form(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in')
            ->assertSee('name="email"', false)->assertSee('name="password"', false)
            ->assertSee(route('register'));
    }

    public function test_active_user_can_login_with_a_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'secret-password', 'role' => 'admin', 'is_active' => true]);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_disabled_user_cannot_login(): void
    {
        User::factory()->create(['email' => 'disabled@example.com', 'password' => 'secret-password', 'is_active' => false]);

        $this->from('/login')->post('/login', ['email' => 'disabled@example.com', 'password' => 'secret-password'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['email' => 'limited@example.com', 'password' => 'secret-password', 'role' => 'admin']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email');
        }

        $this->postJson('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertStatus(429)
            ->assertJsonValidationErrors('email');
        $this->assertGuest();

        $this->travel(61)->seconds();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_clears_failed_attempts(): void
    {
        $user = User::factory()->create(['email' => 'reset-limit@example.com', 'password' => 'secret-password', 'role' => 'admin']);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertUnprocessable();
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('dashboard'));
        $this->post('/logout')->assertRedirect(route('login'));

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertUnprocessable();
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_student_can_register_and_is_sent_to_the_student_portal(): void
    {
        $this->post('/register', [
            'name' => 'Maya Dela Cruz', 'email' => 'maya@example.com',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('student.portal'));

        $user = User::where('email', 'maya@example.com')->firstOrFail();
        $this->assertSame('student', $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_ends_the_local_session_and_returns_to_login(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
