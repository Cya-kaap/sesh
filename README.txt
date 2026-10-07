SESH - Smart Educational Space Hub

Setup
1. Install and start Apache and MySQL from XAMPP.
2. Place this project at C:\xampp\htdocs\sesh\.
3. Import sql/sesh2.sql into MySQL. In phpMyAdmin, open the Import tab and select
	sql/sesh2.sql. From the MySQL CLI, run:

	mysql -u root -p < sql/sesh2.sql

4. Import sql/seed.sql after the schema. From the MySQL CLI, run:

	mysql -u root -p sesh2 < sql/seed.sql

5. Edit config/database.php with the local MySQL host, username, password, and
	database name. This project does not currently use a .env file; the mysqli
	connection is kept in that configuration file.
6. Open http://localhost/sesh/login.php.

First admin user
The supplied seed.sql creates admin@sesh.local. To set the known default
password reliably, run this SQL after importing seed.sql:

USE sesh2;
UPDATE user
SET password_hash = '$2y$12$p4IdZCilukAV0G.G6GHqoe2wlcs.8SZ8xi/FpYWHILWTZcgYjOlL6'
WHERE email = 'admin@sesh.local';

Default admin login:
Email: admin@sesh.local
Password: password

Coordinator and Faculty accounts are created by an administrator through
admin/user_form.php. Do not create those accounts by re-running seed.sql.

The application uses procedural PHP, mysqli prepared statements, sessions,
CSRF-protected POST forms, and redirect flash messages. Timetables are built
from approved booking rows and are not stored as a separate table.
