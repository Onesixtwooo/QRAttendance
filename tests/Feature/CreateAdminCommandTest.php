<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_from_interactive_input(): void
    {
        $this->artisan('app:create-admin', [
            '--name' => 'First Administrator',
            '--email' => 'first.admin@example.com',
        ])
            ->expectsQuestion('Password (minimum 8 characters)', 'strong-password')
            ->expectsQuestion('Confirm password', 'strong-password')
            ->expectsOutput('Administrator account created: first.admin@example.com')
            ->assertExitCode(0);

        $admin = User::where('email', 'first.admin@example.com')->firstOrFail();

        $this->assertSame('First Administrator', $admin->name);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('strong-password', $admin->password));
    }

    public function test_it_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->artisan('app:create-admin', [
            '--name' => 'Another Administrator',
            '--email' => 'existing@example.com',
        ])
            ->assertExitCode(1);

        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
    }

    public function test_it_rejects_mismatched_passwords(): void
    {
        $this->artisan('app:create-admin', [
            '--name' => 'First Administrator',
            '--email' => 'first.admin@example.com',
        ])
            ->expectsQuestion('Password (minimum 8 characters)', 'strong-password')
            ->expectsQuestion('Confirm password', 'different-password')
            ->expectsOutput('The password confirmation does not match.')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'first.admin@example.com']);
    }
}
