# Pateros Technological College Academic Portal

Academic Portal is a PHP web application for academic records and grade management at Pateros Technological College. It provides separate experiences for administrators and registrars, professors, and students, backed by a shared MySQL/MariaDB database.

## Features

- **Admin / Registrar:** manage student and professor accounts, courses, sections, subjects, terms, and grading sheets; review requests; publish announcements; view reports and activity logs.
- **Professor:** view teaching assignments and rosters, enter grades, submit grading sheets, and request access to edit submitted grades.
- **Student:** view enrolled subjects and grades, manage profile settings, and submit document or grade-edit requests.
- **Shared:** role-based sign-in, notifications, announcement reactions and threaded comments, email verification, password reset, and account notifications.

See [SYSTEM_DIAGRAMS.md](SYSTEM_DIAGRAMS.md) for the architecture and database relationship diagrams, and [EMAIL_SETUP.md](EMAIL_SETUP.md) for local and production mail configuration.

## Technology

- PHP with PDO
- MySQL or MariaDB
- Composer and PHPMailer for email
- Vite and Tailwind CSS for building styles

## Requirements

- PHP with the PDO MySQL extension enabled
- MySQL or MariaDB
- Apache (for example, through XAMPP) or PHP's built-in development server
- Composer
- Node.js and npm (only needed to rebuild the CSS assets)

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

The checked-in assets are available for normal use. To rebuild them after changing the Tailwind source:

```powershell
npm install
npm run build
```

The Vite build writes generated styles to `dist/`.

## Database notes

- `core/db/schema.sql` is the schema baseline for a new database.
- `core/db/migrations/` contains incremental changes for existing databases. Review each migration and compare the target database schema before applying it, since older database states may differ.
- `grading_app-06-09-26.sql` is a database export, not the baseline setup script. Treat it as potentially containing data: use only when authorized, and never import it over a production or otherwise valuable database.

Back up the database before applying schema changes.
