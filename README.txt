# eSmartlib RHSTI – QR Code Extension

A library system that uses QR codes for borrowing, returning, and student time-in/time-out.

## Features

- **Borrow** – scan the student QR, then each book QR. The loan is saved with a due date.
- **Return** – scan the book QR. The return date/time is saved and the book becomes available again.
- **Attendance** – first scan is Time-In, next scan is Time-Out.
- **Dashboard, history, reports** – view saved records.
- **Roles** – admin and librarian logins.

## Tech Stack

HTML, CSS, JavaScript • PHP • MySQL • QR libraries (loaded from CDN)

## Setup (XAMPP)

1. Copy this folder to `C:\xampp\htdocs\esmartlib`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Open phpMyAdmin, create a database named `database`, then import `database.sql`.
4. Open http://localhost/esmartlib/ and log in:
   - `admin` / `admin123`
   - `librarian` / `lib123`

   These accounts are created automatically on first visit. **Change the passwords.**
5. Add students and books, print their QR codes, then use the Borrow, Return, and Attendance pages.

### Database settings

Local XAMPP defaults: host `localhost`, database `database`, user `root`, no password.

### Online hosting (InfinityFree)

1. Copy `hosting-config.example.php` to `hosting-config.php`.
2. Enter the MySQL hostname, database name, username, and password from your hosting control panel.
3. Upload `hosting-config.php` to the same `htdocs` folder as `config.php`.
4. Import `database.sql` using the hosting phpMyAdmin. (The file creates tables only; it does not create the database.)

Do not share or commit `hosting-config.php`. You can also use the environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`.

## QR Code Formats

- Students: `STU:<student_no>`
- Books: `BK:<accession_no>`

## Scanning

- **USB scanner:** it works like a keyboard. Keep the scan box selected.
- **Camera:** works on phone or laptop, but needs `localhost` or HTTPS.
- The QR libraries load from the internet (CDN), so you need a connection on first use.

## Project Team

13 members – see the project documentation for each member's role and contribution.
