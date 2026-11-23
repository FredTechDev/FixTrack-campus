# FixTrack Campus — Test Cases

This document records manual and automated test cases covering successful operations and expected failures. Run them against a fresh Docker stack (`docker compose down -v && docker compose up -d --build`).

An executable subset lives in [`tests/smoke_test.sh`](tests/smoke_test.sh).

---

## 1. Authentication

| # | Scenario | Steps | Expected result | Actual |
|---|---|---|---|---|
| A1 | Successful admin login | POST `login.php` with `admin@fixtrack.campus` / `Admin@123` | 302 → `dashboard.php` → 302 → `admin/dashboard.php`; welcome flash | PASS |
| A2 | Successful student login | `achieng.student@fixtrack.campus` / `Student@123` | Redirect to `student/dashboard.php` | PASS |
| A3 | Successful technician login | `tech.electrical@fixtrack.campus` / `Tech@123` | Redirect to `technician/dashboard.php` | PASS |
| A4 | Wrong password | Any valid email + wrong password | Error "Invalid email or password"; no session created | PASS |
| A5 | Unknown email | `nobody@example.co.ke` + any password | Same generic error (no user enumeration) | PASS |
| A6 | Inactive account | Admin sets a user inactive, then that user logs in | Error "This account has been deactivated" | PASS |
| A7 | Registration validation | `register.php` with short name, bad email, weak password, mismatched confirmation | All errors shown; no row inserted | PASS |
| A8 | Duplicate email registration | Register with an existing email | Error "already registered"; no row inserted | PASS |
| A9 | Successful registration | Unique email + strong password | Row inserted with bcrypt hash; redirect to login | PASS |
| A10 | Logout | `logout.php` | Session destroyed; protected pages redirect to login | PASS |
| A11 | Brute-force throttle | 8 failed logins within 5 minutes | "Too many failed sign-in attempts" | PASS |

---

## 2. Access control (RBAC)

| # | Scenario | Expected result | Actual |
|---|---|---|---|
| R1 | Guest requests `student/dashboard.php` | 302 → `login.php` | PASS |
| R2 | Student requests `admin/reports.php` | HTTP 403 "Access Denied" | PASS |
| R3 | Technician requests `admin/users.php` | HTTP 403 | PASS |
| R4 | Student POSTs to `actions/report-review.php` | HTTP 403 | PASS |
| R5 | Student opens another student's report | 302 → `student/reports.php`, error flash | PASS |
| R6 | Technician opens a job assigned to someone else | 302 → `technician/jobs.php`, error flash | PASS |
| R7 | Any state-changing POST without CSRF token | HTTP 403 "Invalid or missing security token" | PASS |
| R8 | POST with a forged CSRF token | HTTP 403 | PASS |

---

## 3. Reporting

| # | Scenario | Steps | Expected result | Actual |
|---|---|---|---|---|
| C1 | Successful report submission | Fill all required fields, attach a PNG | 302 to report view; unique `FTC-YYYY-NNNNN` code; status `REPORTED`; `status_history` entry; admin notification | PASS |
| C2 | Short title | Title under 5 chars | Error; no insert | PASS |
| C3 | Short description | Description under 10 chars | Error; no insert | PASS |
| C4 | Invalid category / facility ID | POST `category_id=99999` | Error "Select a valid issue category" | PASS |
| C5 | Invalid priority | `priority=nonsense` | Error "Select a valid priority level" | PASS |
| C6 | More than 5 photos | Attach 6 files | Error "maximum of 5" | PASS |
| C7 | Disallowed file type | Upload a `.txt` renamed `.png` | `finfo` rejects it; warning shown; report still saved without that image | PASS |
| C8 | Oversized file | Upload >5 MB | Rejected with size warning | PASS |
| C9 | Script in uploads | Request `uploads/evil.php` | HTTP 403 (no execution) | PASS |
| C10 | Duplicate warning | Type an existing title prefix | AJAX suggests matching tracking codes | PASS |
| C11 | QR prefill | Open `student/report-new.php?facility=3` | Facility select pre-selected | PASS |
| C12 | XSS attempt | Title `<script>alert(1)</script> ...` | Stored raw in DB but rendered escaped (`&lt;script&gt;`); no script execution | PASS |

---

## 4. Workflow

| # | Scenario | From → To | Expected result | Actual |
|---|---|---|---|---|
| W1 | Admin approves | REPORTED → APPROVED | Deadline set from priority; status history + student notification | PASS |
| W2 | Admin moves under review | REPORTED → UNDER REVIEW | Status updated | PASS |
| W3 | Admin rejects | UNDER REVIEW → REJECTED | Rejection reason stored; student notified; reason displayed | PASS |
| W4 | Reject without reason | Any → REJECTED with blank notes | Error "rejection explanation ... required" | PASS |
| W5 | Assign technician | APPROVED → ASSIGNED | Assignment row created; deadline set; tech + student notified | PASS |
| W6 | Invalid technician ID | Assign `technician_id=99999` | Error "Select a valid, active technician" | PASS |
| W7 | Start job | ASSIGNED → IN PROGRESS | Assignment status `in_progress`; student notified | PASS |
| W8 | Resolve without notes | IN PROGRESS → RESOLVED with short notes | Error "repair notes of at least 10 characters" | PASS |
| W9 | Submit repair | IN PROGRESS → RESOLVED | Completion photos linked; assignment completed; student verification notification | PASS |
| W10 | Verify satisfied | RESOLVED → VERIFIED | Verification row with rating; admin notified | PASS |
| W11 | Verify unsatisfied | RESOLVED → IN PROGRESS | Verification row `is_satisfied=0`; feedback required; tech + admin notified | PASS |
| W12 | Close | VERIFIED → CLOSED | `closed_at` set; student notified | PASS |
| W13 | Reopen closed | CLOSED → IN PROGRESS | Reason required; timestamps cleared; notifications sent | PASS |
| W14 | Illegal transition | Try CLOSED → APPROVED | Error "That status change is not allowed" | PASS |
| W15 | Full lifecycle | Report → approve → assign → start → resolve → verify → close → reopen | Every transition present in `status_history` in order | PASS |

---

## 5. Dashboards, analytics and export

| # | Scenario | Expected result | Actual |
|---|---|---|---|
| D1 | Student dashboard counters | Totals match DB queries | PASS |
| D2 | Admin dashboard counters | Totals, unassigned, active, overdue, resolved, awaiting-close match | PASS |
| D3 | Technician dashboard counters | Assigned/pending/active/completed match assignments | PASS |
| D4 | Analytics counts + bars | Render from live aggregates; no PHP errors | PASS |
| D5 | Search/filter reports | Status/category/priority/facility/technician/keyword filters combine correctly | PASS |
| D6 | Overdue-only filter | Returns only active reports past deadline | PASS |
| D7 | Pagination | Page links preserve filters | PASS |
| D8 | Admin CSV export | Downloads all matching rows with headers | PASS |
| D9 | Student CSV export | Contains only the student's own reports | PASS |
| D10 | CSV formula injection | Value starting with `=` is prefixed with `'` | PASS |
| D11 | SQL injection in search (`' OR 1=1--`) | 200, treated as literal string, no extra rows | PASS |

---

## 6. Administration CRUD

| # | Scenario | Expected result | Actual |
|---|---|---|---|
| M1 | Create technician | Account created with bcrypt hash, role `technician` | PASS |
| M2 | Edit user without password | Hash unchanged | PASS |
| M3 | Duplicate email | Error; no update | PASS |
| M4 | Deactivate own account | Error "cannot deactivate your own account" | PASS |
| M5 | Create facility with duplicate code | Error "building code is already in use" | PASS |
| M6 | Create category with invalid slug | Error; no insert | PASS |
| M7 | QR label page | Shows facility QR and direct report link | PASS |

---

## 6b. Guest reporting (no account)

| # | Scenario | Expected result | Actual |
|---|---|---|---|
| G1 | Guest opens `guest-report.php` | 200, no login redirect | PASS |
| G2 | Submit with name, title, category, facility, room, description | 302 to receipt, tracking code + private 48-char token | PASS |
| G3 | Bad token in `track.php` | No report data shown | PASS |
| G4 | Guest tracking page at `RESOLVED` | Verification form visible and works | PASS |
| G5 | Guest verifies unsatisfied | Status returns to `IN PROGRESS`, technician + admins notified | PASS |
| G6 | Guest reopens closed issue | Reason required, status `IN PROGRESS`, history records it | PASS |
| G7 | Guest report in admin list / export | Shown with "Guest" badge; CSV `Is Guest = yes`; no PHP warnings | PASS |
| G8 | Staff actions on guest report | No notification attempted for `student_id = NULL` | PASS |
| G9 | Register with the guest email | Guest reports linked to new account (`is_guest=0`, token cleared); old private link invalid; reports appear on student dashboard | PASS |

---

## 7. Automated smoke test

With the Docker stack running:

```bash
chmod +x tests/smoke_test.sh
bash tests/smoke_test.sh
```

The script logs in as all three roles, loads every protected page, verifies HTTP status and checks for PHP error output. It exits non-zero if any check fails.

---

## 8. Notes on validation approach

- Client-side validation (`assets/js/validate.js`, HTML5 attributes) is used for immediate feedback only.
- Every action endpoint re-validates all input server-side, re-checks the CSRF token and re-verifies role/ownership before touching the database.
- All SQL that includes user input uses PDO prepared statements; numeric IDs are cast to `int`.
- All dynamic output passes through `e()` (`htmlspecialchars`).
