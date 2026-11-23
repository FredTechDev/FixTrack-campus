# FixTrack Campus

A full-stack **Campus Facilities & Maintenance Management System** built with plain HTML5, CSS3, JavaScript, PHP 8 and MySQL. Students report problems, administrators triage and assign them, and technicians carry out and document repairs — all through a single auditable workflow.

---

## 1. Features

### Students
- Register / sign in with a hashed password and PHP session.
- Submit reports with title, category, building, room, description, priority and up to 5 photos.
- Automatic duplicate-report warning while typing a title (AJAX).
- Unique tracking code for every report (e.g. `FTC-2026-48291`).
- Track progress through an 8-step visual workflow with a complete status history.
- Receive in-app notifications at every meaningful status change.
- Verify repairs with a star rating, or reopen an unsuccessful/closed issue with a reason.
- Search, filter, sort and export their own reports to CSV.

### Administrators
- Operations dashboard: totals, unassigned, active, overdue, resolved, awaiting closure.
- Review, approve or reject reports (rejection requires an explanation).
- Assign or reassign technicians, set priorities and response deadlines.
- Manage students, technicians, facilities and issue categories.
- Analytics dashboard: simple status counts, category bars, average resolution time and verification totals.
- Filter all reports and export the result to CSV.
- Printable QR codes for every facility that open a pre-filled report form.

### Technicians
- Personal dashboard with assigned, pending, active, completed and overdue job counts.
- Job queue with search and filters, sorted by priority and deadline.
- View issue descriptions, evidence and administrator instructions.
- Mark jobs as in progress, add repair notes, upload completion photos and submit for verification.
- See their own update history.

### Workflow
`REPORTED → UNDER REVIEW → APPROVED → ASSIGNED → IN PROGRESS → RESOLVED → VERIFIED → CLOSED`

Invalid reports can be **REJECTED** with a reason. Unsuccessful repairs can be **REOPENED** straight to `IN PROGRESS`. Every transition is written to `status_history`.

---

## 2. Technology stack

| Layer | Technology |
|---|---|
| Structure | HTML5 |
| Styling | CSS3 (custom design system, responsive grid) |
| Client logic | Vanilla JavaScript (no framework) |
| Server | PHP 8.3 (PDO, sessions, password hashing) |
| Database | MySQL 8.0 |
| Web server | Apache (via the official PHP Docker image) |

Vendored front-end assets (local, no CDN at runtime):
- Font Awesome 6.5.2 (`assets/vendor/fontawesome`) — icons.
- qrcodejs 1.0.0 (`assets/vendor/qrcode`) — QR rendering.

No React, Laravel, Node.js, Python or other frameworks are used.

---

## 3. Quick start (Docker — recommended)

Requirements: Docker and Docker Compose.

```bash
git clone https://github.com/FredTechDev/FixTrack-campus.git
cd FixTrack-campus
docker compose up -d --build
```

- Application: <http://localhost:8085>
- MySQL: `127.0.0.1:3307` (user `fixtrack_user`, password `fixtrack_password`, database `fixtrack_db`)
- The database is created and seeded automatically from `database.sql` on first boot.

To reset all data to the sample state:

```bash
docker compose down -v
docker compose up -d --build
```

> `-v` removes the database volume, so the init script runs again.

To view logs:

```bash
docker compose logs -f web
docker compose logs -f db
```

---

## 4. Manual installation (XAMPP / WAMP / LAMP)

1. Copy the project into your web server document root (e.g. `htdocs/fixtrack`).
2. Create a MySQL database named `fixtrack_db`.
3. Import the schema and sample data:
   ```bash
   mysql -u root -p fixtrack_db < database.sql
   ```
   or use phpMyAdmin → Import → `database.sql`.
4. Update credentials in `config/database.php` (or set environment variables):
   - `DB_HOST` (default `127.0.0.1`)
   - `DB_PORT` (default `3307`, Docker uses `3306` inside the network)
   - `DB_NAME` (default `fixtrack_db`)
   - `DB_USER` (default `fixtrack_user`)
   - `DB_PASS` (default `fixtrack_password`)
5. Make sure `uploads/`, `uploads/evidence/` and `uploads/completion/` are writable by the web server.
6. Apache: allow `.htaccess` overrides (`AllowOverride All`) so the uploads security policy applies.
7. Open `http://localhost/fixtrack/index.php` in a browser.

> The app assumes it is served from the site root. If you install it in a subfolder, adjust `BASE_URL` in `config/app.php`.

---

## 5. Demo accounts

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@fixtrack.campus` | `Admin@123` |
| Technician (electrical) | `tech.electrical@fixtrack.campus` | `Tech@123` |
| Student | `alice.student@fixtrack.campus` | `Student@123` |

You can also register a new student account from `register.php`.

---

## 6. Project structure

```
fixtrack-campus/
├── assets/
│   ├── css/
│   │   ├── style.css              # Core design system
│   │   └── pages.css              # Page-specific components
│   ├── js/
│   │   ├── app.js                 # Navigation, modals, dropzone, duplicate check
│   │   ├── validate.js            # Client-side form validation
│   │   └── qr.js                  # QR rendering
│   └── vendor/                    # Vendored Font Awesome + qrcodejs
├── actions/                       # POST-only form handlers (one per operation)
│   ├── report-create.php
│   ├── report-review.php
│   ├── report-assign.php
│   ├── report-priority.php
│   ├── report-close.php
│   ├── report-verify.php
│   ├── report-reopen.php
│   ├── technician-update.php
│   ├── user-save.php
│   ├── facility-save.php
│   ├── category-save.php
│   └── notification-read.php
├── admin/                         # Administrator pages (role protected)
├── api/duplicate-check.php        # AJAX duplicate suggestions (JSON)
├── config/
│   ├── app.php                    # Constants, workflow map, priorities, upload rules
│   └── database.php               # PDO connection factory
├── includes/
│   ├── auth.php                   # Sessions, RBAC, CSRF helpers
│   ├── bootstrap.php              # Shared bootstrap for actions
│   ├── functions.php              # Validation, helpers, notifications, audit trail
│   ├── header.php / footer.php    # Shared layout
├── qr/                            # QR landing/label pages
├── student/                       # Student pages (role protected)
├── technician/                    # Technician pages (role protected)
├── uploads/                       # Evidence & completion images (.htaccess blocks scripts)
├── database.sql                   # Full schema + seed data
├── export.php                     # Role-scoped CSV export
├── index.php / login.php / register.php / logout.php / dashboard.php
├── Dockerfile / docker-compose.yml
├── ARCHITECTURE.md                # Modularity, module map, request flows
├── TESTING.md                     # Test cases (success + failure)
└── README.md
```

---

## 7. Database schema

| Table | Purpose |
|---|---|
| `users` | All accounts with role, contact details, activation flag and hashed password |
| `facilities` | Campus buildings, codes, zones, floors |
| `categories` | The 8 issue categories with default priority/SLA |
| `reports` | Core tickets: tracking code, location, priority, status, deadline, timestamps |
| `report_images` | Evidence and completion photos linked to reports and uploader |
| `assignments` | Technician assignments, notes, status and completion time |
| `status_history` | Immutable audit trail of every status transition |
| `notifications` | Per-user alerts with read state |
| `repair_verifications` | Student satisfaction, rating and feedback |

Relationships use `InnoDB` foreign keys with `CASCADE` / `RESTRICT` / `SET NULL` as appropriate. Indexes cover tracking code, status, priority, dates, user/role and report history.

`database.sql` drops existing tables, recreates them and inserts **minimal** sample data: 3 users, 2 facilities, all 8 categories, 3 reports, 2 assignments, status history and notifications.

---

## 8. Security measures

- **Password hashing** with `password_hash()` / `password_verify()` (bcrypt).
- **Session authentication** with regenerated session IDs on login, `HttpOnly`, `SameSite=Lax`, and secure cookies on HTTPS.
- **Role-based access control** enforced on every page and every action endpoint (`require_role`).
- **CSRF protection** on all state-changing forms; tokens are compared with `hash_equals()`.
- **Prepared statements** (PDO, emulated prepares off) for all user-supplied SQL values.
- **Server-side validation** of every field; JavaScript validation is UX only and never trusted.
- **Output escaping** with `htmlspecialchars()` for all dynamic HTML output.
- **Upload validation**: real MIME detection with `finfo`, extension allow-list, 5 MB size limit, randomized filenames, and `uploads/.htaccess` that blocks script execution and directory listing.
- **Ownership checks**: students can only see their own reports; technicians only jobs assigned to them.
- **Restricted transitions**: the workflow map rejects illegal status jumps.
- **Formula-injection neutralisation** in CSV exports.
- **Login throttling** (session-based) to slow brute-force attempts.
- **Database errors are never echoed** to end users.

---

## 9. Usage walkthrough

1. Sign in as **Alice** (student) → **Report an issue** → fill in the form → submit. Note the tracking code.
2. Sign in as **admin** → **Reports** → open the new ticket → **Approve** → **Assign technician** (choose e.g. Samuel Sparks).
3. Sign in as that **technician** → **My Jobs** → open the job → **Mark as in progress** → add notes → **Submit for verification** (optionally attach completion photos).
4. Back as **Alice** → open the report → **Verify this repair** (rate it) or mark it unsatisfied to reopen.
5. As **admin**, close verified reports from the report page.
6. Explore **Analytics**, **People**, **Facilities**, **Categories**, **QR Codes** and **Export CSV** from the admin navigation.

---

## 10. QR-based reporting

`admin/qr-codes.php` renders a QR code for each active facility. Each code encodes an absolute URL such as:

```
http://localhost:8085/student/report-new.php?facility=3
```

`student/report-new.php` reads `facility` and `category` query parameters and pre-selects them. `qr/facility.php?id=N` produces a printable labelled poster, and `qr/report.php` is a public hub listing all facilities.

---

## 11. Testing

For how the code is split into modules and why, see **[ARCHITECTURE.md](ARCHITECTURE.md)**.

See **[TESTING.md](TESTING.md)** for a detailed matrix of successful and unsuccessful test cases, plus an automated smoke script in `tests/smoke_test.sh`.

---

## 12. License / attribution

Created as a full-stack web application project. Font Awesome is licensed under CC BY 4.0 (icons) / MIT (code); qrcodejs is MIT licensed. Both are vendored locally for offline operation.
