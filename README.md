# SAMSqr

SAMSqr is a web-based Student Attendance Monitoring System that records event attendance through student QR codes. Administrators manage students, scheduled events, booths, and attendance records, while booth accounts scan students during an event that is currently marked **In Progress**.

## Features

- Administrator and booth user roles
- Student profile management
- CSV and XLSX student imports
- Search and query-based student filtering
- Individual and filtered batch QR printing
- Event scheduling and status management
- Time-in and verification scanning phases
- Controls for opening or closing time-in scanning
- Booth assignment and online heartbeat monitoring
- Attendance record filtering, editing, deletion, and printing
- Mobile-friendly QR scanner with manual student ID fallback

## Technology

- PHP 8.2 or newer
- Laravel 12
- SQLite by default
- Vite 7
- Tailwind CSS 4
- JavaScript browser camera and Barcode Detector APIs

## Requirements

Install the following before setting up the project:

- PHP 8.2+ with the extensions required by Laravel
- Composer
- Node.js and npm
- SQLite, or another Laravel-supported database

The XLSX import feature also requires PHP's `zip` and SimpleXML extensions.

## Local installation

Clone or open the project directory, then run:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Create the SQLite database if it does not exist:

```powershell
New-Item database/database.sqlite -ItemType File -ErrorAction SilentlyContinue
```

Install the database schema and seed the administrator:

```powershell
php artisan migrate --seed
```

Install frontend dependencies:

```powershell
npm install
```

## Seeded administrator

The database seeder creates this development account:

```text
Email: admin@example.com
Password: password
```

Change these credentials before using the system in a real environment.

## Running locally

The easiest local development command is:

```powershell
composer run dev
```

This starts Laravel, the queue listener, application logs, and Vite. Open:

```text
http://127.0.0.1:8000
```

Alternatively, run Laravel and Vite in separate terminals:

```powershell
php artisan serve
```

```powershell
npm run dev
```

## Running over a LAN

Find the host computer's Wi-Fi or Ethernet IPv4 address:

```powershell
ipconfig
```

For example, if the address is `192.168.100.134`, start Laravel with:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

In a second terminal, set the Vite LAN host and start Vite:

```powershell
$env:VITE_LAN_HOST="192.168.100.134"
npm run dev
```

Other computers on the same network can then open:

```text
http://192.168.100.134:8000
```

Keep both terminals running. If another device cannot connect, allow PHP and Node.js through Windows Firewall and confirm that both devices are connected to the same network.

The Vite host must match the current computer address. If CSS is requested from an old address, stop Vite, update `VITE_LAN_HOST`, and start it again.

## Mobile camera requirement

Browsers normally require a trusted HTTPS connection before exposing camera and barcode APIs to a web application. A page opened from a plain LAN address such as `http://192.168.100.134:8000` may load correctly while Android Chrome still blocks QR camera scanning.

For reliable mobile scanning, serve the application through trusted HTTPS. Manual student ID entry remains available when camera scanning is unsupported.

## Attendance workflow

1. Sign in as an administrator.
2. Add or import student records.
3. Generate and print student QR codes from a filtered student query.
4. Create an event schedule.
5. Set the event status to **In Progress**.
6. Open time-in scanning and keep the scan phase set to **Time-in**.
7. Sign in at a booth and scan each student's QR code.
8. When required, activate the **Verification** phase and scan students again.
9. Review or print results from Attendance Records.

Booth scanning is disabled unless an event dated today has the **In Progress** status. Scheduled or upcoming events do not accept scans.

## Student filtering

The Students page intentionally does not load every student on its initial request. Use one or more of these fields and click **Apply**:

- Search text
- Course
- Block
- Year level

Student rows and batch QR printing are generated only from the submitted query. This avoids loading the complete student dataset at once.

## Student import format

Imports accept CSV, TXT, and XLSX files up to 5 MB. The first row must contain these headers:

```text
student_id,last_name,first_name,course,block,year_level
```

Example:

```csv
student_id,last_name,first_name,course,block,year_level
STU-26001,Santos,Mika,Information Technology,A,1st Year
STU-26002,Ramos,Noah,Computer Science,B,2nd Year
```

Existing students with the same student ID are updated. Invalid rows are skipped and reported after the import.

## Event scanning rules

- Only an event dated today and marked **In Progress** is selected by the scanner.
- A student can receive only one time-in record per event.
- Verification requires an existing time-in record.
- A student can receive only one verification record per event.
- Time-in scans can be closed independently by an administrator.
- Scan-phase and time-in controls appear only for events that are **In Progress**.

## Testing

Run the complete test suite:

```powershell
php artisan test
```

Run a specific feature suite:

```powershell
php artisan test --filter=EventScheduleTest
php artisan test --filter=ScannerTest
php artisan test --filter=StudentPageTest
```

## Production notes

Before deployment:

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Set a correct HTTPS `APP_URL`.
- Use a trusted TLS certificate.
- Change the seeded administrator password.
- Configure a production database and backups.
- Run `npm run build` instead of the Vite development server.
- Protect access to student and attendance information according to applicable privacy requirements.
- Configure a production web server such as Apache or Nginx instead of `php artisan serve`.

## License

This project is built with Laravel, which is released under the MIT License.
