# Architecture & Modularity

FixTrack Campus is modular by **folder and responsibility**, not by framework. Every piece has one job and dependencies point in one direction:

```
config/  →  includes/  →  pages + actions/  →  uploads/
                 ↑
              database.sql (MySQL)
```

`config/` knows nothing about the app. `includes/` knows about config and the DB. Pages and actions know about includes. Nothing underneath imports something above it.

---

## Module map

| Module | Owns | Never does |
|---|---|---|
| `config/app.php` | Constants: roles, statuses, workflow order, priorities, upload rules, paths | Query the DB, output HTML |
| `config/database.php` | One PDO connection (`get_db()`) | Run business logic |
| `includes/auth.php` | Session, login/logout, `require_role()`, CSRF | Touch reports or other app data |
| `includes/functions.php` | Escaping, validation helpers, queries, history, notifications, badges | Render whole pages |
| `includes/bootstrap.php` | One-line setup for action endpoints | Anything else |
| `includes/header.php` / `footer.php` | Shared layout, role-aware nav, flash messages, assets | Business logic |
| `student/`, `admin/`, `technician/` | **Read** data and render a page | Mutate data directly |
| `guest-report.php`, `guest-receipt.php`, `track.php` | Public read/render for account-less reporters | Trust a tracking code alone (token required) |
| `actions/` | **Write** data, then redirect | Render HTML |
| `api/duplicate-check.php` | One JSON read endpoint | Mutate anything |
| `assets/` | CSS + vanilla JS only | Know PHP |
| `uploads/` | Image files + `.htaccess` guard | Execute scripts |
| `database.sql` | Schema + minimal seed | Application logic |

---

## The two request paths

**Viewing a page**
`page → require_role() → SELECT with prepared statement → header → HTML → footer`

**Changing data**
`form POST → action → require_role() → require_post() (POST + CSRF) → validate → beginTransaction → UPDATE/INSERT + history + notifications → commit → set_flash → redirect`

The redirect is the important part: after a write, the browser always lands on a GET page. Refreshing cannot resubmit. This is the whole state-change pattern — every action follows it.

---

## Three rules that keep it modular

1. **Pages read, actions write.** If you add a button that changes data, its `action` points at a file in `actions/`, never back at the page.
2. **Every guard runs twice.** Role check on the page *and* on the action. Ownership check (`student_id` / `technician_id`) on reads *and* on writes. A guessed URL id must never expose or modify another user's data.
3. **One write = three rows.** A status change writes the report's new status, one `status_history` row, and notifications. Helpers `record_status_change()` and `create_notification()` are called inside the same transaction so these can never disagree.

---

## State ownership

- **MySQL is the source of truth.** Reports, assignments, history, notifications and verifications live only there.
- **The session holds identity only** — user id/name/role, a CSRF token, and one-shot flash messages. It is never used as a data store for reports.
- **The filesystem holds images only** — `uploads/evidence/` and `uploads/completion/`. Their metadata lives in `report_images`.

That separation is why the app can be restarted at any time without losing meaning: only the DB and the upload folders matter.

---

## Adding a feature (the checklist)

1. New data? Add a table + foreign keys in `database.sql`.
2. New shared rule? Add a constant in `config/app.php` or a helper in `includes/functions.php`.
3. New screen? Add a file in the owning role folder, call `require_role()`, query, include `header.php` / `footer.php`.
4. New button that writes? Add `actions/your-action.php` using the request path above.
5. New decision on the student's report? Update the whitelist in `is_valid_status_transition()` so the workflow stays legal.

You never edit a shared include to add a page-specific feature.

---

## Deliberate non-choices

| Not used | Why |
|---|---|
| Laravel / any framework | The requirement is plain PHP; the patterns are written out explicitly instead |
| ORM / query builder | PDO prepared statements in helpers are enough for this schema |
| Routing framework | One file per screen is clearer than a route table for a project this size |
| Template engine | PHP itself is the template engine; `e()` handles escaping |
| JS framework | Vanilla DOM + `fetch()` covers all client needs |
| Build step / npm | Assets are vendored locally; `docker compose up` is the only setup |

Minimal by design: three demo users, two buildings, three reports, one stylesheet extension, three small JS files. The schema stays normalised and the security guards stay intact — those are requirements, not complexity added for its own sake.
