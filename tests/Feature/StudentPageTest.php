<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectBoothUsersToScanner;
use App\Models\Student;
use App\Models\User;
use App\Mail\StudentAccountCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class StudentPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_students_page_shows_profile_columns_without_attendance_fields(): void
    {
        $response = $this->get('/students');

        $response->assertOk();
        $response->assertSee('Student ID');
        $response->assertSee('Full Name');
        $response->assertSee('Course');
        $response->assertSee('Block');
        $response->assertSee('Year Level');
        $response->assertSee('Select year level');
        $response->assertSee('5th Year');
        $response->assertSee('href="#add-student-modal"', false);
        $response->assertSee('href="#import-students-modal"', false);
        $response->assertDontSee('>Export<', false);
        $response->assertDontSee('>Add Record<', false);
        $response->assertSee('Import Students');
        $response->assertSee('Add Student');
        $response->assertSee('Save Student');
        $response->assertSee('spreadsheet-fill-handle', false);
        $response->assertSee('Drag to fill course');
        $response->assertSee('Drag to fill block');
        $response->assertSee('Drag to fill year level');
        $response->assertSee('<option value="G"', false);
        $response->assertDontSee('Print QR');
        $response->assertDontSee('Time In');
    }

    public function test_students_page_loads_the_first_ten_students_by_default(): void
    {
        foreach (range(1, 12) as $number) {
            Student::create([
                'student_id' => 'STU-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'last_name' => 'Student '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'first_name' => 'Test',
                'course' => 'Information Technology',
                'block' => 'A',
                'year_level' => '1st Year',
            ]);
        }

        $response = $this->get('/students');

        $response->assertOk();
        $response->assertSee('STU-01');
        $response->assertSee('STU-10');
        $response->assertDontSee('STU-11');
        $response->assertDontSee('STU-12');
        $response->assertSee('Showing the first');
        $response->assertSee('10</span> student record(s).', false);
    }

    public function test_student_can_be_saved_to_the_database(): void
    {
        Mail::fake();
        $response = $this->post('/students', [
            'student_id' => 'STU-25001',
            'email' => 'maya@example.com',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Maya',
            'course' => 'BS Information Systems',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $response->assertRedirect('/students');

        $this->assertDatabaseHas('students', [
            'student_id' => 'STU-25001',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Maya',
            'course' => 'BS Information Systems',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);
    }

    public function test_student_can_be_saved_without_a_block(): void
    {
        Mail::fake();
        $response = $this->post('/students', [
            'student_id' => 'STU-25002',
            'email' => 'mika@example.com',
            'last_name' => 'Santos',
            'first_name' => 'Mika',
            'course' => 'BS Information Systems',
            'block' => '',
            'year_level' => '2nd Year',
        ]);

        $response->assertRedirect('/students');
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students', [
            'student_id' => 'STU-25002',
            'block' => null,
        ]);
    }

    public function test_student_without_email_is_not_saved(): void
    {
        Mail::fake();

        $this->post('/students', [
            'student_id' => 'STU-NO-EMAIL',
            'last_name' => 'Davis',
            'first_name' => 'John',
            'course' => 'BSIT',
            'year_level' => '1st Year',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('students', ['student_id' => 'STU-NO-EMAIL']);
        Mail::assertNothingSent();
    }

    public function test_single_creation_reports_failed_credential_delivery(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));

        $response = $this->post('/students', [
            'student_id' => 'STU-FAILED-MAIL', 'email' => 'failed@example.com',
            'last_name' => 'Davis', 'first_name' => 'John', 'course' => 'BSIT',
            'year_level' => '1st Year',
        ]);

        $student = Student::where('student_id', 'STU-FAILED-MAIL')->firstOrFail();
        $response->assertSessionHas('student_status', 'Student saved, but credentials could not be sent. Check the mail settings and resend them.');
        $response->assertSessionHas('student_mail_failures', [['id' => $student->id, 'email' => 'failed@example.com']]);
        $this->get('/students')->assertSee('Resend credentials');
    }

    public function test_multiple_students_can_be_saved_from_the_spreadsheet(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();
        $response = $this->post('/students/bulk', [
            'students' => [
                ['student_id' => 'STU-GRID-1', 'email' => 'john@example.com', 'last_name' => 'Davis', 'first_name' => 'John', 'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year'],
                ['student_id' => 'STU-GRID-2', 'email' => 'mika@example.com', 'last_name' => 'Santos', 'first_name' => 'Mika', 'course' => 'BSCS', 'block' => 'B', 'year_level' => '2nd Year'],
            ],
        ]);

        $response->assertRedirect('/students');
        $response->assertSessionHas('student_status', 'Added 2 student record(s). 2 email(s) written to the mail log (not emailed).');
        $this->assertDatabaseHas('students', ['student_id' => 'STU-GRID-1']);
        $this->assertDatabaseHas('students', ['student_id' => 'STU-GRID-2']);
    }

    public function test_bulk_creation_reports_successful_mail_handoff(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $response = $this->post('/students/bulk', [
            'students' => [[
                'student_id' => 'STU-MAIL-1', 'email' => 'student@example.com',
                'last_name' => 'Davis', 'first_name' => 'John', 'course' => 'BSIT',
                'block' => 'A', 'year_level' => '1st Year',
            ]],
        ]);

        $response->assertSessionHas('student_status', 'Added 1 student record(s). 1 email(s) submitted to the configured mail transport.');
        $response->assertSessionHas('student_mail_failures', []);
        Mail::assertSent(StudentAccountCredentials::class, 1);
    }

    public function test_bulk_creation_reports_failed_mail(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));

        $response = $this->post('/students/bulk', [
            'students' => [[
                'student_id' => 'STU-MAIL-2', 'email' => 'failed@example.com',
                'last_name' => 'Davis', 'first_name' => 'Jane', 'course' => 'BSIT',
                'block' => 'A', 'year_level' => '1st Year',
            ]],
        ]);

        $student = Student::where('student_id', 'STU-MAIL-2')->firstOrFail();
        $response->assertSessionHas('student_status', 'Added 1 student record(s). 0 email(s) submitted to the configured mail transport; 1 failed.');
        $response->assertSessionHas('student_mail_failures', [['id' => $student->id, 'email' => 'failed@example.com']]);
        $this->assertNotNull($student->user);
        $this->get('/students')
            ->assertSee('Credentials could not be sent to:')
            ->assertSee('failed@example.com')
            ->assertSee(route('students.account.resend', $student));
    }

    public function test_admin_can_resend_student_credentials_with_a_new_password(): void
    {
        config(['mail.default' => 'smtp']);
        $student = Student::create([
            'student_id' => 'STU-RESEND', 'last_name' => 'Davis', 'first_name' => 'Jane',
            'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year',
        ]);
        $user = User::factory()->create(['email' => 'failed@example.com', 'role' => 'student']);
        $student->user()->associate($user);
        $student->save();

        $oldPassword = $user->password;
        Mail::fake();
        $this->post(route('students.account.resend', $student))->assertRedirect(route('students'));
        Mail::assertSent(StudentAccountCredentials::class, function (StudentAccountCredentials $mail) use ($student, $oldPassword): bool {
            return $mail->hasTo('failed@example.com')
                && $mail->student->is($student)
                && Hash::check($mail->temporaryPassword, $student->user->fresh()->password)
                && $oldPassword !== $student->user->fresh()->password;
        });
    }

    public function test_failed_resend_preserves_the_existing_password(): void
    {
        config(['mail.default' => 'smtp']);
        $student = Student::create([
            'student_id' => 'STU-FAILED-RESEND', 'last_name' => 'Davis', 'first_name' => 'Jane',
            'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year',
        ]);
        $user = User::factory()->create(['email' => 'existing@example.com', 'role' => 'student']);
        $student->user()->associate($user);
        $student->save();
        $previousPassword = $user->password;
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));

        $this->post(route('students.account.resend', $student))
            ->assertSessionHas('student_status', 'Credentials could not be sent to existing@example.com. The existing password still works. Check the mail settings and try again.');

        $this->assertSame($previousPassword, $user->fresh()->password);
    }

    public function test_student_can_be_saved_from_the_spreadsheet_without_a_block(): void
    {
        Mail::fake();
        $response = $this->post('/students/bulk', [
            'students' => [
                ['student_id' => 'STU-NO-BLOCK', 'email' => 'john@example.com', 'last_name' => 'Davis', 'first_name' => 'John', 'course' => 'BSIT', 'block' => '', 'year_level' => '1st Year'],
            ],
        ]);

        $response->assertRedirect('/students');
        $this->assertDatabaseHas('students', [
            'student_id' => 'STU-NO-BLOCK',
            'block' => null,
        ]);
    }

    public function test_spreadsheet_rejects_the_entire_batch_when_a_row_is_invalid(): void
    {
        Student::create(['student_id' => 'STU-EXISTS', 'last_name' => 'Existing', 'first_name' => 'Student', 'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year']);

        $response = $this->from('/students#add-student-modal')->post('/students/bulk', [
            'students' => [
                ['student_id' => 'STU-NEW', 'email' => 'new@example.com', 'last_name' => 'New', 'first_name' => 'Student', 'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year'],
                ['student_id' => 'STU-EXISTS', 'email' => 'existing@example.com', 'last_name' => 'Duplicate', 'first_name' => 'Student', 'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year'],
            ],
        ]);

        $response->assertRedirect('/students#add-student-modal');
        $response->assertSessionHasErrors('students.1.student_id', null, 'bulkStudents');
        $this->assertDatabaseMissing('students', ['student_id' => 'STU-NEW']);
    }

    public function test_spreadsheet_rejects_the_entire_batch_when_a_student_has_no_email(): void
    {
        Mail::fake();

        $this->post('/students/bulk', [
            'students' => [
                ['student_id' => 'STU-WITH-EMAIL', 'email' => 'student@example.com', 'last_name' => 'Davis', 'first_name' => 'John', 'course' => 'BSIT', 'year_level' => '1st Year'],
                ['student_id' => 'STU-NO-EMAIL', 'last_name' => 'Santos', 'first_name' => 'Mika', 'course' => 'BSCS', 'year_level' => '2nd Year'],
            ],
        ])->assertSessionHasErrors('students.1.email', null, 'bulkStudents');

        $this->assertDatabaseMissing('students', ['student_id' => 'STU-WITH-EMAIL']);
        $this->assertDatabaseMissing('students', ['student_id' => 'STU-NO-EMAIL']);
        Mail::assertNothingSent();
    }

    public function test_student_can_be_loaded_for_editing(): void
    {
        $student = Student::create([
            'student_id' => 'STU-25002',
            'last_name' => 'Reyes',
            'first_name' => 'Paolo',
            'course' => 'BS Computer Science',
            'block' => 'B',
            'year_level' => '2nd Year',
        ]);

        $response = $this->get('/students?edit='.$student->id);

        $response->assertOk();
        $response->assertSee('id="student-form"', false);
        $response->assertSee('action="'.url('/students/'.$student->id).'"', false);
        $response->assertSee('Editing Student');
        $response->assertSee('Update Student');
        $response->assertSee('Reyes');
    }

    public function test_student_can_be_updated(): void
    {
        $student = Student::create([
            'student_id' => 'STU-25003',
            'last_name' => 'Garcia',
            'first_name' => 'Lena',
            'course' => 'BS Data Analytics',
            'block' => 'A',
            'year_level' => '3rd Year',
        ]);

        $response = $this->patch('/students/'.$student->id, [
            'student_id' => 'STU-25003',
            'last_name' => 'Garcia',
            'first_name' => 'Elena',
            'course' => 'BS Information Technology',
            'block' => 'C',
            'year_level' => '4th Year',
        ]);

        $response->assertRedirect('/students');

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'Elena',
            'course' => 'BS Information Technology',
            'block' => 'C',
            'year_level' => '4th Year',
        ]);
    }

    public function test_student_can_be_deleted(): void
    {
        $student = Student::create([
            'student_id' => 'STU-25004',
            'last_name' => 'Torres',
            'first_name' => 'Ivan',
            'course' => 'BS Information Systems',
            'block' => 'D',
            'year_level' => '1st Year',
        ]);

        $response = $this->delete('/students/'.$student->id);

        $response->assertRedirect('/students');

        $this->assertDatabaseMissing('students', [
            'id' => $student->id,
        ]);
    }

    public function test_selected_students_can_be_deleted_together(): void
    {
        $students = collect(['One', 'Two', 'Three'])->map(fn (string $name, int $index) => Student::create([
            'student_id' => 'STU-BULK-'.$index,
            'last_name' => $name,
            'first_name' => 'Selected',
            'course' => 'BS Information Systems',
            'block' => 'A',
            'year_level' => '1st Year',
        ]));

        $response = $this->from('/students?course=BS+Information+Systems')->delete('/students/bulk', [
            'student_ids' => [$students[0]->id, $students[2]->id],
        ]);

        $response->assertRedirect('/students?course=BS+Information+Systems');
        $response->assertSessionHas('student_status', 'Deleted 2 selected student record(s).');
        $this->assertDatabaseMissing('students', ['id' => $students[0]->id]);
        $this->assertDatabaseHas('students', ['id' => $students[1]->id]);
        $this->assertDatabaseMissing('students', ['id' => $students[2]->id]);
    }

    public function test_student_list_has_bulk_selection_controls(): void
    {
        $student = Student::create([
            'student_id' => 'STU-SELECT-1',
            'last_name' => 'Selectable',
            'first_name' => 'Student',
            'course' => 'BS Information Systems',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $response = $this->get('/students?search=STU-SELECT-1');

        $response->assertOk();
        $response->assertSee('id="select-all-students"', false);
        $response->assertSee('name="student_ids[]" value="'.$student->id.'"', false);
        $response->assertSee('action="'.route('students.destroy-selected').'"', false);
        $response->assertSee('Delete Selected');
    }

    public function test_students_can_be_imported_from_csv(): void
    {
        Mail::fake();
        $csv = implode("\n", [
            'student_id,email,last_name,first_name,course,block,year_level',
            'STU-26001,mika@example.com,Santos,Mika,BS Information Systems,A,1st Year',
            'STU-26002,noah@example.com,Ramos,Noah,BS Computer Science,B,2nd Year',
        ]);

        $response = $this->post('/students/import', [
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ]);

        $response->assertRedirect('/students');

        $this->assertDatabaseHas('students', [
            'student_id' => 'STU-26001',
            'last_name' => 'Santos',
            'first_name' => 'Mika',
            'course' => 'BS Information Systems',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $this->assertDatabaseHas('students', [
            'student_id' => 'STU-26002',
            'last_name' => 'Ramos',
            'first_name' => 'Noah',
            'course' => 'BS Computer Science',
            'block' => 'B',
            'year_level' => '2nd Year',
        ]);
    }

    public function test_students_can_be_imported_from_windows_encoded_csv(): void
    {
        Mail::fake();
        $csv = implode("\n", [
            'student_id,email,last_name,first_name,course,block,year_level',
            "STU-26003,jhon@example.com,Fari\xF1as,Jhon,BSIT,B,1st Year",
        ]);

        $this->post('/students/import', [
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertRedirect('/students');

        $this->assertDatabaseHas('students', [
            'student_id' => 'STU-26003',
            'last_name' => 'Fariñas',
        ]);
    }

    public function test_xlsx_import_keeps_columns_aligned_when_a_cell_is_blank(): void
    {
        Mail::fake();
        $path = tempnam(sys_get_temp_dir(), 'student-import-');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE) === true);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="inlineStr"><is><t>student_id</t></is></c><c r="B1" t="inlineStr"><is><t>email</t></is></c><c r="C1" t="inlineStr"><is><t>last_name</t></is></c><c r="D1" t="inlineStr"><is><t>first_name</t></is></c><c r="E1" t="inlineStr"><is><t>course</t></is></c><c r="F1" t="inlineStr"><is><t>block</t></is></c><c r="G1" t="inlineStr"><is><t>year_level</t></is></c></row>'
            .'<row r="2"><c r="A2" t="inlineStr"><is><t>STU-XLSX</t></is></c><c r="B2" t="inlineStr"><is><t>xlsx@example.com</t></is></c><c r="C2" t="inlineStr"><is><t>Rivera</t></is></c><c r="D2" t="inlineStr"><is><t>Ana</t></is></c><c r="E2" t="inlineStr"><is><t>BSIT</t></is></c><c r="G2" t="inlineStr"><is><t>2nd Year</t></is></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();

        try {
            $response = $this->post('/students/import', [
                'student_file' => UploadedFile::fake()->createWithContent('students.xlsx', file_get_contents($path)),
            ]);
        } finally {
            unlink($path);
        }

        $response->assertRedirect('/students');
        $response->assertSessionHas('student_status', 'Imported 1 student record(s). Skipped 0 row(s). 1 email(s) submitted to the configured mail transport.');
        $this->assertDatabaseHas('students', ['student_id' => 'STU-XLSX', 'block' => '', 'year_level' => '2nd Year']);
    }

    public function test_import_rejects_missing_required_headers(): void
    {
        Mail::fake();
        $csv = implode("\n", [
            'student_id,last_name,first_name,course,block,year_level',
            'STU-NO-HEADER,Rivera,Ana,BSIT,A,2nd Year',
        ]);

        $this->post('/students/import', [
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('student_import_error', 'Missing required headers: email.');

        $this->assertDatabaseMissing('students', ['student_id' => 'STU-NO-HEADER']);
        Mail::assertNothingSent();
    }

    public function test_non_admin_cannot_import_students(): void
    {
        $this->withoutMiddleware(RedirectBoothUsersToScanner::class);
        $this->actingAs(User::factory()->create(['role' => 'student']));
        $csv = implode("\n", [
            'student_id,email,last_name,first_name,course,block,year_level',
            'STU-UNAUTHORIZED,student@example.com,Rivera,Ana,BSIT,A,2nd Year',
        ]);

        $this->post('/students/import', [
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertForbidden();

        $this->assertDatabaseMissing('students', ['student_id' => 'STU-UNAUTHORIZED']);
    }

    public function test_import_skips_a_student_without_email(): void
    {
        Mail::fake();
        $csv = implode("\n", [
            'student_id,email,last_name,first_name,course,block,year_level',
            'STU-VALID,valid@example.com,Santos,Mika,BSIT,A,1st Year',
            'STU-NO-EMAIL,,Ramos,Noah,BSCS,B,2nd Year',
        ]);

        $this->post('/students/import', [
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('student_status', 'Imported 1 student record(s). Skipped 1 row(s). 1 email(s) submitted to the configured mail transport.');

        $this->assertDatabaseHas('students', ['student_id' => 'STU-VALID']);
        $this->assertDatabaseMissing('students', ['student_id' => 'STU-NO-EMAIL']);
        Mail::assertSent(StudentAccountCredentials::class, 1);
    }

    public function test_import_skips_email_owned_by_an_admin_without_changing_that_account(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
        $oldPassword = $admin->password;
        Mail::fake();
        $csv = implode("\n", [
            'student_id,email,last_name,first_name,course,block,year_level',
            'STU-ADMIN,admin@example.com,Davis,John,BSIT,A,1st Year',
        ]);

        $this->post('/students/import', [
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('student_status', 'Imported 0 student record(s). Skipped 1 row(s). 0 email(s) submitted to the configured mail transport.');

        $this->assertDatabaseMissing('students', ['student_id' => 'STU-ADMIN']);
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame($oldPassword, $admin->fresh()->password);
        Mail::assertNothingSent();
    }

    public function test_reimport_updates_student_without_resetting_an_unchanged_account(): void
    {
        $student = Student::create([
            'student_id' => 'STU-REIMPORT', 'last_name' => 'Davis', 'first_name' => 'John',
            'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year',
        ]);
        $user = User::factory()->create(['email' => 'student@example.com', 'role' => 'student']);
        $student->user()->associate($user);
        $student->save();
        $oldPassword = $user->password;
        Mail::fake();
        $csv = implode("\n", [
            'student_id,email,last_name,first_name,course,block,year_level',
            'STU-REIMPORT,student@example.com,Santos,John,BSCS,B,2nd Year',
        ]);

        $this->post('/students/import', [
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('student_status', 'Imported 1 student record(s). Skipped 0 row(s). 0 email(s) submitted to the configured mail transport.');

        $this->assertDatabaseHas('students', ['id' => $student->id, 'last_name' => 'Santos', 'course' => 'BSCS', 'block' => 'B']);
        $this->assertSame($oldPassword, $user->fresh()->password);
        Mail::assertNothingSent();
    }

    public function test_import_cannot_assign_another_students_email(): void
    {
        $other = Student::create([
            'student_id' => 'STU-OTHER', 'last_name' => 'Davis', 'first_name' => 'Jane',
            'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year',
        ]);
        $user = User::factory()->create(['email' => 'taken@example.com', 'role' => 'student']);
        $other->user()->associate($user);
        $other->save();
        Mail::fake();
        $csv = implode("\n", [
            'student_id,email,last_name,first_name,course,block,year_level',
            'STU-NEW,taken@example.com,Santos,Mika,BSCS,B,2nd Year',
        ]);

        $this->post('/students/import', [
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('student_status', 'Imported 0 student record(s). Skipped 1 row(s). 0 email(s) submitted to the configured mail transport.');

        $this->assertDatabaseMissing('students', ['student_id' => 'STU-NEW']);
        $this->assertSame($user->id, $other->fresh()->user_id);
        Mail::assertNothingSent();
    }

    public function test_failed_reimport_preserves_existing_password(): void
    {
        $student = Student::create([
            'student_id' => 'STU-UPDATE-EMAIL', 'last_name' => 'Davis', 'first_name' => 'John',
            'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year',
        ]);
        $user = User::factory()->create(['email' => 'old@example.com', 'role' => 'student']);
        $student->user()->associate($user);
        $student->save();
        $oldPassword = $user->password;
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));
        $csv = implode("\n", [
            'student_id,email,last_name,first_name,course,block,year_level',
            'STU-UPDATE-EMAIL,new@example.com,Davis,John,BSIT,A,1st Year',
        ]);

        $this->post('/students/import', [
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ])->assertSessionHas('student_mail_failures', [['id' => $student->id, 'email' => 'new@example.com']]);

        $this->assertSame($oldPassword, $user->fresh()->password);
    }

    public function test_import_reports_failed_credential_delivery(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));
        $csv = implode("\n", [
            'student_id,email,last_name,first_name,course,block,year_level',
            'STU-IMPORT-FAILED,failed@example.com,Santos,Mika,BSIT,A,1st Year',
        ]);

        $response = $this->post('/students/import', [
            'student_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ]);

        $student = Student::where('student_id', 'STU-IMPORT-FAILED')->firstOrFail();
        $response->assertSessionHas('student_status', 'Imported 1 student record(s). Skipped 0 row(s). 0 email(s) submitted to the configured mail transport; 1 failed.');
        $response->assertSessionHas('student_mail_failures', [['id' => $student->id, 'email' => 'failed@example.com']]);
    }

    public function test_students_can_be_filtered_by_course_block_and_year_level(): void
    {
        Student::create([
            'student_id' => 'STU-27001',
            'last_name' => 'Davis',
            'first_name' => 'John',
            'course' => 'Information Technology',
            'block' => 'B',
            'year_level' => '3rd Year',
        ]);

        Student::create([
            'student_id' => 'STU-27002',
            'last_name' => 'Smith',
            'first_name' => 'Emily',
            'course' => 'Elementary Education',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $response = $this->get('/students?course=Information+Technology&block=B&year_level=3rd+Year');

        $response->assertOk();
        $response->assertSee('STU-27001');
        $response->assertSee('Davis');
        $response->assertDontSee('STU-27002');
        $response->assertSee('All courses');
        $response->assertSee('All blocks');
        $response->assertSee('All year levels');
    }

    public function test_students_can_be_filtered_by_search(): void
    {
        Student::create([
            'student_id' => 'STU-28001',
            'last_name' => 'Santos',
            'first_name' => 'Mika',
            'course' => 'Information Technology',
            'block' => 'B',
            'year_level' => '3rd Year',
        ]);

        Student::create([
            'student_id' => 'STU-28002',
            'last_name' => 'Ramos',
            'first_name' => 'Noah',
            'course' => 'Criminology',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $response = $this->get('/students?course=Information+Technology&search=Mika');

        $response->assertOk();
        $response->assertSee('STU-28001');
        $response->assertDontSee('STU-28002');
        $response->assertSee('value="Mika"', false);
        $response->assertDontSee('Print QR');
    }

    public function test_student_rows_show_account_link_status_and_qr_code(): void
    {
        $student = Student::create([
            'student_id' => 'STU-29001',
            'last_name' => 'Rivera',
            'first_name' => 'Alyssa',
            'course' => 'Information Technology',
            'block' => 'C',
            'year_level' => '4th Year',
        ]);

        $response = $this->get('/students?search=STU-29001');

        $response->assertOk();
        $response->assertSee('Not linked');
        $response->assertSee('Rivera, Alyssa');
        $response->assertSee('href="#student-details-modal-'.$student->id.'"', false);
        $response->assertSee('id="student-details-modal-'.$student->id.'"', false);
        $response->assertSee('Student Details');
        $response->assertSee('Information Technology');
        $response->assertSee('4th Year');
        $response->assertSee('Current status:');
        $response->assertSee('Attendance QR Code');
        $response->assertDontSee('Print QR');
    }
}
