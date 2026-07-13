<?php

namespace Tests\Feature;

use App\Models\Booth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_nav_tab_lists_admin_and_booth_accounts(): void
    {
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-pass',
            'role' => 'admin',
        ]);

        $boothUser = User::create([
            'name' => 'Gate Booth',
            'email' => 'gate@example.com',
            'password' => 'secret-pass',
            'role' => 'booth',
        ]);

        Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'user_id' => $boothUser->id,
            'admin_name' => 'Gate Booth',
            'admin_email' => 'gate@example.com',
        ]);

        $response = $this->get('/users');

        $response->assertOk();
        $response->assertSee('href="'.route('users').'"', false);
        $response->assertSee('Users');
        $response->assertSee('Admin Accounts');
        $response->assertSee('Booth Accounts');
        $response->assertSee('System Admin');
        $response->assertSee('admin@example.com');
        $response->assertSee('Admin console');
        $response->assertSee('Gate Booth');
        $response->assertSee('gate@example.com');
        $response->assertSee('Main Gate');
        $response->assertSee('Online');
        $response->assertSee('Edit');
        $response->assertSee('Delete');
        $response->assertSee('href="#add-admin-modal"', false);
        $response->assertSee('Create Admin');
        $response->assertSee('Save Admin');
    }

    public function test_admin_user_can_be_created_from_users_page(): void
    {
        $response = $this->post('/users', [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertRedirect('/users');

        $user = User::where('email', 'new-admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('New Admin', $user->name);
        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
    }

    public function test_user_can_be_loaded_for_editing(): void
    {
        $user = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-pass',
            'role' => 'admin',
        ]);

        $response = $this->get('/users?edit='.$user->id);

        $response->assertOk();
        $response->assertSee('/users?edit='.$user->id.'#user-form', false);
        $response->assertSee('Editing User');
        $response->assertSee('Update User');
        $response->assertSee('System Admin');
        $response->assertSee('admin@example.com');
    }

    public function test_users_can_be_filtered_by_admin_or_booth_role(): void
    {
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-pass',
            'role' => 'admin',
        ]);

        $boothUser = User::create([
            'name' => 'Gate Booth',
            'email' => 'gate@example.com',
            'password' => 'secret-pass',
            'role' => 'booth',
        ]);

        Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'user_id' => $boothUser->id,
            'admin_name' => 'Gate Booth',
            'admin_email' => 'gate@example.com',
        ]);

        $adminResponse = $this->get('/users?role=admin');

        $adminResponse->assertOk();
        $adminResponse->assertSee('Admins only');
        $adminResponse->assertSee('System Admin');
        $adminResponse->assertDontSee('Gate Booth');
        $adminResponse->assertSee('Reset');

        $boothResponse = $this->get('/users?role=booth');

        $boothResponse->assertOk();
        $boothResponse->assertSee('Booths only');
        $boothResponse->assertSee('Gate Booth');
        $boothResponse->assertSee('Main Gate');
        $boothResponse->assertDontSee('System Admin');
        $boothResponse->assertSee('Reset');
    }

    public function test_user_can_be_updated(): void
    {
        $user = User::create([
            'name' => 'Old Admin',
            'email' => 'old@example.com',
            'password' => 'old-secret',
            'role' => 'admin',
        ]);

        $response = $this->patch('/users/'.$user->id, [
            'name' => 'Updated Admin',
            'email' => 'updated@example.com',
            'role' => 'admin',
            'password' => 'new-secret',
            'password_confirmation' => 'new-secret',
        ]);

        $response->assertRedirect('/users');

        $user->refresh();

        $this->assertSame('Updated Admin', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check('new-secret', $user->password));
    }

    public function test_deleting_booth_user_keeps_booth_and_detaches_login(): void
    {
        $boothUser = User::create([
            'name' => 'Gate Booth',
            'email' => 'gate@example.com',
            'password' => 'secret-pass',
            'role' => 'booth',
        ]);

        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'user_id' => $boothUser->id,
            'admin_name' => 'Gate Booth',
            'admin_email' => 'gate@example.com',
        ]);

        $response = $this->delete('/users/'.$boothUser->id);

        $response->assertRedirect('/users');

        $this->assertDatabaseMissing('users', [
            'id' => $boothUser->id,
        ]);

        $this->assertDatabaseHas('booths', [
            'id' => $booth->id,
            'name' => 'Main Gate',
            'user_id' => null,
        ]);
    }
}
