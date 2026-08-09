SESH - Session / Exam Resource Management System
SRFMS (Session Resource & Facility Management System) is a web-based application for managing exam halls, classrooms, and session bookings in an academic institution.
Features

Role-based access (Admin, Coordinator, Faculty)
Room management (add, edit, delete, status)
Booking management with conflict detection
Approval workflow for bookings
Dashboard for each role

Tech Stack

PHP (Native)
MySQL
HTML / CSS / JavaScript
XAMPP (recommended for local development)

Installation (XAMPP)

Place the project folder in:textC:\xampp\htdocs\sesh
Start Apache and MySQL from XAMPP Control Panel.
Open phpMyAdmin → http://localhost/phpmyadmin
Import the database:
Go to Import
Select database/schema.sql → Go
Then import database/seed.sql → Go

Open the application:texthttp://localhost/sesh

Default Login

























RoleEmailPasswordAdminadmin@sesh.com123456Coordinatorcoordinator@sesh.com123456Facultyfaculty@sesh.com123456
Change these passwords immediately after first login in production.
Project Structure
textsesh/
├── admin/              # Admin panel
├── coordinator/        # Coordinator panel
├── faculty/            # Faculty panel
├── modules/            # Shared booking & room modules
├── includes/           # Header, footer, auth, functions
├── assets/             # CSS & images
├── config/             # Database & app configuration
├── database/           # schema.sql & seed.sql
├── index.php
├── login.php
└── dashboard.php
Configuration
Database settings are located in:

config/config.php
config/database.php
config/db.php

Default connection (XAMPP):
PHPDB_HOST = 'localhost'
DB_NAME = 'sesh'
DB_USER = 'root'
DB_PASS = ''
Development Notes

Password hashing uses PHP password_hash() / password_verify()
Conflict checking is handled in modules/bookings/conflict_check.php
Test scripts (create_admin.php, test_*.php) are ignored by .gitignore

License
This project is developed for academic purposes.