# MiralDrive Lead Qualification — API (`lead_b`)

REST API of the MiralDrive internal lead qualification platform: dispatchers call
leads coming from Instagram campaigns and qualify their transportation need
through a guided, admin-configurable call script.

The React SPA lives in the [`lead_f`](https://github.com/mohamedahmedtrigui/lead_f) repository.

**Stack:** Laravel 12 · PHP 8.2+ · MySQL 8 · Sanctum (SPA cookie auth)

---

## Quick start (local, WAMP / XAMPP / Laragon)

```bash
composer install
cp .env.example .env            # then set DB_* and ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed      # admin + call script (+ demo dispatchers in local)
php artisan leads:import /path/to/leads.csv --distribute
php artisan serve --host=localhost --port=8000
```

> Use `localhost` (not `127.0.0.1`) for both the API and the SPA: the session
> cookie is per host.

> **MySQL on WAMP** defaults to MyISAM. `config/database.php` forces
> `DB_ENGINE=InnoDB` (needed for foreign keys and long indexes).

| Account (local seed) | Password | Role |
|---|---|---|
| `ADMIN_EMAIL` from `.env` | `ADMIN_PASSWORD` from `.env` | Admin |
| `sami@`, `ines@`, `youssef@miraldrive.com` | `Dispatch@2026` | Dispatcher (approved) |
| `amira@miraldrive.com` | `Dispatch@2026` | Dispatcher (pending approval) |

Demo dispatchers are seeded only when `APP_ENV=local`.

### Useful commands

```bash
php artisan test                         # feature tests (SQLite in memory)
vendor/bin/pint                          # code style
php artisan leads:import file.csv        # import (source fields only)
php artisan leads:import file.csv --distribute --strategy=balanced
php artisan db:seed --class=ScriptStepSeeder   # add missing script steps
```

---

## Architecture

Business logic is grouped by **domain module** under `app/Domain`. Controllers
stay thin (validate → call a service → return a resource), so new CRM modules
can be added without touching the qualification module.

```
app/
├── Domain/
│   ├── Analytics/       AdminDashboardService, DispatcherDashboardService (funnel, KPIs, performance)
│   ├── Audit/           AuditEvent enum + AuditLogger (single entry point for the audit trail)
│   ├── Calls/           CallService: start/end calls, NRP workflow, callbacks, quick outcomes
│   ├── Leads/           Import (CSV), assignment (round-robin / balanced), status changes, notes
│   ├── Qualification/   Enums of every answer, QualificationService, ScoringService, validation rules
│   ├── Scripts/         DefaultScript (structure + default wording) and ScriptService (admin edits)
│   ├── Shared/          BusinessRuleException (→ HTTP 422 with a code)
│   └── Users/           UserRole/UserStatus, DispatcherService (register/approve/reject/deactivate)
├── Http/
│   ├── Controllers/Api/V1/        Auth, Leads, Calls, Qualification, Notes, Script, Dashboard
│   ├── Controllers/Api/V1/Admin/  Dashboard, Dispatchers, Lead management, Import, Export, Script, Activity
│   ├── Middleware/                EnsureRole (`role:ADMIN`), EnsureUserIsApproved (`approved`)
│   ├── Requests/                  FormRequest validation (French messages via lang/fr)
│   └── Resources/                 API resources (JSON contracts)
├── Models/          User, Lead, LeadQualification, CallAttempt, DispatcherNote,
│                    LeadAssignment, AuditLog, ScriptStep, LeadImport
└── Policies/        LeadPolicy (dispatcher data isolation)
config/
├── leads.php          NRP rules, import timezone / phone country code
└── qualification.php  scoring weights and interest level thresholds
```

### Data model

| Table | Purpose |
|---|---|
| `users` | admins & dispatchers (`role`, `status`: PENDING / APPROVED / REJECTED / DEACTIVATED) |
| `leads` | **source data** (CSV columns, never touched by the qualification) + **pipeline state** (status, assignee, NRP counter, callback) |
| `lead_qualifications` | one structured row per lead: every wizard answer, score, level, priority, next action |
| `call_attempts` | every call (start/end, outcome, duration) |
| `dispatcher_notes` | internal notes & call summaries |
| `lead_assignments` | assignment history (AUTO / MANUAL / UNASSIGN, from → to, by) |
| `audit_logs` | event trail (assigned, call started/ended, qualification started/completed, status/score changed, callback, note…) |
| `script_steps` | admin-editable wording of the call script |
| `lead_imports` | import history and counters |

Indexes: `leads.status`, `leads.assigned_to`, `leads.phone`, `(assigned_to, status)`,
`lead_qualifications.lead_id` (unique), `call_attempts.lead_id`, `call_attempts.dispatcher_id`, …

### Key business rules

- **Isolation** — dispatchers only see leads assigned to them
  (`Lead::scopeVisibleTo` + `LeadPolicy`). Another dispatcher's lead returns
  **404**, so IDs cannot be probed.
- **Registration** — self-registered dispatchers are `PENDING` and cannot log in
  until approved. Deactivation kills sessions and can release open leads.
- **Round-robin** — 13 leads / 3 dispatchers → 5 / 4 / 4; the rotation resumes
  after the last dispatcher served. A `balanced` strategy fills the least-loaded first.
- **NRP** — attempt 1 → NRP, 2 → NRP, 3 → **NRP final**. Attempts must be spaced
  by `NRP_MIN_INTERVAL_MINUTES`, so the final NRP cannot be reached instantly.
- **Import** — deduplication by normalized phone (E.164), then email / WhatsApp /
  name; re-import only refreshes source columns, never status, assignment or qualification.
- **Scoring** (server-side, `config/qualification.php`): daily +20, recurring +15,
  ≥ 2 passengers +15, shared YES +15 (MAYBE +7), B2B +20, quotation +10,
  callback +10, positive MiralDrive experience +5 → HOT ≥ 80, WARM ≥ 60, INTERESTED ≥ 40, LOW.
- **Script** — admins edit titles, speech, questions, prompts, option labels and tips.
  Fields and option values are fixed in code so data and scoring stay consistent.

---

## API (v1)

Auth flow for the SPA: `GET /sanctum/csrf-cookie` → `POST /api/v1/auth/login`
(session cookie + `X-XSRF-TOKEN`).

| Method | Endpoint | Role |
|---|---|---|
| POST | `/auth/register`, `/auth/login` | public (rate-limited) |
| POST/GET | `/auth/logout`, `/auth/me` | authenticated |
| GET | `/script` | all |
| GET | `/leads`, `/leads/{id}`, `/leads/{id}/timeline`, `/leads/{id}/qualification`, `/leads/{id}/notes` | all (scoped) |
| POST | `/leads/{id}/notes` | owner / admin |
| GET | `/dashboard` | dispatcher |
| POST | `/leads/{id}/calls` · `/leads/{id}/calls/outcome` | dispatcher (owner) |
| PATCH / POST | `/leads/{id}/qualification` · `/leads/{id}/qualification/complete` | dispatcher (owner) |
| GET | `/admin/dashboard` | admin |
| GET/POST/PATCH | `/admin/dispatchers`, `/admin/dispatchers/{id}/{approve,reject,deactivate,reactivate}` | admin |
| POST | `/admin/leads/assign`, `/admin/leads/distribute` | admin |
| PATCH | `/admin/leads/{id}/status` | admin |
| GET/POST | `/admin/leads/imports` | admin |
| GET | `/admin/leads/export` (CSV, `;`, UTF-8 BOM) | admin |
| GET/PUT/POST | `/admin/script-steps`, `/admin/script-steps/{id}`, `/admin/script-steps/{id}/reset` | admin |
| GET | `/admin/calls`, `/admin/audit-logs` | admin |

---

## Security

Bcrypt password hashing · Sanctum session auth with CSRF (XSRF-TOKEN) · explicit
CORS origins with credentials · role middleware + policies (server-side) ·
FormRequest validation on every input · rate limiting (`login` 5/min per
email+IP, `register` 10/h per IP, `import` 5/min, `api` 180/min) · strict
Eloquent mode outside production · personal data (CSV) never committed.

## Production checklist

1. Copy `.env.production.example` to `.env`, set `APP_KEY`, DB credentials, domains.
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force && php artisan db:seed --class=AdminSeeder --force && php artisan db:seed --class=ScriptStepSeeder --force`
4. `php artisan config:cache route:cache event:cache`
5. Serve `public/` over HTTPS (Nginx/Apache + PHP-FPM); run `php artisan queue:work` under a supervisor if queues are used.
6. Remove `ADMIN_PASSWORD` from `.env` once the admin exists.
