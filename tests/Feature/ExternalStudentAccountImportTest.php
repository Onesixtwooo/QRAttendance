<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExternalStudentAccountImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_external_student_account_is_imported_for_portal_linking(): void
    {
        $path = $this->externalDump();

        try {
            $this->artisan('students:import-accounts', ['path' => $path])->assertSuccessful();

            $user = User::where('email', 'maya@example.com')->firstOrFail();
            $student = Student::where('student_id', '11-02418')->firstOrFail();

            $this->assertSame('student', $user->role);
            $this->assertTrue(Hash::check('same-password', $user->password));
            $this->assertSame($user->id, $student->user_id);
            $this->assertSame('BS Information Technology', $student->course);
            $this->assertSame('C', $student->block);
            $this->assertSame('3rd Year', $student->year_level);

            $this->post('/login', [
                'email' => 'maya@example.com',
                'password' => 'same-password',
            ])->assertRedirect(route('student.portal'));
            $this->assertAuthenticatedAs($user);
        } finally {
            @unlink($path);
        }
    }

    public function test_external_account_import_is_idempotent(): void
    {
        $path = $this->externalDump();

        try {
            $this->artisan('students:import-accounts', ['path' => $path])->assertSuccessful();
            $this->artisan('students:import-accounts', ['path' => $path])->assertSuccessful();

            $this->assertSame(1, User::where('email', 'maya@example.com')->count());
            $this->assertSame(1, Student::where('student_id', '11-02418')->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_a_previously_imported_account_is_disabled_when_the_external_account_becomes_inactive(): void
    {
        $path = $this->externalDump();

        try {
            $this->artisan('students:import-accounts', ['path' => $path])->assertSuccessful();
            $sql = str_replace(
                "'maya@example.com', 1, '2026-08-01 06:01:26'",
                "'maya@example.com', 0, '2026-08-01 06:01:26'",
                file_get_contents($path)
            );
            file_put_contents($path, $sql);

            $this->artisan('students:import-accounts', ['path' => $path])->assertSuccessful();

            $this->assertFalse(User::where('email', 'maya@example.com')->firstOrFail()->is_active);
        } finally {
            @unlink($path);
        }
    }

    private function externalDump(): string
    {
        $hash = Hash::make('same-password');
        $sql = <<<SQL
INSERT INTO `courses` (`id`, `code`, `name`, `is_active`) VALUES
(2, 'BSIT', 'BS Information Technology', 1);
INSERT INTO `sections` (`id`, `course_id`, `name`, `year_level`, `is_active`) VALUES
(15, 2, 'C', 3, 1);
INSERT INTO `students` (`id`, `user_id`, `student_number`, `course_id`, `section_id`, `year_level`) VALUES
(1, 4, '11-02418', 2, 15, 3);
INSERT INTO `users` (`id`, `role`, `name`, `email`, `is_active`, `registration_verified_at`, `registration_declined_at`, `password`) VALUES
(4, 'student', 'Maya Dela Cruz', 'maya@example.com', 1, '2026-08-01 06:01:26', NULL, '{$hash}');
SQL;
        $path = tempnam(sys_get_temp_dir(), 'eas-import-');
        file_put_contents($path, $sql);

        return $path;
    }
}
