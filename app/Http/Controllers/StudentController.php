<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ZipArchive;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->studentFilters($request);
        $students = $this->studentRows($this->filteredStudents($filters)->get());

        $filterOptions = [
            'courses' => Student::query()->select('course')->distinct()->orderBy('course')->pluck('course'),
            'blocks' => Student::query()->select('block')->distinct()->orderBy('block')->pluck('block'),
            'year_levels' => collect(['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year']),
        ];

        $editStudent = null;

        if ($request->query('edit')) {
            $editStudent = Student::find($request->query('edit'));
        }

        return view('welcome', [
            'page' => 'students',
            'students' => $students,
            'editStudent' => $editStudent,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
        ]);
    }

    public function printQr(Request $request): View
    {
        $filters = $this->studentFilters($request);
        $students = $this->studentRows($this->filteredStudents($filters)->get());

        return view('students.qr-print', [
            'students' => $students,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Student::create($this->validated($request));

        return redirect()
            ->route('students')
            ->with('student_status', 'Student saved.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $student->update($this->validated($request, $student));

        return redirect()
            ->route('students')
            ->with('student_status', 'Student updated.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()
            ->route('students')
            ->with('student_status', 'Student deleted.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'student_file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ]);

        $file = $request->file('student_file');
        $rows = $file->getClientOriginalExtension() === 'xlsx'
            ? $this->readXlsx($file->getRealPath())
            : $this->readCsv($file->getRealPath());

        if (count($rows) < 2) {
            return redirect()
                ->route('students')
                ->with('student_import_error', 'Import file must include a header row and at least one student row.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows));
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $data = $this->mapStudentRow($headers, $row);

            if (count(array_filter($data, fn ($value) => $value !== null && $value !== '')) === 0) {
                continue;
            }

            $validator = Validator::make($data, $this->studentRules());

            if ($validator->fails()) {
                $skipped++;
                continue;
            }

            Student::updateOrCreate(
                ['student_id' => $data['student_id']],
                $validator->validated()
            );

            $imported++;
        }

        return redirect()
            ->route('students')
            ->with('student_status', "Imported {$imported} student record(s). Skipped {$skipped} row(s).");
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate($this->studentRules($student));
    }

    private function studentFilters(Request $request): array
    {
        return $request->only(['course', 'block', 'year_level', 'search']);
    }

    private function filteredStudents(array $filters)
    {
        return Student::query()
            ->when($filters['course'] ?? null, fn ($query, $course) => $query->where('course', $course))
            ->when($filters['block'] ?? null, fn ($query, $block) => $query->where('block', $block))
            ->when($filters['year_level'] ?? null, fn ($query, $yearLevel) => $query->where('year_level', $yearLevel))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $search = addcslashes($search, '%_\\');

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('student_id', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('course', 'like', "%{$search}%")
                        ->orWhere('block', 'like', "%{$search}%")
                        ->orWhere('year_level', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    private function studentRows($students)
    {
        return $students->map(fn (Student $student): array => [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'last_name' => $student->last_name,
            'first_name' => $student->first_name,
            'course' => $student->course,
            'block' => $student->block,
            'year_level' => $student->year_level,
        ]);
    }

    private function studentRules(?Student $student = null): array
    {
        return [
            'student_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'student_id')->ignore($student),
            ],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
            'block' => ['required', 'string', 'max:50'],
            'year_level' => ['required', Rule::in(['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'])],
        ];
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'rb');

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(fn ($value) => trim((string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        $namespace = $sheet->getNamespaces(true)[''] ?? null;
        $sheetData = $namespace
            ? $sheet->children($namespace)->sheetData
            : $sheet->sheetData;

        $rows = [];

        foreach ($sheetData->row as $xmlRow) {
            $row = [];

            foreach ($xmlRow->c as $cell) {
                $attributes = $cell->attributes();
                $columnIndex = $this->columnIndex((string) $attributes['r']);
                $type = (string) $attributes['t'];
                $value = (string) $cell->v;

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) $cell->is->t;
                }

                $row[$columnIndex] = trim($value);
            }

            ksort($row);
            $rows[] = array_values($row);
        }

        return $rows;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $shared = simplexml_load_string($xml);
        $namespace = $shared->getNamespaces(true)[''] ?? null;
        $items = $namespace ? $shared->children($namespace)->si : $shared->si;
        $strings = [];

        foreach ($items as $item) {
            $text = '';

            if (isset($item->t)) {
                $text = (string) $item->t;
            } else {
                foreach ($item->r as $run) {
                    $text .= (string) $run->t;
                }
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function columnIndex(string $cellReference): int
    {
        preg_match('/[A-Z]+/', $cellReference, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
            $header = strtolower(trim($header));

            return str_replace([' ', '-'], '_', $header);
        }, $headers);
    }

    private function mapStudentRow(array $headers, array $row): array
    {
        $aliases = [
            'student_id' => ['student_id', 'studentid', 'student_number', 'id_number'],
            'last_name' => ['last_name', 'lastname', 'surname'],
            'first_name' => ['first_name', 'firstname', 'given_name'],
            'course' => ['course', 'program'],
            'block' => ['block', 'section'],
            'year_level' => ['year_level', 'yearlevel', 'year'],
        ];

        $data = [];

        foreach ($aliases as $field => $names) {
            $data[$field] = null;

            foreach ($names as $name) {
                $index = array_search($name, $headers, true);

                if ($index !== false) {
                    $data[$field] = trim((string) ($row[$index] ?? ''));
                    break;
                }
            }
        }

        return $data;
    }
}
