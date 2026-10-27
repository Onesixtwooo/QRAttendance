<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Attendance</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4">
            <div>
                <p class="text-xs font-semibold uppercase text-emerald-700">Student attendance monitoring system</p>
                <h1 class="text-xl font-bold">My Attendance</h1>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold">Logout</button>
            </form>
        </div>
    </header>
    <main class="mx-auto grid max-w-5xl gap-6 px-4 py-8 md:grid-cols-[320px_1fr]">
        <section class="h-fit rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="font-bold">{{ $student->first_name }} {{ $student->last_name }}</h2>
            <p class="text-sm text-zinc-500">{{ $student->student_id ?: 'Unassigned' }} · {{ $student->course }} · {{ $student->year_level }}</p>
            <div class="mt-5 rounded-lg bg-emerald-50 p-4 text-center">
                <p class="text-sm font-semibold text-emerald-800">My attendance QR code</p>
                <img id="my-qr-image" src="{{ $qrCode['image_url'] }}" alt="My attendance QR code" class="mx-auto mt-3 h-56 w-56 rounded bg-white p-2">
                <p id="my-qr-code" class="mt-2 font-mono text-sm font-bold tracking-widest text-emerald-900">{{ $qrCode['verification_code'] }}</p>
                <p class="mt-2 text-xs text-emerald-700">Refreshes every few seconds. Keep this page open and approve scans here.</p>
            </div>
            <div id="attendance-approval" class="mt-4 hidden rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-semibold text-amber-900">Confirm attendance</p>
                <p class="mt-1 text-xs text-amber-800">A booth scanned your QR code. Approve it only if you are physically present.</p>
                <button id="approve-attendance" type="button" class="mt-3 w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Approve scan</button>
                <p id="approval-message" class="mt-2 text-xs text-amber-800"></p>
            </div>
            @if (session('account_status'))
                <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">{{ session('account_status') }}</p>
            @endif
            @if (auth()->user()->force_password_change)
                <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">For your security, change your temporary password before continuing.</p>
            @endif
            <details class="mt-5 border-t border-zinc-200 pt-4">
                <summary class="cursor-pointer text-sm font-semibold text-zinc-700">Edit my account</summary>
                <form method="POST" action="{{ route('student.account.update') }}" class="mt-4 grid gap-3">
                    @csrf
                    @method('PATCH')
                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Email
                        <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal" required>
                    </label>
                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        New password @if (! auth()->user()->force_password_change)<span class="font-normal text-zinc-500">(leave blank to keep current)</span>@endif
                        <input type="password" name="password" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal" minlength="8" {{ auth()->user()->force_password_change ? 'required' : '' }}>
                    </label>
                    <label class="grid gap-1 text-sm font-semibold text-zinc-700">
                        Confirm new password
                        <input type="password" name="password_confirmation" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-normal" minlength="8">
                    </label>
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Save account changes</button>
                </form>
            </details>
        </section>
        <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold">Events I attended</h2>
            @forelse ($events as $event)
                <article class="mt-4 rounded-lg border border-zinc-200 p-4">
                    <h3 class="font-semibold">{{ $event['title'] }}</h3>
                    <p class="mt-1 text-sm text-zinc-600">{{ $event['date'] }}{{ $event['venue'] ? ' · '.$event['venue'] : '' }}</p>
                    <p class="mt-2 text-sm text-emerald-700">{{ $event['time_in'] ? 'Time in: '.$event['time_in'] : 'Recorded attendance' }}{{ $event['verified'] ? ' · Verified' : '' }}</p>
                </article>
            @empty
                <p class="mt-4 text-sm text-zinc-500">No attended events have been recorded yet.</p>
            @endforelse
        </section>
    </main>
    <script>
        let qrRefreshInFlight = false;

        async function refreshQr() {
            if (qrRefreshInFlight || document.visibilityState !== 'visible') return;
            qrRefreshInFlight = true;
            try {
                const response = await fetch(@json(route('student.qr')), { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) return;
                const code = await response.json();
                const nextImage = new Image();
                nextImage.onload = () => {
                    document.getElementById('my-qr-image').src = nextImage.src;
                    document.getElementById('my-qr-code').textContent = code.verification_code;
                };
                nextImage.src = code.image_url;
            } catch (error) { /* Keep the current QR and retry on the next refresh. */ }
            finally {
                qrRefreshInFlight = false;
            }
        }

        setInterval(refreshQr, 10000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refreshQr();
        });

        const approvalBox = document.getElementById('attendance-approval');
        const approveButton = document.getElementById('approve-attendance');
        const approvalMessage = document.getElementById('approval-message');
        let approvalToken = null;

        async function checkAttendanceApproval() {
            try {
                const response = await fetch(@json(route('student.attendance-approval')), { headers: { Accept: 'application/json' }, cache: 'no-store' });
                const data = await response.json();
                approvalToken = data.pending ? data.token : null;
                approvalBox.classList.toggle('hidden', !approvalToken);
            } catch (error) { /* Retry on the next poll. */ }
        }

        approveButton.addEventListener('click', async () => {
            if (!approvalToken) return;
            approveButton.disabled = true;
            approvalMessage.textContent = 'Approving…';
            try {
                const response = await fetch(@json(route('student.attendance-approval.confirm')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ token: approvalToken }),
                });
                const data = await response.json();
                approvalMessage.textContent = data.message ?? 'Approval failed.';
                if (response.ok) {
                    approvalToken = null;
                    approvalBox.classList.add('hidden');
                }
            } catch (error) {
                approvalMessage.textContent = 'Approval failed. Please try again.';
            } finally {
                approveButton.disabled = false;
            }
        });

        checkAttendanceApproval();
        setInterval(checkAttendanceApproval, 3000);
    </script>
</body>
</html>
