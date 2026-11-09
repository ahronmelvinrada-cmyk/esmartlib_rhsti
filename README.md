# eSmartLib RHSTI

eSmartLib is a PHP and MySQL library management system for RHSTI. Staff can
manage the catalog and students, record book loans and returns with QR codes,
and record student attendance. Student accounts can view their library
information and request books.

## Repository contents

- `sourcecode/` — PHP application, API, PWA manifest/service worker, and PHP
  configuration.
- `assets/` — application JavaScript, CSS, icons, and school logo.
- `database/database.sql` — schema-only MySQL/MariaDB initialization script.
- `uploads/` — upload protection rules and the location for runtime uploads.
- `application/` — Android application package.
- `tests/check.sh` — PHP syntax and repository-resource checks.
- `sourcecode/Dockerfile` and `docker-compose.yml` — local container deployment.
- `.env.example` and `sourcecode/hosting-config.example.php` — configuration
  templates. Do not put real credentials in either template.

The repository does not include deployable staff credentials or production
student data. Create a fresh database and staff account for each installation.

## Application structure

- The PHP pages in `sourcecode/` implement staff and student workflows; `api.php`
  provides the authenticated POST actions `student`, `borrow`, `return`, and
  `attendance` used by the QR scanning interface.
- `assets/app.js`, `assets/messenger.js`, and `assets/style.css` implement the
  browser interface and client-side scanning behavior.
- `database/database.sql` defines the MySQL/MariaDB schema. There is no separate
  API service or AI/ML model in this version of the application.
- QR scanning, QR generation, charts, and fonts use third-party browser
  libraries loaded from CDNs; there are no Composer or npm packages to install.

## Requirements

### Container setup

- Docker Engine with the Docker Compose plugin.
- An available host port `8080` (or change the published port in
  `docker-compose.yml`).

### Manual setup

- PHP 8.2 or newer with PDO MySQL (`pdo_mysql`) enabled.
- MySQL 8 or MariaDB 10.4 or newer.
- Apache with PHP support, or PHP's development server for local testing.
- A modern browser. Camera-based QR scanning requires HTTPS or `localhost`.
- Internet access for the QR scanner, QR-code generation, chart, and font
  libraries currently loaded from CDNs.

There is no Composer or npm dependency manifest; the application uses PHP
extensions and browser libraries referenced by its pages.

## Quick start with Docker Compose

1. Copy `.env.example` to `.env` and set unique local database passwords:

   ```sh
   cp .env.example .env
   ```

2. Build and start the application and database:

   ```sh
   docker compose up --build -d
   ```

   The database initialization script creates an empty schema on the first
   start. MySQL stores its data in the `db_data` volume.

3. Create an administrator account as described in
   [Creating the first staff account](#creating-the-first-staff-account).

4. Open <http://localhost:8080/>.

To stop the services without deleting database contents:

```sh
docker compose down
```

To remove the local database volume and all data in it, use
`docker compose down -v`. This is destructive.

## Manual installation

1. Copy the contents of `sourcecode/` into the web document root, then copy
   the repository's `assets/` and `uploads/` directories into that same
   document root. PHP files, `assets/`, and `uploads/` must be siblings. The
   included Docker configuration demonstrates the required layout.
2. Create an empty MySQL/MariaDB database and a dedicated database user. Grant
   that user access only to the application database.
3. Import `database/database.sql` into the database. The script creates the
   tables and indexes; it does not create the database or add sample accounts.
4. Configure the database using either:
   - Environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`; or
   - A private `hosting-config.php` in the document root, copied from
     `sourcecode/hosting-config.example.php` and populated with the database
     values.
5. Ensure the web server can write to `uploads/students/` and
   `uploads/library/` in the document root. Keep PHP execution disabled in upload
   directories.
6. Create an administrator account, then open `login.php`.

For local XAMPP without explicit configuration, the application retains its
legacy defaults: database `database`, user `root`, and an empty password.
Prefer a dedicated database user outside a disposable local environment.

## Creating the first staff account

The application intentionally does not seed default administrator or librarian
passwords. Generate a password hash with PHP:

```sh
php -r 'echo password_hash("Replace-With-A-Unique-Password", PASSWORD_DEFAULT), PHP_EOL;'
```

Use the resulting hash in this one-time SQL statement, replacing the username
and hash with your own values:

```sql
INSERT INTO users (username, password, role, must_change)
VALUES ('admin', '<PASTE_GENERATED_HASH>', 'admin', 0);
```

Sign in and create additional staff accounts through the application's account
management page. Never use a real password in shell history or commit the hash
to the repository.

## Configuration

| Variable | Purpose |
| --- | --- |
| `DB_HOST` | MySQL/MariaDB server hostname |
| `DB_NAME` | Application database |
| `DB_USER` | Database username |
| `DB_PASS` | Database password |
| `MYSQL_DATABASE` | Database created by the Compose database container |
| `MYSQL_USER` | Application database user created by Compose |
| `MYSQL_PASSWORD` | Password for the Compose application database user |
| `MYSQL_ROOT_PASSWORD` | Root password for the Compose database container |

Compose reads `.env` automatically. For a PHP deployment outside Compose, set
the four `DB_*` environment variables in the PHP process environment or use the
untracked `hosting-config.php` file in the document root. Do not upload
`.env.example` or a populated template as though it were a secret store.

## Database and privacy

`database/database.sql` contains schema only and is suitable for a new
installation. It does not contain real students, account hashes, loans, or
attendance records. Backups of a live database are sensitive; store them
outside source control with access controls and encryption appropriate to the
data.

The PHP application may create a small number of supplemental tables or columns
on first use. The database account therefore needs schema-alteration privileges
during initial setup. Review privileges and deployment requirements before
restricting them further.

## QR formats and operation

- Student QR content: `STU:<student_no>`
- Book QR content: `BK:<accession_no>`
- A USB scanner can be used as a keyboard and submits the focused scan field.
- Camera scanning requires a secure browser context (`https://` or
  `localhost`) and camera permission.
- QR, chart, and font libraries are loaded from third-party CDNs, so those
  features require network access.

## Testing

Run the repository checks from its root:

```sh
bash tests/check.sh
```

This checks PHP syntax and required repository resources. For a running
installation, also verify staff login, student registration, catalog
management, borrowing, return, attendance, and the student request flow against
a non-production database.

## Deployment notes

- Serve production traffic over HTTPS; the camera feature requires it, and
  authentication cookies should only be used over TLS.
- Keep PHP errors out of browser responses on public hosts and review server
  logs for operational errors.
- Keep the private database configuration and live database backups out of the
  web root and source control.
- Back up the database and uploaded files separately, and test restoring them.
- Rotate credentials if they were ever committed or shared. Removing a
  credential from the current checkout does not remove it from Git history.
