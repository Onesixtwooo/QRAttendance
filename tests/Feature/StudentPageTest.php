<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

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
        $response->assertDontSee('href="#add-student-modal"', false);
        $response->assertDontSee('href="#import-students-modal"', false);
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
        $response = $this->post('/students', [
            'student_id' => 'STU-25001',
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
        $response = $this->post('/students', [
            'student_id' => 'STU-25002',
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

    public function test_multiple_students_can_be_saved_from_the_spreadsheet(): void
    {
        $response = $this->post('/students/bulk', [
            'students' => [
                ['student_id' => 'STU-GRID-1', 'last_name' => 'Davis', 'first_name' => 'John', 'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year'],
                ['student_id' => 'STU-GRID-2', 'last_name' => 'Santos', 'first_name' => 'Mika', 'course' => 'BSCS', 'block' => 'B', 'year_level' => '2nd Year'],
            ],
        ]);

        $response->assertRedirect('/students');
        $response->assertSessionHas('student_status', 'Added 2 student record(s).');
        $this->assertDatabaseHas('students', ['student_id' => 'STU-GRID-1']);
        $this->assertDatabaseHas('students', ['student_id' => 'STU-GRID-2']);
    }

    public function test_student_can_be_saved_from_the_spreadsheet_without_a_block(): void
    {
        $response = $this->post('/students/bulk', [
            'students' => [
                ['student_id' => 'STU-NO-BLOCK', 'last_name' => 'Davis', 'first_name' => 'John', 'course' => 'BSIT', 'block' => '', 'year_level' => '1st Year'],
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
                ['student_id' => 'STU-NEW', 'last_name' => 'New', 'first_name' => 'Student', 'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year'],
                ['student_id' => 'STU-EXISTS', 'last_name' => 'Duplicate', 'first_name' => 'Student', 'course' => 'BSIT', 'block' => 'A', 'year_level' => '1st Year'],
            ],
        ]);

        $response->assertRedirect('/students#add-student-modal');
        $response->assertSessionHasErrors('students.1.student_id', null, 'bulkStudents');
        $this->assertDatabaseMissing('students', ['student_id' => 'STU-NEW']);
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
        $csv = implode("\n", [
            'student_id,last_name,first_name,course,block,year_level',
            'STU-26001,Santos,Mika,BS Information Systems,A,1st Year',
            'STU-26002,Ramos,Noah,BS Computer Science,B,2nd Year',
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

    public function test_student_rows_show_account_link_status_without_qr_controls(): void
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
        $response->assertSee('No account linked');
        $response->assertDontSee('Student QR');
        $response->assertDontSee('Print QR');
    }
}
