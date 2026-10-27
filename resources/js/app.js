import './bootstrap';
import jsQR from 'jsqr';

window.jsQR = jsQR;

document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.createElement('div');
    overlay.className = 'app-loading-overlay';
    overlay.innerHTML = '<div class="app-loading-card" role="status" aria-live="polite"><div class="app-loading-spinner" aria-hidden="true"></div><p id="app-loading-message">Loading…</p></div>';
    document.body.append(overlay);

    const showLoading = (message = 'Loading…') => {
        overlay.querySelector('#app-loading-message').textContent = message;
        overlay.classList.add('is-visible');
    };
    const hideLoading = () => overlay.classList.remove('is-visible');

    window.addEventListener('pageshow', hideLoading);

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented || event.target.dataset.loadingSkip !== undefined || event.target.id === 'student-import-form') return;
        showLoading(event.target.method.toLowerCase() === 'get' ? 'Loading results…' : 'Saving, please wait…');
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.hasAttribute('download') || link.dataset.loadingSkip !== undefined || link.target === '_blank') return;
        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin || !['http:', 'https:'].includes(destination.protocol)) return;
        if (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash) return;
        showLoading('Loading page…');
    });

    const form = document.getElementById('student-import-form');
    if (!form) return;

    const panel = document.getElementById('student-import-progress');
    const stage = document.getElementById('student-import-stage');
    const bar = document.getElementById('student-import-bar');
    const counts = document.getElementById('student-import-counts');
    const recent = document.getElementById('student-import-recent');
    const errorBox = document.getElementById('student-import-client-error');
    const button = document.getElementById('student-import-submit');
    const mailTestButton = document.getElementById('student-mail-test');
    const mailTestResult = document.getElementById('student-mail-test-result');
    let token = null;
    let running = false;

    if (mailTestButton && !mailTestButton.dataset.bound) {
        mailTestButton.dataset.bound = 'true';
        mailTestButton.addEventListener('click', async () => {
        mailTestButton.disabled = true;
        mailTestButton.textContent = 'Testing SMTP…';
        mailTestResult.className = 'text-sm text-zinc-600';
        mailTestResult.textContent = 'Checking connection and quota…';
        mailTestResult.classList.remove('hidden');

        try {
            const response = await fetch(form.dataset.mailStatusUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const data = await response.json().catch(() => ({}));
            const quota = data.quota?.available
                ? ` Remaining Brevo credits: ${data.quota.remaining}.`
                : ` ${data.quota?.message || 'Quota unavailable.'}`;
            mailTestResult.className = response.ok ? 'text-sm font-semibold text-emerald-700' : 'text-sm font-semibold text-rose-700';
            mailTestResult.textContent = `${data.message || 'SMTP test failed.'}${quota}`;
        } catch (error) {
            mailTestResult.className = 'text-sm font-semibold text-rose-700';
            mailTestResult.textContent = error.message || 'SMTP test failed.';
        } finally {
            mailTestButton.disabled = false;
            mailTestButton.textContent = 'Test SMTP connection';
        }
        });
    }

    form.querySelector('input[name="student_file"]').addEventListener('change', () => {
        if (!running) token = null;
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (running) return;
        running = true;
        button.disabled = true;
        button.textContent = 'Importing…';
        panel.classList.remove('hidden');
        errorBox.classList.add('hidden');
        stage.textContent = token ? 'Resuming import…' : 'Reading uploaded file…';

        const request = async (url, body) => {
            const response = await fetch(url, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || `Server error (${response.status}).`);
            return data;
        };

        try {
            if (!token) {
                const prepared = await request(form.dataset.prepareUrl, new FormData(form));
                token = prepared.token;
                bar.max = prepared.total;
                bar.value = 0;
                recent.replaceChildren();
            }

            while (true) {
                stage.textContent = 'Processing student records…';
                const body = new FormData();
                body.append('token', token);
                const progress = await request(form.dataset.progressUrl, body);
                bar.max = progress.total;
                bar.value = progress.processed;
                counts.textContent = `${progress.processed} of ${progress.total} rows processed · ${progress.imported} imported · ${progress.skipped} skipped · ${progress.mail_failures} mail failures`;
                for (const row of progress.recent) {
                    const item = document.createElement('li');
                    item.textContent = `Row ${row.row}: ${row.student_id || '(blank)'} — ${row.status}`;
                    recent.prepend(item);
                }
                while (recent.children.length > 12) recent.lastChild.remove();
                if (progress.complete) {
                    stage.textContent = 'Import complete. Loading results…';
                    if (progress.download) {
                        const download = document.createElement('a');
                        download.href = progress.download;
                        download.download = '';
                        document.body.append(download);
                        download.click();
                        download.remove();
                    }
                    showLoading('Loading imported students…');
                    window.location.assign(progress.redirect);
                    return;
                }
            }
        } catch (error) {
            stage.textContent = 'Import paused';
            errorBox.textContent = error.message;
            errorBox.classList.remove('hidden');
            button.textContent = token ? 'Retry import' : 'Import Records';
            button.disabled = false;
            running = false;
        }
    });
});
