<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Booth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BoothPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_booths_page_shows_saved_booths_and_add_form(): void
    {
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'admin_name' => 'Admin Cruz',
            'admin_email' => 'cruz@example.com',
        ]);

        AttendanceRecord::create([
            'booth_id' => $booth->id,
            'student_id' => null,
            'scanned_at' => now(),
        ]);

        AttendanceRecord::create([
            'booth_id' => $booth->id,
            'student_id' => null,
            'scanned_at' => now(),
        ]);

        $response = $this->get('/booths');

        $response->assertOk();
        $response->assertSee('Main Gate');
        $response->assertSee('2');
        $response->assertDontSee('999');
        $response->assertSee('Admin Cruz');
        $response->assertSee('cruz@example.com');
        $response->assertSee('href="#add-booth-modal"', false);
        $response->assertSee('Add Booth');
        $response->assertSee('User-Admin');
        $response->assertSee('Password');
        $response->assertSee('Save Booth');
        $response->assertDontSee('name="status"', false);
    }

    public function test_booth_can_be_saved_to_the_database(): void
    {
        $response = $this->post('/booths', [
            'name' => 'Library Entrance',
            'admin_name' => 'Admin Ford',
            'admin_email' => 'ford@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertRedirect('/booths');

        $this->assertDatabaseHas('booths', [
            'name' => 'Library Entrance',
            'status' => 'Online',
            'admin_name' => 'Admin Ford',
            'admin_email' => 'ford@example.com',
        ]);

        $user = User::where('email', 'ford@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Admin Ford', $user->name);
        $this->assertTrue(Hash::check('secret-pass', $user->password));

        $this->assertDatabaseHas('booths', [
            'name' => 'Library Entrance',
            'user_id' => $user->id,
        ]);
    }

    public function test_booth_can_be_loaded_for_editing(): void
    {
        $booth = Booth::create([
            'name' => 'Science Hall',
            'status' => 'Review',
            'admin_name' => 'Admin Lim',
            'admin_email' => null,
        ]);

        $response = $this->get('/booths?edit='.$booth->id);

        $response->assertOk();
        $response->assertSee('/booths?edit='.$booth->id.'#edit-booth-modal', false);
        $response->assertSee('id="edit-booth-modal"', false);
        $response->assertSee('Editing Booth');
        $response->assertSee('Update Booth');
        $response->assertSee('Science Hall');
        $response->assertSee('Admin Lim');
        $response->assertDontSee('name="status"', false);
    }

    public function test_booth_can_be_updated(): void
    {
        $user = User::create([
            'name' => 'Unassigned',
            'email' => 'old-gym@example.com',
            'password' => 'old-password',
        ]);

        $booth = Booth::create([
            'name' => 'Gym Lobby',
            'status' => 'Offline',
            'user_id' => $user->id,
            'admin_name' => 'Unassigned',
            'admin_email' => 'old-gym@example.com',
        ]);

        $response = $this->patch('/booths/'.$booth->id, [
            'name' => 'Gym Lobby',
            'admin_name' => 'Admin Reyes',
            'admin_email' => 'reyes@example.com',
            'password' => 'new-secret',
            'password_confirmation' => 'new-secret',
        ]);

        $response->assertRedirect('/booths');

        $this->assertDatabaseHas('booths', [
            'id' => $booth->id,
            'status' => 'Offline',
            'admin_name' => 'Admin Reyes',
            'admin_email' => 'reyes@example.com',
        ]);

        $user = User::where('email', 'reyes@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Admin Reyes', $user->name);
        $this->assertTrue(Hash::check('new-secret', $user->password));
    }

    public function test_booth_scans_only_count_todays_attendance_records(): void
    {
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'admin_name' => 'Admin Cruz',
            'admin_email' => null,
        ]);

        AttendanceRecord::create([
            'booth_id' => $booth->id,
            'student_id' => null,
            'scanned_at' => now(),
        ]);

        AttendanceRecord::create([
            'booth_id' => $booth->id,
            'student_id' => null,
            'scanned_at' => now()->subDay(),
        ]);

        $response = $this->get('/booths');

        $response->assertOk();
        $response->assertSee('Main Gate');
        $response->assertSee('1');
    }

    public function test_booth_heartbeat_updates_last_seen_for_logged_in_booth_user(): void
    {
        $user = User::create([
            'name' => 'Gate Booth',
            'email' => 'gate@example.com',
            'password' => 'password',
            'role' => 'booth',
        ]);

        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'user_id' => $user->id,
            'admin_name' => 'Gate Booth',
            'admin_email' => 'gate@example.com',
        ]);

        $response = $this->actingAs($user)->post('/booths/heartbeat');

        $response->assertNoContent();
        $this->assertNotNull($booth->fresh()->last_seen_at);
    }

    public function test_booth_status_is_based_on_recent_heartbeat(): void
    {
        Booth::create([
            'name' => 'Recent Booth',
            'status' => 'Online',
            'last_seen_at' => now()->subMinute(),
            'admin_name' => 'Admin Cruz',
            'admin_email' => 'cruz@example.com',
        ]);

        Booth::create([
            'name' => 'Silent Booth',
            'status' => 'Online',
            'last_seen_at' => now()->subMinutes(3),
            'admin_name' => 'Admin Ford',
            'admin_email' => 'ford@example.com',
        ]);

        Booth::create([
            'name' => 'Review Booth',
            'status' => 'Review',
            'last_seen_at' => now(),
            'admin_name' => 'Admin Lim',
            'admin_email' => 'lim@example.com',
        ]);

        $response = $this->get('/booths');

        $response->assertOk();
        $response->assertSeeInOrder(['Recent Booth', 'Online']);
        $response->assertSeeInOrder(['Review Booth', 'Review']);
        $response->assertSeeInOrder(['Silent Booth', 'Offline']);
    }

    public function test_booth_status_endpoint_returns_current_statuses(): void
    {
        $recentBooth = Booth::create([
            'name' => 'Recent Booth',
            'status' => 'Online',
            'last_seen_at' => now(),
            'admin_name' => 'Admin Cruz',
            'admin_email' => 'cruz@example.com',
        ]);
        $silentBooth = Booth::create([
            'name' => 'Silent Booth',
            'status' => 'Online',
            'last_seen_at' => now()->subMinutes(3),
            'admin_name' => 'Admin Ford',
            'admin_email' => 'ford@example.com',
        ]);

        $response = $this->get('/booths/statuses');

        $response->assertOk();
        $response->assertJsonPath('booths.0.id', $recentBooth->id);
        $response->assertJsonPath('booths.0.status', 'Online');
        $response->assertJsonPath('booths.1.id', $silentBooth->id);
        $response->assertJsonPath('booths.1.status', 'Offline');
    }

    public function test_booths_page_has_realtime_status_update_hooks(): void
    {
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'last_seen_at' => now(),
            'admin_name' => 'Admin Cruz',
            'admin_email' => 'cruz@example.com',
        ]);

        $response = $this->get('/booths');

        $response->assertOk();
        $response->assertSee('data-booth-card="'.$booth->id.'"', false);
        $response->assertSee('data-booth-status', false);
        $response->assertSee(route('booths.statuses'), false);
    }

    public function test_booth_can_be_deleted(): void
    {
        $booth = Booth::create([
            'name' => 'Side Gate',
            'status' => 'Offline',
            'admin_name' => 'Admin Neri',
            'admin_email' => 'neri@example.com',
        ]);

        $response = $this->delete('/booths/'.$booth->id);

        $response->assertRedirect('/booths');

        $this->assertDatabaseMissing('booths', [
            'id' => $booth->id,
        ]);
    }
}
