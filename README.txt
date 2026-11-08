SETUP (XAMPP)
1. Copy this folder to C:\xampp\htdocs\esmartlib (rename if you like).
2. Start Apache + MySQL. In phpMyAdmin, create/select the `database` database and import database.sql.
3. The local XAMPP defaults are host `localhost`, database `database`, user `root`, and no password. For InfinityFree, copy `hosting-config.example.php` to `hosting-config.php`, enter the exact MySQL hostname, database name, username, and password from the hosting control panel, then upload `hosting-config.php` to the same `htdocs` folder as `config.php`. Do not share or commit this file. Import database.sql into the hosting database using the hosting phpMyAdmin; the SQL file creates tables but does not create or name the hosting database. Environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` are also supported.
4. Open http://localhost/esmartlib/  -> login admin/admin123 or librarian/lib123 (auto-created on first visit; change them).
5. Add students/books, print QR codes, then use Borrow / Return / Attendance pages.
QR formats: students "STU:<student_no>", books "BK:<accession_no>".
USB barcode/QR scanners work as keyboards: keep the scan box focused. Phone/laptop camera also supported (camera needs localhost or HTTPS).
QR libraries load from CDN (internet needed on first use).

PASSWORD RECOVERY
- Users can submit a reset request from the Forgot password link on the sign-in page; no Gmail or outgoing mail setup is required.
- An administrator signs in and opens Account & Requests > Password resets, confirms the user's identity, then generates a six-digit code. Give the code directly to that user; it is displayed once and expires after 10 minutes.
- The user enters the code and chooses a new password on the Forgot password page. Codes expire after 10 minutes and are invalidated after five incorrect attempts.

ONLINE BOOK BOOKINGS
- Students can request currently unreserved books from My Dashboard and include a message to the admin. Check request status and admin replies in the same section.
- Admins can open Account & Requests > Book requests, reply, and approve or decline each request. Approved bookings hold a copy until the student borrows it using the usual student/book QR scanning workflow; the student can also cancel to release the hold.
- Existing databases are updated automatically when a student dashboard, Books page, or Book requests page is opened.

ACCOUNT AND CHAT
- Admins can manage their account, system settings, reset-code requests, and book requests in the tabs on the Account & Requests page. The old Settings, Password resets, and Book requests links redirect to the matching tab.
- Use Chat > New chat to search by student name, student ID, or username and start private conversations with another active student, librarian, or admin. New messages are checked while the chat panel is open.
- Admins see a red dot on Account & Requests when reset-code or book requests are waiting. The chat button also indicates unread messages.
- For InfinityFree updates, upload config.php and messages.php beside student.php, and upload assets/messenger.js into the existing assets folder. Do not overwrite hosting-config.php unless intentionally updating database credentials.
- Existing databases try to create the private-chat tables automatically when an authenticated page is opened. If the hosting database user cannot create tables, import migration_chat.sql into that database using phpMyAdmin.

INSTALLABLE APP
- On Android Chrome, use the browser menu and choose "Install app" or "Add to Home screen" when available.
- On iPhone/iPad, open the site in Safari, tap Share, then "Add to Home Screen".

STUDENT DASHBOARD
- Students who register on login.php create their own password and sign in with their Student ID and that password.
- Students added by library staff can sign in with their Student ID as the initial password.
- Students can change their password later from their dashboard.
- Upgrading an old database? Import migration_student_dashboard.sql first.
