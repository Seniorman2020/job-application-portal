# Job Application Portal

Job Application Portal is a PHP and MySQL recruitment platform for publishing openings, collecting applications, and managing applicant review from an internal admin workspace.

It is designed around a practical hiring workflow: public job discovery, structured application intake, document uploads, protected admin access, applicant status management, and export-ready records.

## Overview

- Public jobs board with multiple openings
- Structured application form with validation and document upload handling
- Admin authentication and protected review workspace
- Job opening editor for creating and updating listings
- Applicant review flow with status updates and CSV export
- Optional email hooks for confirmations and review messaging

## Screenshots

### Public Jobs Page
![Public jobs page](assets/screenshots/public-jobs-home.png)

### Application Form
![Application form](assets/screenshots/public-application-form.png)

### Admin Dashboard
![Admin jobs dashboard](assets/screenshots/admin-jobs-dashboard.png)

### Applicant Review
![Admin application review](assets/screenshots/admin-application-review.png)

## Technology

- PHP
- MySQL / MariaDB
- HTML and inline CSS
- Font Awesome

## Project Structure

- `jobs.php` - public jobs listing and application flow
- `admin/login.php` - admin authentication page
- `admin/jobs.php` - job and applicant management dashboard
- `admin/job_opening.php` - job opening editor
- `admin/job_application.php` - individual applicant review page
- `database/sample_dataset.sql` - optional starter dataset
- `includes/jobs_portal.php` - schema setup, portal logic, exports, uploads, and helpers

## Setup

1. Copy `config.local.example.php` to `config.local.php`.
2. Update database and admin credentials in `config.local.php`.
3. Create the database named in `DB_NAME`.
4. Place the project inside your PHP web root.
5. Open `jobs.php` for the public interface.
6. Open `admin/login.php` for the admin workspace.

## Notes

- Database tables are created automatically on first load.
- Initial job openings are seeded automatically for a working first run.
- Import `database/sample_dataset.sql` if you want starter applicant records.
- `config.local.php` is excluded from version control.

## Contact

- Email: `cniorman6@gmail.com`
- Phone: `08164616531`

## License

This project is released under the MIT License by Seniorman Computers. See [LICENSE](LICENSE).
