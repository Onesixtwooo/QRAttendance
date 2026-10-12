<?php

namespace Tests\Feature;

use App\Models\Booth;
use App\Models\EventSchedule;
use App\Models\Student;
use App\Models\User;
use App\Support\StudentQrCode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-07-10 08:15:00');
    }

    public function test_booth_scanner_page_marks_assigned_booth_seen(): void
    {
        $boothUser = User::create([
            'name' => 'Gate Booth',
            'email' => 'gate@example.com',
            'password' => 'password',
            'role' => 'booth',
        ]);
        $booth = Booth::create([
            'name' => 'Assigned Gate',
            'status' => 'Online',
            'user_id' => $boothUser->id,
            'admin_name' => 'Gate Booth',
            'admin_email' => 'gate@example.com',
        ]);

        $response = $this->actingAs($boothUser)->get('/qr-scanner');

        $response->assertOk();
        $response->assertSee('window.setTimeout', false);
        $response->assertSee('Ready for next scan.', false);
        $response->assertSee('Verification Code');
        $response->assertSee('id="latest-verification-code"', false);
        $response->assertSee('playScanBuzzer', false);
        $response->assertSee('}, 2000);', false);
        $this->assertNotNull($booth->fresh()->last_seen_at);
    }

    public function test_admin_can_scan_student_qr_for_selected_booth(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'admin_name' => 'Gate Admin',
            'admin_email' => 'gate@example.com',
        ]);
        $event = $this->activeEvent();
        $student = Student::create([
            'student_id' => 'STU-24017',
            'last_name' => 'Rivera',
            'first_name' => 'Alyssa',
            'course' => 'Information Technology',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $response = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => app(StudentQrCode::class)->generate($student)['payload'],
            'booth_id' => $booth->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('record.student_id', 'STU-24017');
        $response->assertJsonStructure(['record' => ['verification_code']]);
        $response->assertJsonPath('record.booth', 'Main Gate');
        $response->assertJsonPath('record.event', 'Morning Orientation');
        $response->assertJsonPath('record.scan_type', 'time_in');
        $this->assertDatabaseHas('attendance_records', [
            'event_schedule_id' => $event->id,
            'booth_id' => $booth->id,
            'student_id' => $student->id,
            'scan_type' => 'time_in',
        ]);
        $this->assertNotNull($booth->fresh()->last_seen_at);
    }

    public function test_second_scan_waits_for_admin_to_activate_verification_phase(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'admin_name' => 'Gate Admin',
            'admin_email' => 'gate@example.com',
        ]);
        $event = $this->activeEvent();
        $student = Student::create([
            'student_id' => 'STU-24019',
            'last_name' => 'Reyes',
            'first_name' => 'Nina',
            'course' => 'Information Technology',
            'block' => 'C',
            'year_level' => '3rd Year',
        ]);

        $first = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => 'STU-24019',
            'booth_id' => $booth->id,
        ]);
        $second = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => 'STU-24019',
            'booth_id' => $booth->id,
        ]);
        $event->update(['scan_phase' => 'verification']);
        $verification = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => 'STU-24019',
            'booth_id' => $booth->id,
        ]);
        $third = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => 'STU-24019',
            'booth_id' => $booth->id,
        ]);

        $first->assertJsonPath('record.scan_type', 'time_in');
        $second->assertStatus(409);
        $second->assertJsonPath('message', 'This student already has a time-in scan for the current event.');
        $verification->assertOk();
        $verification->assertJsonPath('record.scan_type', 'verification');
        $third->assertStatus(409);
        $third->assertJsonPath('message', 'This student already has a verification scan for the current event.');
        $this->assertDatabaseHas('attendance_records', [
            'event_schedule_id' => $event->id,
            'student_id' => $student->id,
            'scan_type' => 'time_in',
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'event_schedule_id' => $event->id,
            'student_id' => $student->id,
            'scan_type' => 'verification',
        ]);
        $this->assertDatabaseCount('attendance_records', 2);
    }

    public function test_booth_user_scan_uses_assigned_booth(): void
    {
        $boothUser = User::create([
            'name' => 'Gate Booth',
            'email' => 'gate@example.com',
            'password' => 'password',
            'role' => 'booth',
        ]);
        $assignedBooth = Booth::create([
            'name' => 'Assigned Gate',
            'status' => 'Online',
            'user_id' => $boothUser->id,
            'admin_name' => 'Gate Booth',
            'admin_email' => 'gate@example.com',
        ]);
        $otherBooth = Booth::create([
            'name' => 'Other Gate',
            'status' => 'Online',
            'admin_name' => 'Other Booth',
            'admin_email' => 'other@example.com',
        ]);
        $event = $this->activeEvent();
        $student = Student::create([
            'student_id' => 'STU-24018',
            'last_name' => 'Santos',
            'first_name' => 'Mika',
            'course' => 'Criminology',
            'block' => 'B',
            'year_level' => '2nd Year',
        ]);

        $response = $this->actingAs($boothUser)->postJson('/qr-scanner/scan', [
            'payload' => 'STU-24018',
            'booth_id' => $otherBooth->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('record.booth', 'Assigned Gate');
        $this->assertDatabaseHas('attendance_records', [
            'event_schedule_id' => $event->id,
            'booth_id' => $assignedBooth->id,
            'student_id' => $student->id,
            'scan_type' => 'time_in',
        ]);
        $this->assertNotNull($assignedBooth->fresh()->last_seen_at);
        $this->assertDatabaseMissing('attendance_records', [
            'booth_id' => $otherBooth->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_scanner_rejects_unknown_student_qr(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'admin_name' => 'Gate Admin',
            'admin_email' => 'gate@example.com',
        ]);
        $this->activeEvent();

        $response = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => 'UNKNOWN',
            'booth_id' => $booth->id,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_scanner_requires_an_event_in_progress(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'admin_name' => 'Gate Admin',
            'admin_email' => 'gate@example.com',
        ]);
        Student::create([
            'student_id' => 'STU-24020',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Ivy',
            'course' => 'Information Technology',
            'block' => 'D',
            'year_level' => '4th Year',
        ]);

        $response = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => 'STU-24020',
            'booth_id' => $booth->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'No event is currently in progress.');
    }

    public function test_scanner_rejects_scheduled_event_even_during_its_time_window(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'admin_name' => 'Gate Admin',
            'admin_email' => 'gate@example.com',
        ]);
        $this->activeEvent(['status' => 'Scheduled']);
        Student::create([
            'student_id' => 'STU-24024',
            'last_name' => 'Santos',
            'first_name' => 'Ana',
            'course' => 'Information Technology',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $response = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => 'STU-24024',
            'booth_id' => $booth->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'No event is currently in progress.');
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_scanner_uses_admin_in_progress_event_even_outside_clock_window(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'admin_name' => 'Gate Admin',
            'admin_email' => 'gate@example.com',
        ]);
        $event = EventSchedule::create([
            'title' => 'Manual In Progress',
            'event_date' => '2026-07-10',
            'start_time' => '22:26',
            'end_time' => '23:59',
            'venue' => 'Virtual',
            'booth' => 'Not assigned',
            'status' => 'In Progress',
            'scan_phase' => 'time_in',
            'time_in_open' => true,
        ]);
        $student = Student::create([
            'student_id' => 'STU-24023',
            'last_name' => 'Lopez',
            'first_name' => 'Mara',
            'course' => 'Information Technology',
            'block' => 'G',
            'year_level' => '1st Year',
        ]);

        $response = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => 'STU-24023',
            'booth_id' => $booth->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('record.event', 'Manual In Progress');
        $this->assertDatabaseHas('attendance_records', [
            'event_schedule_id' => $event->id,
            'student_id' => $student->id,
            'scan_type' => 'time_in',
        ]);
    }

    public function test_verification_phase_requires_prior_time_in(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'admin_name' => 'Gate Admin',
            'admin_email' => 'gate@example.com',
        ]);
        $this->activeEvent(['scan_phase' => 'verification']);
        Student::create([
            'student_id' => 'STU-24021',
            'last_name' => 'Garcia',
            'first_name' => 'Lena',
            'course' => 'Information Technology',
            'block' => 'E',
            'year_level' => '4th Year',
        ]);

        $response = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => 'STU-24021',
            'booth_id' => $booth->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This student needs a time-in scan before verification.');
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_time_in_scan_is_blocked_when_admin_closes_time_in(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $booth = Booth::create([
            'name' => 'Main Gate',
            'status' => 'Online',
            'admin_name' => 'Gate Admin',
            'admin_email' => 'gate@example.com',
        ]);
        $this->activeEvent(['time_in_open' => false]);
        Student::create([
            'student_id' => 'STU-24022',
            'last_name' => 'Perez',
            'first_name' => 'Tala',
            'course' => 'Information Technology',
            'block' => 'F',
            'year_level' => '1st Year',
        ]);

        $response = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => 'STU-24022',
            'booth_id' => $booth->id,
        ]);

        $response->assertStatus(423);
        $response->assertJsonPath('message', 'Time-in scanning is currently closed for this event.');
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_scanner_rejects_expired_dynamic_student_qr(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = Student::create([
            'student_id' => 'STU-EXPIRED',
            'last_name' => 'Expired',
            'first_name' => 'Code',
            'course' => 'BSIT',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);
        $payload = app(StudentQrCode::class)
            ->generate($student, CarbonImmutable::now()->subMinutes(2))['payload'];

        $response = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => $payload,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This student QR code is invalid or has expired. Ask the student to refresh it.');
    }

    public function test_scanner_rejects_legacy_static_student_qr(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/qr-scanner/scan', [
            'payload' => json_encode(['type' => 'student', 'student_id' => 'STU-OLD']),
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This student QR code is invalid or has expired. Ask the student to refresh it.');
    }

    private function activeEvent(array $overrides = []): EventSchedule
    {
        return EventSchedule::create(array_merge([
            'title' => 'Morning Orientation',
            'event_date' => '2026-07-10',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'venue' => 'Gym',
            'booth' => 'Not assigned',
            'status' => 'In Progress',
            'scan_phase' => 'time_in',
            'time_in_open' => true,
        ], $overrides));
    }
}
