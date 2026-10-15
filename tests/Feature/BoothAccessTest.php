<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoothAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_booth_user_only_sees_scanner_navigation_and_logout(): void
    {
        $boothUser = User::create([
            'name' => 'Gate Booth',
            'email' => 'gate@example.com',
            'password' => 'password',
            'role' => 'booth',
        ]);

        $response = $this->actingAs($boothUser)->get('/qr-scanner');

        $response->assertOk();
        $response->assertSee('QR Scanner');
        $response->assertSee('Logout');
        $response->assertDontSee('Dashboard');
        $response->assertDontSee('Users');
        $response->assertDontSee('Students');
        $response->assertDontSee('Attendance Records');
        $response->assertDontSee('Event Schedule');
        $response->assertDontSee('Scanner Operators');
        $response->assertDontSee('Export');
        $response->assertDontSee('Add Record');
    }

    public function test_booth_user_is_redirected_from_admin_pages_to_scanner(): void
    {
        $boothUser = User::create([
            'name' => 'Gate Booth',
            'email' => 'gate@example.com',
            'password' => 'password',
            'role' => 'booth',
        ]);

        $response = $this->actingAs($boothUser)->get('/dashboard');

        $response->assertRedirect('/qr-scanner');
    }
}
