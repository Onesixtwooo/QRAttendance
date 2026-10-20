<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use App\Mail\StudentAccountCredentials;
use App\Support\StudentQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;
use ZipArchive;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->studentFilters($request);
        $filtersApplied = collect($filters)->contains(fn ($value) => filled($value));
        $studentQuery = $this->filteredStudents($filters);
        $students = $this->studentRows(
            $filtersApplied ? $studentQuery->with('user')->get() : $studentQuery->with('user')->limit(10)->get()
        );

        $filterOptions = [
            'courses' => Student::query()->select('course')->distinct()->orderBy('course')->pluck('course'),
            'blocks' => Student::query()->select('block')->distinct()->orderBy('block')->pluck('block'),
            'year_levels' => collect(['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year']),
        ];

        $editStudent = null;

        if ($request->query('edit')) {
            $editStudent = Student::with('user')->find($request->query('edit'));
        }

        return view('welcome', [
            'page' => 'students',
            'students' => $students,
            'editStudent' => $editStudent,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'studentsLoaded' => true,
            'showingInitialStudents' => ! $filtersApplied,
        ]);
    }

    public function qr(Student $student, StudentQrCode $qrCode): JsonResponse
    {
        return response()->json($qrCode->generate($student));
    }

    public function saveAccount(Request $request, Student $student): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        $user = $student->user;
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($student, $user, $data): void {
            $user ??= new User;
            $user->name = trim($student->first_name.' '.$student->last_name);
            $user->email = $data['email'];
            $user->role = 'student';
            $user->is_active = true;

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }

            $user->save();
            $student->user()->associate($user);
            $student->save();
        });

        return redirect()->route('students')->with('student_status', 'Student login account saved.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge($this->studentRules(), [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ]));
        $email = $data['email'];
        unset($data['email']);
        $student = Student::create($data);
        $sent = $this->provisionAndNotify($student, $email);
        $status = $sent
            ? 'Student saved. '.$this->credentialDeliveryStatus(1).'.'
            : 'Student saved, but credentials could not be sent. Check the mail settings and resend them.';

        return redirect()
            ->route('students')
            ->with('student_status', $status)
            ->with('student_mail_failures', $sent ? [] : [['id' => $student->id, 'email' => $email]]);
    }

    public function storeBulk(Request $request): RedirectResponse
    {
        $students = collect($request->input('students', []))
            ->filter(fn ($student): bool => is_array($student) && collect($student)->contains(fn ($value): bool => filled($value)))
            ->values()
            ->all();
        $request->merge(['students' => $students]);

        $data = $request->validateWithBag('bulkStudents', [
            'students' => ['required', 'array', 'min:1', 'max:100'],
            'students.*.student_id' => ['required', 'string', 'max:50', 'distinct', Rule::unique('students', 'student_id')],
            'students.*.email' => ['required', 'email', 'max:255', 'distinct', Rule::unique('users', 'email')],
            'students.*.last_name' => ['required', 'string', 'max:255'],
            'students.*.first_name' => ['required', 'string', 'max:255'],
            'students.*.course' => ['required', 'string', 'max:255'],
            'students.*.block' => ['nullable', Rule::in(range('A', 'G'))],
            'students.*.year_level' => ['required', Rule::in(['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'])],
        ]);

        $credentials = DB::transaction(function () use ($data): array {
            $credentials = [];
            foreach ($data['students'] as $studentData) {
                $email = $studentData['email'];
                unset($studentData['email']);
                $student = Student::create($studentData);
                $credentials[] = [$student, $this->provisionStudentAccount($student, $email)];
            }

            return $credentials;
        });

        $count = count($data['students']);
        $sent = 0;
        $failed = [];

        foreach ($credentials as [$student, $password]) {
            if ($this->sendStudentCredentials($student, $password)) {
                $sent++;
            } else {
                $failed[] = ['id' => $student->id, 'email' => $student->user->email];
            }
        }

        $mailResult = $this->credentialDeliveryStatus($sent);
        if ($failed !== []) {
            $mailResult .= '; '.count($failed).' failed';
        }

        return redirect()
            ->route('students')
            ->with('student_status', "Added {$count} student record(s). {$mailResult}.")
            ->with('student_mail_failures', $failed);
    }

    public function resendCredentials(Request $request, Student $student): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless($student->user?->role === 'student', 404);

        if (config('mail.default') === 'log') {
            return redirect()->route('students')->with('student_status', 'Mail is configured for logging. Configure an email transport before resending credentials.');
        }

        $password = str()->random(12);
        $previousPassword = $student->user->password;
        $student->user->update(['password' => $password]);

        if ($this->sendStudentCredentials($student, $password)) {
            $status = 'Credentials submitted to the configured mail transport for '.$student->user->email.'.';
        } else {
            $student->user->update(['password' => $previousPassword]);
            $status = 'Credentials could not be sent to '.$student->user->email.'. The existing password still works. Check the mail settings and try again.';
        }

        return redirect()->route('students')->with('student_status', $status);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validated($request, $student);

        $student->update($data);

        return redirect()
            ->route('students')
            ->with('student_status', 'Student updated.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        DB::transaction(function () use ($student): void {
            $user = $student->user;
            $student->delete();

            if ($user?->role === 'student') {
                $user->delete();
            }
        });

        return redirect()
            ->route('students')
            ->with('student_status', 'Student deleted.');
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ]);
        $students = Student::with('user')->whereKey($data['student_ids'])->get();
        $deleted = $students->count();

        DB::transaction(function () use ($students): void {
            foreach ($students as $student) {
                $user = $student->user;
                $student->delete();

                if ($user?->role === 'student') {
                    $user->delete();
                }
            }
        });

        return back()->with('student_status', "Deleted {$deleted} selected student record(s).");
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        [$headers, $rows, $error] = $this->uploadedStudentRows($request);

        if ($error !== null) {
            return redirect()->route('students')->with('student_import_error', $error);
        }

        $imported = 0;
        $skipped = 0;
        $sent = 0;
        $failed = [];

        foreach ($rows as $row) {
            $result = $this->importStudentRow($headers, $row);
            $imported += (int) ($result['status'] === 'imported');
            $skipped += (int) ($result['status'] === 'skipped');
            $sent += (int) $result['sent'];
            if ($result['failure'] !== null) {
                $failed[] = $result['failure'];
            }
        }

        $status = "Imported {$imported} student record(s). Skipped {$skipped} row(s). ".$this->credentialDeliveryStatus($sent);
        if ($failed !== []) {
            $status .= '; '.count($failed).' failed';
        }

        return redirect()
            ->route('students')
            ->with('student_status', $status.'.')
            ->with('student_mail_failures', $failed);
    }

    public function prepareImport(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        [$headers, $rows, $error] = $this->uploadedStudentRows($request);
        if ($error !== null) {
            return response()->json(['message' => $error], 422);
        }

        $token = (string) Str::uuid();
        foreach (Storage::disk('local')->files('student-imports') as $oldPath) {
            if (Storage::disk('local')->lastModified($oldPath) < time() - 3600) {
                Storage::disk('local')->delete($oldPath);
            }
        }
        Storage::disk('local')->put("student-imports/{$token}.json", json_encode([
            'user_id' => $request->user()->id,
            'created_at' => time(),
            'headers' => $headers,
            'rows' => $rows,
            'next' => 0,
            'imported' => 0,
            'skipped' => 0,
            'sent' => 0,
            'failed' => [],
        ], JSON_THROW_ON_ERROR));

        return response()->json(['token' => $token, 'total' => count($rows)]);
    }

    public function processImport(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['token' => ['required', 'uuid']]);
        $path = "student-imports/{$data['token']}.json";
        abort_unless(Storage::disk('local')->exists($path), 404);

        $handle = fopen(Storage::disk('local')->path($path), 'r+');
        abort_unless($handle !== false, 500);
        flock($handle, LOCK_EX);

        try {
            $state = json_decode(stream_get_contents($handle), true, 512, JSON_THROW_ON_ERROR);
            abort_unless($state['user_id'] === $request->user()->id, 403);
            abort_if($state['created_at'] < time() - 3600, 410, 'Import expired. Please upload the file again.');

            $recent = [];
            $end = min($state['next'] + 10, count($state['rows']));
            while ($state['next'] < $end) {
                $row = $state['rows'][$state['next']];
                $result = $this->importStudentRow($state['headers'], $row);
                $state['next']++;
                $state['imported'] += (int) ($result['status'] === 'imported');
                $state['skipped'] += (int) ($result['status'] === 'skipped');
                $state['sent'] += (int) $result['sent'];
                if ($result['failure'] !== null) {
                    $state['failed'][] = $result['failure'];
                }
                $recent[] = [
                    'row' => $state['next'] + 1,
                    'student_id' => $this->mapStudentRow($state['headers'], $row)['student_id'],
                    'status' => $result['status'],
                ];
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);

            $complete = $state['next'] === count($state['rows']);
            if ($complete) {
                $status = "Imported {$state['imported']} student record(s). Skipped {$state['skipped']} row(s). ".$this->credentialDeliveryStatus($state['sent']);
                if ($state['failed'] !== []) {
                    $status .= '; '.count($state['failed']).' failed';
                }
                $request->session()->flash('student_status', $status.'.');
                $request->session()->flash('student_mail_failures', $state['failed']);
            }

            return response()->json([
                'processed' => $state['next'],
                'total' => count($state['rows']),
                'imported' => $state['imported'],
                'skipped' => $state['skipped'],
                'mail_failures' => count($state['failed']),
                'recent' => $recent,
                'complete' => $complete,
                'redirect' => $complete ? route('students') : null,
            ]);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function uploadedStudentRows(Request $request): array
    {
        $request->validate(['student_file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120']]);
        $file = $request->file('student_file');
        $rows = strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? $this->readXlsx($file->getRealPath())
            : $this->readCsv($file->getRealPath());

        if (count($rows) < 2) {
            return [[], [], 'Import file must include a header row and at least one student row.'];
        }

        $headers = $this->normalizeHeaders(array_shift($rows));
        $missing = [];
        foreach ($this->studentImportAliases() as $field => $aliases) {
            if (array_intersect($aliases, $headers) === []) {
                $missing[] = $field;
            }
        }

        return [$headers, $rows, $missing === [] ? null : 'Missing required headers: '.implode(', ', $missing).'.'];
    }

    private function importStudentRow(array $headers, array $row): array
    {
        $data = $this->mapStudentRow($headers, $row);
        if (count(array_filter($data, fn ($value) => $value !== null && $value !== '')) === 0) {
            return ['status' => 'blank', 'sent' => false, 'failure' => null];
        }

        $existingStudent = Student::with('user')->where('student_id', $data['student_id'])->first();
        $validator = Validator::make($data, array_merge($this->studentRules($existingStudent), [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existingStudent?->user)],
        ]));
        if ($validator->fails()) {
            return ['status' => 'skipped', 'sent' => false, 'failure' => null];
        }

        $validated = $validator->validated();
        $email = $validated['email'];
        unset($validated['email']);
        $student = Student::updateOrCreate(['student_id' => $validated['student_id']], $validated);
        $sent = false;
        $failure = null;
        if (! $student->user || $student->user->email !== $email) {
            $sent = $this->provisionAndNotify($student, $email);
            if (! $sent) {
                $failure = ['id' => $student->id, 'email' => $email];
            }
        }

        return ['status' => 'imported', 'sent' => $sent, 'failure' => $failure];
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
        $qrCode = app(StudentQrCode::class);


        return $students->map(fn (Student $student): array => [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'last_name' => $student->last_name,
            'first_name' => $student->first_name,
            'course' => $student->course,
            'block' => $student->block,
            'year_level' => $student->year_level,
            'qr_code' => $qrCode->generate($student),
            'account_email' => $student->user?->email,
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
            'block' => ['nullable', 'string', 'max:50'],
            'year_level' => ['required', Rule::in(['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'])],
        ];
    }

    private function provisionAndNotify(Student $student, string $email): bool
    {
        $previousPassword = $student->user?->password;
        $password = $this->provisionStudentAccount($student, $email);
        if ($this->sendStudentCredentials($student, $password)) {
            return true;
        }

        if ($previousPassword !== null) {
            $student->user->update(['password' => $previousPassword]);
        }

        return false;
    }

    private function credentialDeliveryStatus(int $sent): string
    {
        return config('mail.default') === 'log'
            ? "{$sent} email(s) written to the mail log (not emailed)"
            : "{$sent} email(s) submitted to the configured mail transport";
    }

    private function provisionStudentAccount(Student $student, string $email): string
    {
        $temporaryPassword = str()->random(12);
        $user = $student->user ?? new User;
        $user->name = trim($student->first_name.' '.$student->last_name);
        $user->email = $email;
        $user->password = Hash::make($temporaryPassword);
        $user->role = 'student';
        $user->is_active = true;
        $user->save();
        $student->user()->associate($user);
        $student->save();

        return $temporaryPassword;
    }

    private function sendStudentCredentials(Student $student, string $temporaryPassword): bool
    {
        try {
            Mail::to($student->user->email)->send(new StudentAccountCredentials($student->load('user'), $temporaryPassword));

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'rb');

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(function ($value) {
                $value = (string) $value;

                if (! mb_check_encoding($value, 'UTF-8')) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                }

                return trim($value);
            }, $row);
        }

        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

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

            if ($row !== []) {
                $columnCount = max(array_keys($row)) + 1;
                $rows[] = array_replace(array_fill(0, $columnCount, ''), $row);
            }
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
        $aliases = $this->studentImportAliases();

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

    private function studentImportAliases(): array
    {
        return [
            'student_id' => ['student_id', 'studentid', 'student_number', 'id_number'],
            'email' => ['email', 'email_address'],
            'last_name' => ['last_name', 'lastname', 'surname'],
            'first_name' => ['first_name', 'firstname', 'given_name'],
            'course' => ['course', 'program'],
            'block' => ['block', 'section'],
            'year_level' => ['year_level', 'yearlevel', 'year'],
        ];
    }
}
