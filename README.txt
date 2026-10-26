SETUP (XAMPP)
1. Copy this folder to C:\xampp\htdocs\esmartlib (rename if you like).
2. Start Apache + MySQL. In phpMyAdmin, import database.sql.
3. Edit DB credentials in config.php if needed (default root / no password).
4. Open http://localhost/esmartlib/  -> login admin/admin123 or librarian/lib123 (auto-created on first visit; change them).
5. Add students/books, print QR codes, then use Borrow / Return / Attendance pages.
QR formats: students "STU:<student_no>", books "BK:<accession_no>".
USB barcode/QR scanners work as keyboards: keep the scan box focused. Phone/laptop camera also supported (camera needs localhost or HTTPS).
QR libraries load from CDN (internet needed on first use).

STUDENT DASHBOARD
- Students log in at login.php with their Student ID; first-time password = Student ID (account is auto-created). They can change it on their dashboard.
- Upgrading an old database? Import migration_student_dashboard.sql first.
