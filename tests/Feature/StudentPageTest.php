<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_page_shows_profile_columns_without_attendance_fields(): void
    {
        $response = $this->get('/students');

        $response->assertOk();
        $response->assertSee('Student ID');
        $response->assertSee('Last Name');
        $response->assertSee('First Name');
        $response->assertSee('Course');
        $response->assertSee('Block');
        $response->assertSee('Year Level');
        $response->assertSee('Select year level');
        $response->assertSee('5th Year');
        $response->assertSee('href="#add-student-modal"', false);
        $response->assertSee('href="#import-students-modal"', false);
        $response->assertSee('Import Students');
        $response->assertSee('Add Student');
        $response->assertSee('Save Student');
        $response->assertDontSee('Print QR');
        $response->assertDontSee('Time In');
    }

    public function test_students_are_not_loaded_until_a_filter_query_is_applied(): void
    {
        Student::create([
            'student_id' => 'STU-24000',
            'last_name' => 'Unfiltered',
            'first_name' => 'Student',
            'course' => 'Information Technology',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $response = $this->get('/students');

        $response->assertOk();
        $response->assertDontSee('STU-24000');
        $response->assertSee('Use the search or filters above, then click Apply to load students.');
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
        $response->assertSee(url('/students/qr-print?course=Information%20Technology&amp;search=Mika'), false);
        $response->assertSee('Print QR');
    }

    public function test_each_student_row_can_generate_its_own_qr(): void
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
        $response->assertSee('href="#student-qr-modal-'.$student->id.'"', false);
        $response->assertSee('Student QR');
        $response->assertSee('Alyssa Rivera - STU-29001');
        $response->assertSee('QR code for Alyssa Rivera');
        $response->assertSee('Print Individual QR');
        $response->assertSee(url('/students/qr-print?search=STU-29001&amp;student='.$student->id), false);
        $response->assertSee(urlencode('"student_id":"STU-29001"'), false);
    }

    public function test_individual_student_qr_can_be_opened_as_a_printable_card(): void
    {
        $student = Student::create([
            'student_id' => 'STU-29501',
            'last_name' => 'Davis',
            'first_name' => 'John',
            'course' => 'Information Technology',
            'block' => 'B',
            'year_level' => '3rd Year',
        ]);

        Student::create([
            'student_id' => 'STU-29502',
            'last_name' => 'Davis',
            'first_name' => 'Marina',
            'course' => 'Criminology',
            'block' => 'A',
            'year_level' => '2nd Year',
        ]);

        $response = $this->get('/students/qr-print?search=davis&student='.$student->id);

        $response->assertOk();
        $response->assertSee('Student QR Print Card');
        $response->assertSee('1 QR code for the selected student.');
        $response->assertSee('Individual student QR code');
        $response->assertSee('John Davis - STU-29501');
        $response->assertSee('Information Technology');
        $response->assertSee('3rd Year - Block B');
        $response->assertDontSee('Marina Davis');
        $response->assertDontSee('STU-29502');
        $response->assertSee(urlencode('"student_id":"STU-29501"'), false);
    }

    public function test_filtered_students_can_be_opened_as_a_printable_qr_sheet(): void
    {
        Student::create([
            'student_id' => 'STU-30001',
            'last_name' => 'Santos',
            'first_name' => 'Mika',
            'course' => 'Information Technology',
            'block' => 'B',
            'year_level' => '3rd Year',
        ]);

        Student::create([
            'student_id' => 'STU-30002',
            'last_name' => 'Ramos',
            'first_name' => 'Noah',
            'course' => 'Criminology',
            'block' => 'A',
            'year_level' => '1st Year',
        ]);

        $response = $this->get('/students/qr-print?course=Information+Technology&search=Mika');

        $response->assertOk();
        $response->assertSee('Student QR Print Sheet');
        $response->assertSee('1 QR code(s) from the current filtered student list.');
        $response->assertSee('Print QR');
        $response->assertSee('Mika Santos');
        $response->assertSee('STU-30001');
        $response->assertSee('3rd Year - Block B');
        $response->assertDontSee('Noah Ramos');
        $response->assertDontSee('STU-30002');
        $response->assertSee(urlencode('"student_id":"STU-30001"'), false);
    }
}
