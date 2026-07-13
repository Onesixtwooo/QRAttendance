<?php

namespace Tests\Feature;

use App\Models\Booth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_does_not_ask_for_user_type(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Attendance Access');
        $response->assertSee('Sign in');
        $response->assertSee('Use your SAMSqr account to continue.');
        $response->assertDontSee('User Type');
        $response->assertDontSee('name="role"', false);
    }

    public function test_root_page_uses_login_as_the_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_admin_can_login_and_goes_to_dashboard(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-pass',
            'role' => 'admin',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_booth_user_can_login_and_goes_to_scanner(): void
    {
        $boothUser = User::create([
            'name' => 'Gate Booth',
            'email' => 'booth@example.com',
            'password' => 'secret-pass',
            'role' => 'booth',
        ]);
        $booth = Booth::create([
            'name' => 'Gate Booth',
            'status' => 'Online',
            'user_id' => $boothUser->id,
            'admin_name' => 'Gate Booth',
            'admin_email' => 'booth@example.com',
        ]);

        $response = $this->post('/login', [
            'email' => 'booth@example.com',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect('/qr-scanner');
        $this->assertAuthenticatedAs($boothUser);
        $this->assertNotNull($booth->fresh()->last_seen_at);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::create([
            'name' => 'Gate Booth',
            'email' => 'booth@example.com',
            'password' => 'secret-pass',
            'role' => 'booth',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'booth@example.com',
            'password' => 'wrong-pass',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
