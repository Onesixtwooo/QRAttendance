@php
    $filters = array_merge(['course' => null, 'block' => null, 'year_level' => null, 'search' => null], $filters ?? []);
    $activeFilters = collect($filters)->filter(fn ($value) => filled($value));
    $students = $students ?? collect();
    $printMode = $printMode ?? 'batch';
    $isIndividualPrint = $printMode === 'individual';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Student QR Codes</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f4f5;
            color: #09090b;
            font-family: Arial, sans-serif;
        }

        .toolbar {
            align-items: center;
            background: #ffffff;
            border-bottom: 1px solid #e4e4e7;
            display: flex;
            gap: 12px;
            justify-content: space-between;
            padding: 18px 24px;
        }

        h1 {
            font-size: 24px;
            margin: 0;
        }

        .muted {
            color: #71717a;
            font-size: 13px;
            margin: 6px 0 0;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .button {
            border: 1px solid #d4d4d8;
            border-radius: 8px;
            color: #3f3f46;
            font-size: 14px;
            font-weight: 700;
            padding: 10px 14px;
            text-decoration: none;
        }

        .button.primary {
            background: #09090b;
            border-color: #09090b;
            color: #ffffff;
        }

        .sheet {
            margin: 24px auto;
            max-width: 1100px;
            padding: 0 24px 24px;
        }

        .summary {
            background: #ffffff;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            margin-bottom: 18px;
            padding: 14px 16px;
        }

        .summary-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .summary-list span {
            background: #f4f4f5;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 10px;
        }

        .qr-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        }

        .qr-card {
            background: #ffffff;
            border: 1px solid #d4d4d8;
            border-radius: 8px;
            break-inside: avoid;
            padding: 14px;
            text-align: center;
        }

        .qr-card img {
            height: 170px;
            width: 170px;
        }

        .student-name {
            font-size: 16px;
            font-weight: 800;
            margin: 10px 0 3px;
        }

        .student-id {
            font-size: 13px;
            font-weight: 800;
            margin: 0;
        }

        .student-meta {
            color: #52525b;
            font-size: 12px;
            line-height: 1.4;
            margin: 8px 0 0;
        }

        .empty {
            background: #ffffff;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            padding: 24px;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                margin: 0;
                max-width: none;
                padding: 0;
            }

            .summary {
                display: none;
            }

            .qr-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .qr-card {
                border-color: #71717a;
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <header class="toolbar">
        <div>
            <h1>{{ $isIndividualPrint ? 'Student QR Print Card' : 'Student QR Print Sheet' }}</h1>
            <p class="muted">
                @if ($isIndividualPrint)
                    {{ $students->count() }} QR code for the selected student.
                @else
                    {{ $students->count() }} QR code(s) from the current filtered student list.
                @endif
            </p>
        </div>
        <div class="actions">
            <a class="button" href="{{ route('students', $activeFilters->all()) }}">Back</a>
            <button class="button primary" type="button" onclick="window.print()">Print QR</button>
        </div>
    </header>

    <main class="sheet">
        <section class="summary">
            <strong>{{ $isIndividualPrint ? 'Individual student QR code' : 'Filtered student QR codes' }}</strong>
            @if ($isIndividualPrint && $students->isNotEmpty())
                @php
                    $student = $students->first();
                    $studentName = trim($student['first_name'].' '.$student['last_name']);
                @endphp
                <p class="muted">{{ $studentName }} - {{ $student['student_id'] }}</p>
            @elseif ($activeFilters->isNotEmpty())
                <div class="summary-list">
                    @foreach ($activeFilters as $key => $value)
                        <span>{{ str_replace('_', ' ', ucfirst($key)) }}: {{ $value }}</span>
                    @endforeach
                </div>
            @else
                <p class="muted">No filters applied.</p>
            @endif
        </section>

        @if ($students->isNotEmpty())
            <section class="qr-grid">
                @foreach ($students as $student)
                    @php
                        $studentName = trim($student['first_name'].' '.$student['last_name']);
                        $studentQrPayload = json_encode([
                            'type' => 'student',
                            'student_id' => $student['student_id'],
                            'name' => $studentName,
                            'course' => $student['course'],
                            'block' => $student['block'],
                            'year_level' => $student['year_level'],
                        ], JSON_UNESCAPED_SLASHES);
                        $studentQrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='.urlencode($studentQrPayload);
                    @endphp

                    <article class="qr-card">
                        <img src="{{ $studentQrImageUrl }}" alt="QR code for {{ $studentName }}">
                        <p class="student-name">{{ $studentName }}</p>
                        <p class="student-id">{{ $student['student_id'] }}</p>
                        <p class="student-meta">{{ $student['course'] }}<br>{{ $student['year_level'] }} - Block {{ $student['block'] }}</p>
                    </article>
                @endforeach
            </section>
        @else
            <section class="empty">
                <strong>No student QR codes to print.</strong>
                <p class="muted">Adjust the filters and try again.</p>
            </section>
        @endif
    </main>
</body>
</html>
