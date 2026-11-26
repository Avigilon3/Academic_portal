# Pateros Technological College Academic Portal

Academic Portal is a PHP web application for academic records and grade management at Pateros Technological College. It provides separate experiences for administrators and registrars, professors, and students, backed by a shared MySQL/MariaDB database.

## Product scope and system boundaries

Academic Portal is a standalone, single-institution portal for academic records, grading, announcements, and Registrar-managed document requests. The MIS Office is responsible for entering and maintaining master data in the portal.

- **No external academic or payment integrations:** do not add automated LMS/SIS synchronization, third-party enrollment webhooks, payment gateways, or other integrations that make an external service a source of academic records.
- **Standard workflows:** use the institution-wide terms, sections, grade components, weights, status transitions, and grade scale. This is not a multi-tenant product and does not provide custom form builders or department-specific grading rules.
- **Online-only:** users need an internet connection to use the portal. Do not add offline-first mutations, service workers for offline submissions, or IndexedDB synchronization.
- **Responsive web application:** pages should remain usable on desktop and mobile browsers.
- **Email is operational, not academic-data synchronization:** SMTP is used for account verification, password recovery, and notifications. Keep credentials in environment configuration, not in source control.

## Roles and access

The intended access boundaries are:

| Role | Intended responsibilities |
| --- | --- |
| **MIS Staff** | Create, update, and maintain student and professor profiles, courses, subjects, curriculum/elective records, academic terms, sections, and system-wide announcements. |
| **Professor** | Access assigned sections and subjects; configure grade items within the standard grading structure; enter scores; save drafts; submit grades; and request Registrar review when a submitted or locked sheet needs correction. |
| **Registrar** | Review grade amendment/reopen requests; manage hard-copy Report of Grades requests and pickup schedules; and review or export academic reports. |
| **Student** | Read their own profile, enrollment, released grades, and announcements; download a digital Report of Grades; build a resume; view eligible E-Certificates; and request an official hard copy. |

Authentication and role checks are present in the application. In the current implementation, however, the shared administrator guard grants the same admin area access to the `admin`, `registrar`, `mis`, and `super_admin` role values. MIS and Registrar responsibilities are therefore not yet separated as strictly as the intended matrix above. Professor pages also check ownership of assigned sheets, and student pages resolve data from the signed-in student's profile; retain these ownership checks in any new endpoints.

## Current workflows

- **Master data and announcements:** the Admin area includes management pages for students, professors, courses, subjects, sections, terms, and announcements.
- **Grading:** professors can view assigned sheets and rosters, create or edit grade items, enter scores, save a draft, and submit a sheet. The sheet uses configured component weights and maps percentages to the grade equivalents defined by the current application scale.
- **Locking and corrections:** submitted or deadline-locked sheets are not generally editable by professors. A professor can submit an edit-access request, and the Admin/Registrar area can review the request and reopen a sheet.
- **Student grades and reports:** student pages display grades when the grading sheet is in the `locked` state. Students can download a client-rendered JPEG Report of Grades and submit document requests. The Admin/Registrar area can schedule pickup and mark a request released.
- **Student CV and E-Certificates:** the CV builder combines profile/course details, passed subjects from locked grading sheets, and student-entered resume sections. Subject E-Certificates are issued for a computed final grade of at least 75 and can be checked through the public verification page. The current schema has no student achievement/trophy-case table; course-completion certificates remain unavailable until the Registrar defines a completion rule.
- **Reporting and audit support:** the application has operational dashboards, CSV/PDF report paths, and activity-log pages. These should not be assumed to provide complete grade-distribution analytics or a before-and-after audit trail for every score change.
- **Shared account features:** role-aware sign-in, email verification, password recovery, notifications, and announcement interactions are included.

## Current limitations and implementation notes

- **Admin role separation is incomplete.** The current shared admin guard does not independently limit MIS and Registrar pages to their respective duties. Apply least-privilege checks at each route and on every mutation before relying on the intended matrix for production access.
- **Edit requests are reopen requests, not full amendments.** The existing professor request records a reason and asks for edit access. It does not record proposed grade changes or apply an approved patch with a per-grade decision history. Describe this workflow as a reopen/edit-access process until a structured amendment workflow is implemented.
- **Grade calculation logic is duplicated.** Weighted totals and grade-equivalent conversion appear in multiple PHP paths. Keep the grade scale institution-approved and verify that professor, student, and report calculations agree before using grades for official decisions.
- **Score-change audit coverage needs verification.** Activity logs exist, but the professor score-save path should not be presumed to record every individual change, its previous value, its new value, and its actor. Add and verify a grade-specific audit trail before treating the log as a complete audit history.
- **Analytics are limited.** Existing dashboards focus on operational counts and submissions. Grade distributions, pass/fail rates, and progress analysis by term/course should be treated as additional work unless they are explicitly implemented and validated.
- **Publication uses sheet status.** Student grade views currently depend on the grading sheet being `locked`. Confirm the Registrar release process and term deadlines when changing this rule; do not expose draft or merely submitted sheets.
- **Some browser assets are remotely hosted.** The Report of Grades image export loads `html2canvas` from a public CDN, and pages use remotely hosted fonts. Those assets require internet access; bundle them locally if external asset availability becomes unacceptable.
- **Database configuration has development defaults.** `core/db/connection.php` falls back to local MySQL credentials when environment variables are absent. Configure explicit credentials and restrict database access in deployed environments.
- **No offline guarantees:** unsaved form entries may be lost if a browser loses its connection or session. Save drafts while connected.

## Features

- **Admin / Registrar:** manage master records, grading-sheet configuration and deadlines, requests, announcements, reports, and activity logs.
- **Professor:** view assigned teaching sections and rosters, manage grade items, enter scores, save drafts, submit grading sheets, and request edit access.
- **Student:** view personal academic information and available grades, download a digital Report of Grades image, build and print a CV, view/download eligible E-Certificates, and request official documents.
- **Shared:** role-aware sign-in, notifications, announcement reactions and threaded comments, email verification, and password reset.

See [SYSTEM_DIAGRAMS.md](SYSTEM_DIAGRAMS.md) for the architecture and database relationship diagrams, and [EMAIL_SETUP.md](EMAIL_SETUP.md) for local and production mail configuration.

## Technology

- PHP with PDO
- MySQL or MariaDB
- Composer and PHPMailer for email
- Vite and Tailwind CSS for building styles; npm packages also bundle local QR and certificate-image rendering assets

## Requirements

- PHP with the PDO MySQL extension enabled
- MySQL or MariaDB
- Apache (for example, through XAMPP) or PHP's built-in development server
- Composer
- Node.js and npm (needed to rebuild the CSS and bundled certificate assets)

## Local setup

1. Place or clone this repository in your web server's document root. For XAMPP, that is typically `C:\xampp\htdocs\Academic_portal`.
2. Create a **new, empty** database named `grading_app` in MySQL/MariaDB.
3. Import [`core/db/schema.sql`](core/db/schema.sql) into that database using phpMyAdmin or a compatible SQL client. The schema file creates the tables but does not provide production accounts or institution directory data.
4. Configure the database connection. The app reads these environment variables; if they are omitted, the defaults are `127.0.0.1`, port `3306`, database `grading_app`, username `root`, and an empty password:

   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`

   For Apache, configure these in the web server's environment and restart Apache. For PHP's built-in server, set them in the same PowerShell session before starting it:

   ```powershell
   $env:DB_HOST = '127.0.0.1'
   $env:DB_PORT = '3306'
   $env:DB_NAME = 'grading_app'
   $env:DB_USER = 'root'
   $env:DB_PASS = ''
   ```

5. Install the PHP dependency:

   ```powershell
   composer install
   ```

6. Start the app using either:

   - **XAMPP:** start Apache and MySQL, then open `http://localhost/Academic_portal/login.php`.
   - **PHP development server:** from the repository root, run `php -S 127.0.0.1:8000`, then open `http://127.0.0.1:8000/login.php`.

   `welcome.php` is the welcome page; `login.php` opens the sign-in flow.

## Accounts and email

Registration looks up the submitted PTC email in the student/professor directory already stored in the database, then sends a verification code. A new local database therefore needs the institution's authorized directory records before registration will succeed. Administrators should be provisioned through the institution's approved process; no default credentials are included here.

Email uses SMTP. Configure the `SMTP_*` environment variables for the PHP web server, or follow [EMAIL_SETUP.md](EMAIL_SETUP.md) to use the loopback-only Mailpit development setup. Do not put production credentials in the repository or use Mailpit for production delivery.

## CSS development

The checked-in assets are available for normal use. Rebuild the Tailwind output after changing its source, or rebuild the local QR/image helpers after changing their source:

```powershell
npm install
npm run build
npm run build:certificate-assets
```

The Vite build writes generated styles to `dist/`.

## Database notes

- `core/db/schema.sql` is the schema baseline for a new database.
- `core/db/migrations/` contains earlier incremental changes for existing databases. Apply [`database/migrations/add_cv_and_ecertificates.sql`](database/migrations/add_cv_and_ecertificates.sql) after the baseline to enable the CV and E-Certificate modules; it references the existing `terms` table.
- Review migrations against the target database before applying them, since older database states may differ.
- `grading_app-06-09-26.sql` is a database export, not the baseline setup script. Treat it as potentially containing data: use only when authorized, and never import it over a production or otherwise valuable database.

Back up the database before applying schema changes.
