<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_student_login_and_student_only_sees_own_qr(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = Student::create([
            'student_id' => 'STU-100',
            'first_name' => 'Maya',
            'last_name' => 'Santos',
            'course' => 'BSIT',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $this->actingAs($admin)->post(route('students.account', $student), [
            'email' => 'maya@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertRedirect(route('students'));

        $studentUser = $student->fresh()->user;
        $this->assertSame('student', $studentUser->role);

        $this->actingAs($studentUser)->get(route('student.portal'))
            ->assertOk()
            ->assertSee('STU-100')
            ->assertSee('My attendance QR code');

        $this->get(route('student.qr'))
            ->assertOk()
            ->assertJsonPath('verification_code', app(\App\Support\StudentQrCode::class)->generate($student)['verification_code']);

        $this->get(route('students'))->assertRedirect(route('student.portal'));
    }

    public function test_admin_cannot_open_student_portal(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('student.portal'))
            ->assertForbidden();
    }

    public function test_student_can_edit_login_account(): void
    {
        $studentUser = User::factory()->create(['role' => 'student', 'email' => 'old@example.com']);
        $student = Student::create([
            'student_id' => 'STU-200', 'first_name' => 'Ava', 'last_name' => 'Reyes',
            'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year', 'user_id' => $studentUser->id,
        ]);

        $this->actingAs($studentUser)->patch(route('student.account.update'), [
            'email' => 'new@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('student.portal'));

        $this->assertDatabaseHas('users', ['id' => $studentUser->id, 'email' => 'new@example.com']);
        $this->assertTrue(password_verify('new-password', $studentUser->fresh()->password));
    }
}
