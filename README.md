# Job Application Portal

A standalone PHP and MySQL recruitment workflow built for portfolio use. The project demonstrates a realistic hiring flow from public job discovery to admin-side candidate review, status updates, exports, and file handling.

## Highlights
- Public jobs page with multiple sample openings and a complete application form
- Admin login and job management dashboard
- Dedicated job editor for opening content, visibility, and messaging templates
- Application review page with CSV export and uploaded document preview
- Optional email actions for confirmation, acceptance, rejection, and application package forwarding
- Lightweight PHP setup with automatic schema creation and sample data seeding
- Reusable SQL dataset for demo-ready openings and applicant records

## Screenshots
- Public jobs page: `assets/screenshots/public-jobs-home.png`
- Application form section: `assets/screenshots/public-application-form.png`
- Admin jobs dashboard: `assets/screenshots/admin-jobs-dashboard.png`
- Admin application review: `assets/screenshots/admin-application-review.png`

## Project Structure
- `jobs.php`: public-facing jobs list and application flow
- `admin/login.php`: admin authentication page
- `admin/jobs.php`: admin dashboard for jobs and applicant lists
- `admin/job_opening.php`: dedicated job editor page
- `admin/job_application.php`: full applicant review page
- `database/sample_dataset.sql`: optional reset file for clean demo data
- `includes/jobs_portal.php`: domain logic, schema setup, exports, uploads, and email helpers

## Setup
1. Copy `config.local.example.php` to `config.local.php` and update the values.
2. Create the database named in `DB_NAME`.
3. Place the project in your PHP web root.
4. Open `jobs.php` for the public page.
5. Open `admin/login.php` for the admin area.

## Demo Credentials
- Admin email: set `PORTAL_ADMIN_EMAIL` in `config.local.php`
- Admin password: set `PORTAL_ADMIN_PASSWORD` in `config.local.php`
- Optional local demo shortcut: set `PORTAL_DEMO_AUTOLOGIN=1` in `config.local.php`

## Notes
- The first page load creates the required database tables automatically.
- Three sample job openings are seeded automatically for demo purposes.
- Import `database/sample_dataset.sql` if you want the same demo applicants shown in the screenshots.
- Outgoing mail is optional. Set `MAIL_ENABLED=1` only if local mail delivery is configured.
- Uploaded files are stored in `uploads/jobs/`.
- `config.local.php` is excluded from version control.

## Portfolio Angle
This project is useful in a GitHub portfolio because it showcases:
- CRUD workflow design
- admin/public route separation
- file uploads and document delivery
- status-based workflow management
- PHP/MySQL data modeling
- practical product thinking around hiring operations
