<?php

namespace Tests\Feature;

use App\Models\Booth;
use App\Models\Student;
use App\Models\User;
use App\Mail\AdminAccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class UserPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_users_nav_tab_lists_only_admin_accounts(): void
    {
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-pass',
            'role' => 'admin',
        ]);

        $studentUser = User::create([
            'name' => 'Maya Student',
            'email' => 'maya@example.com',
            'password' => 'secret-pass',
            'role' => 'student',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'STU-1001',
            'last_name' => 'Student',
            'first_name' => 'Maya',
            'course' => 'BSIT',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $response = $this->get('/users');

        $response->assertOk();
        $response->assertSee('href="'.route('users').'"', false);
        $response->assertSee('Users');
        $response->assertSee('Admin Accounts');
        $response->assertSee('System Admin');
        $response->assertSee('admin@example.com');
        $response->assertDontSee('Student Accounts');
        $response->assertDontSee('Maya Student');
        $response->assertDontSee('maya@example.com');
        $response->assertDontSee('STU-1001');
        $response->assertSee('Active');
        $response->assertSee('Edit');
        $response->assertSee('Resend credentials');
        $response->assertDontSee('action="'.route('users.credentials.resend', $studentUser->id).'"', false);
        $response->assertSee('Delete');
        $response->assertSee('href="#add-admin-modal"', false);
        $response->assertSee('Create Admin');
        $response->assertSee('Save Admin');
    }

    public function test_admin_user_can_be_created_from_users_page(): void
    {
        Mail::fake();

        $response = $this->post('/users', [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
        ]);

        $response->assertRedirect('/users');

        $user = User::where('email', 'new-admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('New Admin', $user->name);
        $this->assertSame('admin', $user->role);
        $this->assertNotSame('', $user->password);
        Mail::assertSent(AdminAccountCredentials::class, function (AdminAccountCredentials $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->user->is($user)
                && Hash::check($mail->temporaryPassword, $user->password);
        });
    }

    public function test_admin_credentials_can_be_resent_with_a_new_password(): void
    {
        config(['mail.default' => 'smtp']);
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $previousPassword = $admin->password;
        Mail::fake();

        $this->post(route('users.credentials.resend', $admin))
            ->assertRedirect(route('users'))
            ->assertSessionHas('user_status', 'New admin credentials submitted to the configured mail transport for admin@example.com.');

        $this->assertNotSame($previousPassword, $admin->fresh()->password);
        Mail::assertSent(AdminAccountCredentials::class, function (AdminAccountCredentials $mail) use ($admin): bool {
            return $mail->hasTo('admin@example.com')
                && $mail->user->is($admin)
                && Hash::check($mail->temporaryPassword, $admin->fresh()->password);
        });
    }

    public function test_failed_admin_resend_keeps_the_existing_password(): void
    {
        config(['mail.default' => 'smtp']);
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $previousPassword = $admin->password;
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));

        $this->post(route('users.credentials.resend', $admin))
            ->assertSessionHas('user_status', 'Admin credentials could not be sent to admin@example.com. The existing password still works. Check the mail settings and try again.');

        $this->assertSame($previousPassword, $admin->fresh()->password);
    }

    public function test_admin_resend_does_not_reset_a_password_when_mail_uses_the_log(): void
    {
        config(['mail.default' => 'log']);
        $admin = User::factory()->create(['role' => 'admin']);
        $previousPassword = $admin->password;
        Mail::fake();

        $this->post(route('users.credentials.resend', $admin))
            ->assertSessionHas('user_status', 'Mail is configured for logging. Configure an email transport before resending admin credentials.');

        $this->assertSame($previousPassword, $admin->fresh()->password);
        Mail::assertNothingSent();
    }

    public function test_admin_resend_rejects_a_student_account(): void
    {
        config(['mail.default' => 'smtp']);
        $student = User::factory()->create(['role' => 'student']);
        $previousPassword = $student->password;
        Mail::fake();

        $this->post(route('users.credentials.resend', $student))->assertNotFound();

        $this->assertSame($previousPassword, $student->fresh()->password);
        Mail::assertNothingSent();
    }

    public function test_student_cannot_resend_admin_credentials(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $previousPassword = $admin->password;
        Mail::fake();

        $this->actingAs($student)->post(route('users.credentials.resend', $admin))
            ->assertRedirect(route('student.portal'));

        $this->assertSame($previousPassword, $admin->fresh()->password);
        Mail::assertNothingSent();
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

    public function test_role_query_does_not_expose_student_accounts(): void
    {
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-pass',
            'role' => 'admin',
        ]);

        $studentUser = User::create([
            'name' => 'Maya Student',
            'email' => 'maya@example.com',
            'password' => 'secret-pass',
            'role' => 'student',
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'STU-1001',
            'last_name' => 'Student',
            'first_name' => 'Maya',
            'course' => 'BSIT',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $adminResponse = $this->get('/users?role=student');

        $adminResponse->assertOk();
        $adminResponse->assertSee('System Admin');
        $adminResponse->assertDontSee('Maya Student');
        $adminResponse->assertDontSee('Students only');
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
            'is_active' => '1',
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
