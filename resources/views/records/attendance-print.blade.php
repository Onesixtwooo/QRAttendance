@php
    $filters = array_merge(['date' => null, 'event_schedule_id' => null, 'course' => null, 'block' => null, 'year_level' => null], $filters ?? []);
    $summary = $summary ?? null;
    $sections = collect($sections ?? []);
    $departmentSummaries = collect($departmentSummaries ?? []);
    $attendees = collect($summary['attendees'] ?? []);
    $attendedCount = $summary['attended_count'] ?? $sections->sum('attended_count');
    $totalStudents = $summary['total_students'] ?? 0;
    $percentageLabel = $summary['percentage_label'] ?? '0%';
    $backFilters = collect($filters)->filter(fn ($value) => filled($value))->all();
    $eventTitle = $event?->title ?? ($summary['event'] ?? 'Attendance Event');
    $eventDate = $event?->event_date?->format('F j, Y') ?? ($summary['date'] ?? 'No event date');
    $pageCount = $sections->count() + 1;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Attendance - {{ $groupLabel }}</title>
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

        h1,
        h2,
        p {
            margin: 0;
        }

        h1,
        h2,
        .document-header {
            font-family: Tahoma, Arial, sans-serif;
        }

        h1 {
            font-size: 22px;
        }

        h2 {
            font-size: 18px;
            margin-top: 4px;
        }

        .muted {
            color: #71717a;
            font-size: 13px;
            margin-top: 6px;
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

        .paper {
            background: #ffffff;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            margin-bottom: 18px;
            overflow: hidden;
        }

        .document-header {
            border: 1px solid #27272a;
            table-layout: fixed;
            overflow-wrap: anywhere;
            width: 100%;
        }

        .document-school-column {
            width: 42%;
        }

        .document-label-column {
            width: 17%;
        }

        .document-header th,
        .document-header td {
            background: #fff;
            border: 1px solid #27272a;
            color: #18181b;
            font-size: 13px;
            font-weight: 400;
            padding: 9px 10px;
            text-align: left;
            text-transform: none;
            vertical-align: middle;
        }

        .school-identity-content {
            align-items: center;
            display: flex;
            gap: 12px;
            min-height: 92px;
        }

        .school-identity-content > div {
            min-width: 0;
        }

        .school-identity img {
            flex: none;
            height: 72px;
            object-fit: contain;
            width: 72px;
        }

        .school-identity strong {
            color: #b91c1c;
            display: block;
            font-size: 26px;
            line-height: 1;
        }

        .school-identity span {
            display: block;
            font-size: 12px;
            line-height: 1.35;
            margin-top: 5px;
        }

        .document-header .document-value,
        .document-header .document-page {
            text-align: center;
        }

        .document-header .document-department {
            text-align: center;
        }

        .document-department strong {
            display: inline-block;
            margin-top: 4px;
        }

        .document-header .document-title {
            font-size: 17px;
            font-weight: 700;
            text-align: center;
        }

        .report-summary {
            align-items: center;
            display: flex;
            gap: 16px;
            justify-content: space-between;
            padding: 18px 24px;
        }

        .report-summary h2 {
            margin: 0;
        }

        .report-summary .figures {
            text-align: right;
        }

        .report-summary .figures strong {
            display: block;
            font-size: 22px;
        }

        @media (max-width: 650px) {
            .document-header th,
            .document-header td {
                font-size: 11px;
                padding: 6px;
            }

            .school-identity-content {
                gap: 5px;
            }

            .school-identity img {
                height: 42px;
                width: 42px;
            }

            .school-identity strong {
                font-size: 17px;
            }

            .school-identity span {
                font-size: 9px;
            }
        }

        .meta {
            border-top: 1px solid #e4e4e7;
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(3, 1fr);
            padding: 16px 24px;
        }

        .meta span {
            color: #71717a;
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .meta strong {
            display: block;
            font-size: 13px;
            margin-top: 4px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th {
            background: #f4f4f5;
            color: #52525b;
            font-size: 11px;
            padding: 10px 16px;
            text-align: left;
            text-transform: uppercase;
        }

        td {
            border-top: 1px solid #e4e4e7;
            font-size: 13px;
            padding: 12px 16px;
        }

        .empty {
            border-top: 1px solid #e4e4e7;
            color: #71717a;
            padding: 24px;
        }

        .group-title {
            border-top: 1px solid #e4e4e7;
            padding: 18px 24px 12px;
        }

        .group-title h2 {
            margin: 0;
        }

        .department-summary {
            border-top: 1px solid #e4e4e7;
        }

        .department-summary h2 {
            font-size: 14px;
            margin: 0;
            padding: 16px 24px 6px;
        }

        @media print {
            @page {
                margin: 12mm;
            }

            body {
                background: #ffffff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                margin: 0;
                max-width: none;
                padding: 0 2mm;
                width: 100%;
            }

            .paper {
                border: 0;
                border-radius: 0;
                break-after: page;
                margin-bottom: 0;
                page-break-after: always;
                overflow: visible;
                width: 100%;
            }

            .document-header,
            .report-summary,
            tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .paper:last-child {
                break-after: auto;
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>
    <header class="toolbar">
        <div>
            <h1>Attendance Print Sheet</h1>
            <p class="muted">{{ $groupLabel }}</p>
        </div>
        <div class="actions">
            @unless ($archiveMode ?? false)
                <a class="button" href="{{ route('records', $backFilters) }}">Back</a>
            @endunless
            <button class="button primary" type="button" onclick="window.print()">Print Attendance</button>
        </div>
    </header>

    <main class="sheet">
        <section class="paper">
            @include('records.attendance-document-header', [
                'yearBlock' => $groupedPrint ? 'All Year Levels / Blocks' : $filters['year_level'].' / Block '.$filters['block'],
                'department' => $groupedPrint ? 'All Departments' : $filters['course'],
                'eventTitle' => $eventTitle,
                'eventDate' => $eventDate,
                'pageNumber' => 1,
                'pageCount' => $pageCount,
            ])
            <div class="report-summary">
                <div>
                    <h2>{{ $groupLabel }}</h2>
                    <p class="muted">
                        {{ $eventDate }}
                        @if ($event)
                            | {{ $event->start_time }} - {{ $event->end_time }}
                        @elseif ($summary)
                            | {{ $summary['time'] }}
                        @endif
                    </p>
                </div>
                <div class="figures">
                    <strong>{{ $percentageLabel }}</strong>
                    <p class="muted">{{ $attendedCount }} of {{ $totalStudents }} attended</p>
                    <p class="muted">{{ $summary['scan_count'] ?? 0 }} scan record(s)</p>
                </div>
            </div>

            @if (! $groupedPrint)
                <div class="meta">
                    <div>
                        <span>Course</span>
                        <strong>{{ $filters['course'] ?: '-' }}</strong>
                    </div>
                    <div>
                        <span>Year Level</span>
                        <strong>{{ $filters['year_level'] ?: '-' }}</strong>
                    </div>
                    <div>
                        <span>Block</span>
                        <strong>{{ $filters['block'] ?: '-' }}</strong>
                    </div>
                </div>
            @endif

            @if ($sections->isEmpty())
                <div class="empty">
                    No attendance records found for the selected filters.
                </div>
            @elseif ($groupedPrint)
                <div class="department-summary">
                    <h2>Total Students</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Total Students</th>
                                <th>Attended</th>
                                <th>Average</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($departmentSummaries as $department)
                                <tr>
                                    <td><strong>{{ $department['course'] }}</strong></td>
                                    <td>{{ $department['total_students'] }}</td>
                                    <td>{{ $department['attended_count'] }}</td>
                                    <td>{{ $department['average_label'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @foreach ($sections as $section)
            <section class="paper">
                @include('records.attendance-document-header', [
                    'yearBlock' => $section['year_level'].' / Block '.$section['block'],
                    'department' => $section['course'],
                    'eventTitle' => $eventTitle,
                    'eventDate' => $eventDate,
                    'pageNumber' => $loop->iteration + 1,
                    'pageCount' => $pageCount,
                ])
                <div class="report-summary">
                    <div>
                        <h2>{{ $section['label'] }}</h2>
                        <p class="muted">
                            {{ $eventDate }}
                            @if ($event)
                                | {{ $event->start_time }} - {{ $event->end_time }}
                            @elseif ($summary)
                                | {{ $summary['time'] }}
                            @endif
                        </p>
                    </div>
                    <div class="figures">
                        <strong>{{ $section['percentage_label'] }}</strong>
                        <p class="muted">{{ $section['attended_count'] }} of {{ $section['total_students'] }} attended</p>
                        <p class="muted">{{ $section['scan_count'] }} scan record(s)</p>
                    </div>
                </div>

                <div class="meta">
                    <div>
                        <span>Course</span>
                        <strong>{{ $section['course'] }}</strong>
                    </div>
                    <div>
                        <span>Year Level</span>
                        <strong>{{ $section['year_level'] }}</strong>
                    </div>
                    <div>
                        <span>Block</span>
                        <strong>{{ $section['block'] }}</strong>
                    </div>
                    <div>
                        <span>Attended</span>
                        <strong>{{ $section['attended_count'] }} of {{ $section['total_students'] }}</strong>
                    </div>
                </div>

                @if (collect($section['attendees'])->isNotEmpty())
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student</th>
                                <th>Time-in</th>
                                <th>Status</th>
                                <th>Time-out</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section['attendees'] as $attendee)
                                <tr>
                                    <td><strong>{{ $attendee['student_id'] ?: 'Unassigned' }}</strong></td>
                                    <td>{{ $attendee['name'] }}</td>
                                    <td>{{ $attendee['time_in'] ?? 'Pending' }}</td>
                                    <td>{{ $attendee['attendance_status'] }}</td>
                                    <td>{{ $attendee['time_out'] ?? 'Pending' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty">
                        No attendance records found for this group.
                    </div>
                @endif
            </section>
        @endforeach
    </main>
</body>
</html>
