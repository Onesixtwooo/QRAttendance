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
