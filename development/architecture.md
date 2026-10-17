# 02 - System Architecture (with "why" notes for the interview)

How to use this file:
- Sections 1-12 explain **what** is built and **why**. Every `WHY` note is a likely interview answer.
- Section 15 is a Q&A you should be able to say out loud.
- Section 16 shows where to change things when they ask for a live modification.

---

## 1. Key decisions at a glance

| Decision | Choice | One-line reason |
|---|---|---|
| Framework | Laravel, fresh skeleton, no starter kit | Own every line; no hidden template code |
| UI | Blade + Bootstrap 5 (CDN), no build step | UI is not graded; fewer setup steps for reviewers |
| Users | **Three tables**: `admins`, `agents`, `customers` | Required design; strict separation of identities |
| Auth | Three session guards (`admin`, `agent`, `customer`) | One guard per table, separate login portals |
| Authorization | Policies (per resource) + query scopes (per list) | Policy protects one record, scope protects lists |
| Business logic | Service classes, thin controllers | Testable, reusable, easy to explain |
| Config | `config/helpdesk.php` (SLA, business hours, uploads) | Rule changes need no code edits |
| Time | Store **UTC**, calculate business hours in `Asia/Dhaka` | No timezone surprises in the database |
| Queue | `database` driver | No Redis required to run the project |
| Files | Private `local` disk, served through an authorized route | Never publicly reachable |
| Concurrency | One atomic `UPDATE ... WHERE assigned_agent_id IS NULL` | Database decides the winner |
| Scheduler | `tickets:check-sla` every minute | Escalation without user action |
| Tests | Feature + unit tests on risky logic | Money-like rules: SLA, assignment, authorization |

---

## 2. Users, roles and authentication

### 2.1 Three tables, three models

```
admins     -> App\Models\Admin     (guard: admin)
agents     -> App\Models\Agent     (guard: agent)
customers  -> App\Models\Customer  (guard: customer)
```

All three extend `Illuminate\Foundation\Auth\User` (or implement `Authenticatable`) and use `Notifiable`.

> **WHY three tables instead of one `users` + `role` column?**
> - Each type has different columns (agents have `is_available`, customers have `phone`).
> - A customer account can never become an admin by editing a `role` value.
> - Foreign keys are precise: `tickets.assigned_agent_id` points only to `agents`.
> - **Honest trade-off:** duplicated columns, three guards to configure, and "who wrote this?" columns need polymorphic relations (no database-level foreign key). The single-table design is simpler; this one trades simplicity for isolation. Know both sides.

### 2.2 `config/auth.php`

```php
'defaults' => ['guard' => 'customer'],   // remove the 'passwords' => 'users' default (no reset feature)

'guards' => [
    'admin'    => ['driver' => 'session', 'provider' => 'admins'],
    'agent'    => ['driver' => 'session', 'provider' => 'agents'],
    'customer' => ['driver' => 'session', 'provider' => 'customers'],
],

'providers' => [
    'admins'    => ['driver' => 'eloquent', 'model' => App\Models\Admin::class],
    'agents'    => ['driver' => 'eloquent', 'model' => App\Models\Agent::class],
    'customers' => ['driver' => 'eloquent', 'model' => App\Models\Customer::class],
],
```

Remove the `passwords` array (it references the deleted `users` provider), or point it to nothing.

### 2.3 How the right user reaches policies

`auth:agent` middleware calls `Auth::shouldUse('agent')`. After that, `$request->user()`, `Gate`, `$this->authorize()` and `@can` all use the **agent** guard.

```php
// bootstrap/app.php: send guests to the right login page by URL prefix
->withMiddleware(function (Middleware $middleware) {
    $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
        $request->is('admin/*')  => route('admin.login'),
        $request->is('agent/*')  => route('agent.login'),
        default                  => route('customer.login'),
    });
})
```

> **WHY separate portals (`/admin`, `/agent`, `/customer`) and not one shared login?**
> With three tables the system cannot know which table to look in from an email alone (the same email may exist in two tables). Separate login pages remove that ambiguity and keep route groups, layouts and middleware simple.

### 2.4 Morph map (important)

```php
// AppServiceProvider::boot()
Relation::enforceMorphMap([
    'admin'    => Admin::class,
    'agent'    => Agent::class,
    'customer' => Customer::class,
]);
Model::preventLazyLoading(! app()->isProduction());   // surfaces N+1 in development
```

> **WHY a morph map?** The database stores `'agent'` instead of `App\Models\Agent`. Renaming or moving a class never breaks stored data, and `enforceMorphMap` throws if an unmapped model sneaks in.

---

## 3. Database schema

Conventions: all timestamps are stored in **UTC**. Money-like or status-like values are stored as `VARCHAR` backed by PHP enums.

> **WHY `VARCHAR` + PHP enum instead of MySQL `ENUM`?**
> The assessment says statuses may be extended. Adding a value to a MySQL `ENUM` is a schema change; with `VARCHAR` it is a one-line change in the PHP enum.

```sql
-- ============ IDENTITY (three separate tables) ============

CREATE TABLE admins (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name            VARCHAR(255) NOT NULL,
  email           VARCHAR(255) NOT NULL UNIQUE,   -- UNIQUE per table, not across tables (separate portals)
  password        VARCHAR(255) NOT NULL,          -- bcrypt via Hash::make, never plain text
  remember_token  VARCHAR(100) NULL,
  created_at      TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

CREATE TABLE agents (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name            VARCHAR(255) NOT NULL,
  email           VARCHAR(255) NOT NULL UNIQUE,
  password        VARCHAR(255) NOT NULL,
  is_available    BOOLEAN NOT NULL DEFAULT TRUE,  -- used by auto-assignment (agent on leave => FALSE)
  remember_token  VARCHAR(100) NULL,
  created_at      TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

CREATE TABLE customers (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name            VARCHAR(255) NOT NULL,
  email           VARCHAR(255) NOT NULL UNIQUE,
  password        VARCHAR(255) NOT NULL,
  phone           VARCHAR(30) NULL,
  remember_token  VARCHAR(100) NULL,
  created_at      TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

-- ============ REFERENCE DATA ============

CREATE TABLE categories (
  id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL UNIQUE,
  is_active  BOOLEAN NOT NULL DEFAULT TRUE,       -- hide instead of delete so old tickets keep their category
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

-- One row per year. Used only to generate TKT-YYYY-NNNNNN safely (see section 8.1).
CREATE TABLE ticket_sequences (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  year        SMALLINT UNSIGNED NOT NULL UNIQUE,  -- UNIQUE makes "create if missing" race-safe
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

-- ============ CORE ============

CREATE TABLE tickets (
  id                       BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ticket_number            VARCHAR(20)  NOT NULL UNIQUE,   -- TKT-2026-000001; UNIQUE is the final guard against duplicates
  external_id              VARCHAR(100) NULL UNIQUE,       -- only for CSV imports: detects duplicate rows (NULL allowed many times)
  subject                  VARCHAR(255) NOT NULL,
  description              TEXT NOT NULL,
  priority                 VARCHAR(20)  NOT NULL,          -- low | medium | high | critical
  status                   VARCHAR(30)  NOT NULL,          -- open | in_progress | waiting_for_customer | resolved | closed
  category_id              BIGINT UNSIGNED NOT NULL,
  customer_id              BIGINT UNSIGNED NOT NULL,       -- "Created By" (tickets are always created by a customer)
  assigned_agent_id        BIGINT UNSIGNED NULL,           -- NULL = unassigned (this is the concurrency pivot)

  -- SLA is calculated ONCE and stored. Status queries become simple indexed comparisons.
  sla_due_at               DATETIME NULL,                  -- resolution deadline (business-hours aware)
  sla_warning_at           DATETIME NULL,                  -- 80% of the SLA, also in business time
  sla_warning_notified_at  DATETIME NULL,                  -- idempotency flag: warning already sent
  sla_breach_notified_at   DATETIME NULL,                  -- idempotency flag: breach already handled
  escalated_at             DATETIME NULL,                  -- "marked as escalated"
  resolved_at              DATETIME NULL,
  closed_at                DATETIME NULL,
  created_at               TIMESTAMP NULL, updated_at TIMESTAMP NULL,

  FOREIGN KEY (category_id)       REFERENCES categories(id) ON DELETE RESTRICT,
  FOREIGN KEY (customer_id)       REFERENCES customers(id)  ON DELETE RESTRICT,  -- never lose ticket history
  FOREIGN KEY (assigned_agent_id) REFERENCES agents(id)     ON DELETE SET NULL,  -- removed agent => ticket returns to pool

  INDEX idx_status_priority   (status, priority),            -- dashboard + filters
  INDEX idx_agent_status      (assigned_agent_id, status),   -- "my tickets", unassigned (IS NULL), workload count
  INDEX idx_status_due        (status, sla_due_at),          -- breached / approaching queries, escalation scan
  INDEX idx_status_warning    (status, sla_warning_at),      -- warning scan in the scheduler
  INDEX idx_customer_status   (customer_id, status),         -- customer's own list
  INDEX idx_created_at        (created_at)                   -- date filter + default ordering
);

CREATE TABLE ticket_replies (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ticket_id    BIGINT UNSIGNED NOT NULL,
  author_type  VARCHAR(20)  NOT NULL,                        -- morph alias: customer | agent | admin
  author_id    BIGINT UNSIGNED NOT NULL,                     -- no DB foreign key possible (polymorphic)
  body         TEXT NOT NULL,
  is_internal  BOOLEAN NOT NULL DEFAULT FALSE,               -- TRUE = agent/admin note, never shown to customers
  created_at   TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  INDEX idx_ticket_created (ticket_id, created_at),          -- load a conversation in order
  INDEX idx_author (author_type, author_id)
);

CREATE TABLE attachments (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ticket_id        BIGINT UNSIGNED NOT NULL,
  reply_id         BIGINT UNSIGNED NULL,                     -- NULL = attached to the ticket itself
  uploaded_by_type VARCHAR(20) NOT NULL,
  uploaded_by_id   BIGINT UNSIGNED NOT NULL,
  disk             VARCHAR(20) NOT NULL DEFAULT 'local',
  path             VARCHAR(500) NOT NULL,                    -- random UUID name, never the user's file name
  original_name    VARCHAR(255) NOT NULL,                    -- shown to users, never used as a path
  mime_type        VARCHAR(100) NOT NULL,
  size_bytes       INT UNSIGNED NOT NULL,
  created_at       TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  FOREIGN KEY (reply_id)  REFERENCES ticket_replies(id) ON DELETE CASCADE,
  INDEX idx_ticket (ticket_id)
);

-- Append-only audit trail. No updated_at, no soft deletes, rows are never edited.
CREATE TABLE ticket_activities (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ticket_id   BIGINT UNSIGNED NOT NULL,
  actor_type  VARCHAR(20) NULL,                              -- NULL actor = the system (scheduler, auto-assign)
  actor_id    BIGINT UNSIGNED NULL,
  event       VARCHAR(50) NOT NULL,                          -- created | assigned | priority_changed | status_changed | replied ...
  old_values  JSON NULL,                                     -- {"priority":"medium"}
  new_values  JSON NULL,                                     -- {"priority":"high"}
  description VARCHAR(500) NULL,                             -- human-readable line for the timeline
  created_at  TIMESTAMP NULL,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE RESTRICT,
  INDEX idx_ticket_created (ticket_id, created_at)
);

-- ============ CSV IMPORT TRACKING ============

CREATE TABLE imports (
  id                BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  admin_id          BIGINT UNSIGNED NOT NULL,
  file_path         VARCHAR(500) NOT NULL,
  status            VARCHAR(20) NOT NULL,                    -- pending | processing | completed | failed
  total_rows        INT UNSIGNED NOT NULL DEFAULT 0,
  processed_rows    INT UNSIGNED NOT NULL DEFAULT 0,
  failed_rows       INT UNSIGNED NOT NULL DEFAULT 0,
  skipped_duplicates INT UNSIGNED NOT NULL DEFAULT 0,
  error_report_path VARCHAR(500) NULL,                       -- CSV of invalid rows with the reason
  started_at DATETIME NULL, finished_at DATETIME NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (admin_id) REFERENCES admins(id)
);

-- Laravel's own tables: jobs, failed_jobs, job_batches (queue), notifications (database notifications),
-- sessions, cache. The `notifications` table uses a morph (notifiable_type, notifiable_id),
-- so it works for all three user tables without extra work.
```

### 3.1 Which index serves which query

| Query / feature | Index used | WHY this shape |
|---|---|---|
| Customer's own tickets | `(customer_id, status)` | Equality on customer, then filter/sort by status |
| Agent's tickets, workload count | `(assigned_agent_id, status)` | Equality on agent, then status IN (...) |
| Unassigned tickets | `(assigned_agent_id, status)` | `assigned_agent_id IS NULL` is a valid leading-column lookup |
| Dashboard by status/priority | `(status, priority)` | Group/filter on status first, priority second |
| SLA breached (`due <= now`) | `(status, sla_due_at)` | Equality on active status, then a range on the date |
| SLA approaching | `(status, sla_warning_at)` | Same pattern for the warning column |
| Conversation view | `(ticket_id, created_at)` | Fetch one ticket's replies already ordered |
| Ticket timeline | `(ticket_id, created_at)` on activities | Same |
| Find by number | `UNIQUE(ticket_number)` | Exact and prefix lookups (`LIKE 'TKT-2026-%'`) |

> **Rule to quote:** in a composite index, put equality columns first and the range/sort column last. A leading wildcard `LIKE '%x'` cannot use a B-tree index, so ticket-number search uses a prefix match.

> **WHY SLA deadlines are stored, not computed per request?**
> Business-hour math in PHP cannot be filtered or paginated in SQL. Storing `sla_due_at` and `sla_warning_at` at creation makes "breached", "approaching" and "within" three cheap indexed comparisons, and the scheduler can find work without loading every ticket.

---

## 4. Eloquent relationships

| Model | Relationships |
|---|---|
| Customer | `hasMany(Ticket)` |
| Agent | `hasMany(Ticket, 'assigned_agent_id')` named `tickets` |
| Admin | `hasMany(Import)` |
| Category | `hasMany(Ticket)` |
| Ticket | `belongsTo(Customer)`, `belongsTo(Agent, 'assigned_agent_id')` as `assignedAgent`, `belongsTo(Category)`, `hasMany(TicketReply)` as `replies`, `hasMany(Attachment)`, `hasMany(TicketActivity)` as `activities` |
| TicketReply | `belongsTo(Ticket)`, `morphTo('author')`, `hasMany(Attachment)` |
| Attachment | `belongsTo(Ticket)`, `belongsTo(TicketReply)`, `morphTo('uploadedBy')` |
| TicketActivity | `belongsTo(Ticket)`, `morphTo('actor')` (nullable) |

**N+1 rules**
- List pages: `Ticket::with(['customer:id,name', 'assignedAgent:id,name', 'category:id,name'])->paginate(15)`
- Detail page: `->load(['replies.author', 'replies.attachments', 'activities.actor', 'attachments'])`
- `Model::preventLazyLoading()` in non-production makes accidental N+1 throw an exception during development.
- Always `paginate()` lists; use `chunkById()` or `lazyById()` for jobs and commands.

---

## 5. Enums and config

```php
enum TicketStatus: string { case Open='open'; case InProgress='in_progress';
                            case WaitingForCustomer='waiting_for_customer'; case Resolved='resolved'; case Closed='closed'; }
enum Priority: string { case Low='low'; case Medium='medium'; case High='high'; case Critical='critical'; }
enum SlaStatus: string { case Within='within'; case Approaching='approaching'; case Breached='breached'; }
```

Allowed status transitions (one map, one place to edit):

```
open                  -> in_progress, waiting_for_customer, resolved
in_progress           -> waiting_for_customer, resolved
waiting_for_customer  -> in_progress, resolved
resolved              -> closed, in_progress        (reopen)
closed                -> (none)
```

```php
// config/helpdesk.php
return [
    'sla_minutes' => ['low' => 2880, 'medium' => 1440, 'high' => 480, 'critical' => 120],
    'warning_threshold' => 0.8,               // warn at 80% of the SLA (business time)
    'business_hours' => [
        'timezone' => 'Asia/Dhaka',
        'days'     => [0, 1, 2, 3, 4],        // 0 = Sunday ... Thursday (Carbon dayOfWeek)
        'start'    => '09:00',
        'end'      => '18:00',
        'holidays' => [],                      // optional 'Y-m-d' dates
    ],
    'auto_assign_on_create' => true,
    'attachments' => ['disk' => 'local', 'max_kb' => 5120, 'max_files' => 5,
                      'mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'docx']],
    'import' => ['batch_size' => 500],
];
```

> **WHY config and not hard-coded or a database table?**
> The requirement: changing SLA rules must not touch application code. One config file satisfies that. A database table is the next step if an admin screen to edit rules were needed, and the `SlaService` would be the only class to change.

---

## 6. Services and their one job

| Class | Responsibility |
|---|---|
| `TicketNumberGenerator` | Produces `TKT-YYYY-NNNNNN` safely under concurrency |
| `BusinessHoursCalculator` | Adds N business minutes to a start time |
| `SlaService` | Calculates `sla_due_at`/`sla_warning_at`, returns SLA status, recalculates on priority change |
| `TicketService` | Create, update, change status/priority, resolve/close, reply; wraps changes in transactions |
| `TicketAssignmentService` | Manual assign, agent claim (atomic), auto-assign strategy |
| `ActivityLogger` | One method to write an audit row, called inside the same transaction as the change |
| `TicketFilter` | Turns validated query-string input into query conditions |
| `DashboardService` | Few grouped queries for the dashboard |

Controllers only: validate (Form Request) -> authorize (policy) -> call a service -> return a view or redirect.

> **WHY service classes and not repositories or fat controllers?**
> Fat controllers cannot be unit-tested or reused (the scheduler and the import also need ticket creation). A repository layer over Eloquent adds a second abstraction with no gain here. Services hold business rules; Eloquent stays the data layer.

---

## 7. Authorization

### 7.1 Matrix

| Action | Customer | Agent | Admin |
|---|---|---|---|
| Create ticket | Yes | No | No |
| View ticket | Own only | Own assigned or unassigned | All |
| Edit subject/description | Own, while `open` | No | Yes |
| Change priority / category | No | Own assigned | Yes |
| Change status | Close own `resolved` ticket | Own assigned (valid transitions) | Yes |
| Public reply | Own, ticket not closed | Own assigned | Yes |
| Internal note | **No** | Own assigned | Yes |
| See internal notes | **No** | Yes (tickets they can view) | Yes |
| Claim (accept) unassigned | No | Yes | n/a |
| Assign / reassign | No | No | Yes |
| Download attachment | If they can view the ticket and the file is not on an internal note | Same | All |
| CSV import | No | No | Yes |
| Dashboard | Own ticket counts | Own workload | Global |

### 7.2 Two layers (both are required)

1. **Policy** `TicketPolicy` (`view`, `update`, `reply`, `addNote`, `claim`, `assign`, `changeStatus`...): protects a single record. Methods accept `Customer|Agent|Admin $user` and use `instanceof`.
2. **Query scope** `Ticket::scopeVisibleTo($query, $actor)`: the same rules applied to lists, so a customer can never list someone else's tickets even by editing a URL or a filter.

```php
// Customers: where customer_id = actor.id
// Agents:    where (assigned_agent_id = actor.id OR assigned_agent_id IS NULL)
// Admins:    no restriction
```

Internal notes get the same treatment at the **query** level, not only in Blade:

```php
// TicketReply::scopeVisibleTo($q, $actor): customers get ->where('is_internal', false)
```

> **WHY filter in the query, not hide in the view?** A view-only check is one forgotten `@if` away from a data leak. If the query never loads internal notes for customers, they cannot leak through the page, JSON, an email or a future API.

Also:
- Notifications about internal notes are **never** sent to customers.
- Attachments on internal notes are hidden by `AttachmentPolicy` and by the reply scope.
- Authorization is server-side only: every controller action calls `$this->authorize()` or uses route middleware. Hidden buttons are cosmetic.
- Return **404** (not 403) for tickets a customer is not allowed to see if you want to avoid revealing that the ID exists. Pick one and document it.

---

## 8. Core algorithms

### 8.1 Ticket number

```php
public function next(?CarbonInterface $at = null): string
{
    $year = ($at ?? now())->year;

    return DB::transaction(function () use ($year) {
        // Create the year's row if missing. UNIQUE(year) makes concurrent inserts safe.
        DB::table('ticket_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        // Lock the row: concurrent callers queue up here, so each gets a different number.
        $row  = DB::table('ticket_sequences')->where('year', $year)->lockForUpdate()->first();
        $next = $row->last_number + 1;

        DB::table('ticket_sequences')->where('year', $year)->update(['last_number' => $next]);

        return sprintf('TKT-%d-%06d', $year, $next);   // 6 digits = up to 999,999 per year
    });
}
```

Call it **inside** the ticket-creation transaction. If ticket creation fails, the increment rolls back too, so numbers have no gaps.

> **WHY not `id` or an auto-increment?** The number must restart each year and have a prefix. Auto-increment cannot reset per year, and gaps appear after rollbacks.
> **WHY not Redis `INCR`?** It is not part of the database transaction, so a rollback would leave a gap, and it adds infrastructure.
> **Future growth:** 6 digits cover 999,999 per year; widening the pad is a one-line change, and the `VARCHAR(20)` column already has room. The `UNIQUE` index is the final safety net.

### 8.2 Business-hours calculator

Algorithm (walk forward through working windows, consuming minutes):

```
cursor = start (converted to business timezone)
loop:
    move cursor into business time:
        not a business day          -> next business day at opening time
        before opening              -> today at opening time
        at/after closing            -> next business day at opening time
    available = minutes between cursor and closing time today
    if remaining <= available: return cursor + remaining
    remaining -= available
    cursor = next business day at opening time
```

```php
public function addBusinessMinutes(CarbonInterface $start, int $minutes): CarbonImmutable
{
    $c = CarbonImmutable::instance($start)->setTimezone($this->tz);

    while (true) {
        $c = $this->moveIntoBusinessTime($c);
        $close = $c->setTimeFromTimeString($this->end);
        $available = intdiv($close->getTimestamp() - $c->getTimestamp(), 60);   // timestamps: no Carbon-version surprises

        if ($minutes <= $available) {
            return $c->addMinutes($minutes)->utc();
        }
        $minutes -= $available;
        $c = $this->nextBusinessDayStart($c);
    }
}
```

**Worked example (must be able to do this on a whiteboard):** Critical = 120 minutes, created Thursday 17:00, hours Sun-Thu 09:00-18:00.
- Thursday: 60 minutes available (17:00 to 18:00). Remaining 60.
- Next business day: Friday and Saturday are skipped, so Sunday 09:00.
- Sunday: 09:00 + 60 minutes = **Sunday 10:00**.

Edge cases to test: created at exactly 18:00, created on Friday, created at 08:00, SLA longer than one day (Low = 48h = 2,880 minutes spans several days), a holiday.

> **WHY walk forward in a loop and not a formula?** Working windows are irregular (weekends, holidays, partial days). A loop is easy to read and extend. Performance is irrelevant: the maximum is a few iterations per ticket.
> **WHY convert to the business timezone inside the calculator?** The DB and the app stay UTC; only this class needs to know that "9 AM" means 9 AM in Dhaka. The result is converted back to UTC before saving.

### 8.3 SLA service

```php
public function deadlinesFor(Priority $priority, CarbonInterface $from): array
{
    $minutes = config("helpdesk.sla_minutes.{$priority->value}");
    return [
        'due'     => $this->calc->addBusinessMinutes($from, $minutes),
        'warning' => $this->calc->addBusinessMinutes($from, (int) floor($minutes * config('helpdesk.warning_threshold'))),
    ];
}
```

SLA status (only for active tickets; resolved/closed tickets are "met" or "missed" using `resolved_at <= sla_due_at`):

| Status | Condition |
|---|---|
| Breached | `sla_due_at <= now` |
| Approaching | `sla_warning_at <= now < sla_due_at` |
| Within | `now < sla_warning_at` |

Implement these as query scopes (`slaBreached()`, `slaApproaching()`, `slaWithin()`) so the filters, dashboard and scheduler share one definition.

**Assumptions to document in the README**
- The SLA clock keeps running while the status is "Waiting for Customer".
- Changing priority recalculates deadlines from the ticket's original `created_at` and resets the two notified flags so the scheduler re-evaluates it.
- The clock stops when a ticket is `resolved` (`resolved_at` recorded). Reopening does not extend the SLA.

### 8.4 Assignment

**Manual** (admin): choose any agent; reassignment allowed. Logged as `assigned` / `reassigned`.

**Claim / accept (agent), the concurrency solution:**

```php
$affected = Ticket::query()
    ->whereKey($ticket->id)
    ->whereNull('assigned_agent_id')                 // <- the guard: only succeeds if still unassigned
    ->update(['assigned_agent_id' => $agent->id, 'status' => TicketStatus::InProgress]);

if ($affected === 0) {
    throw new TicketAlreadyAssignedException();      // render as HTTP 409 + friendly message
}
// inside the same DB::transaction: ActivityLogger::log(...) and queue the notification
```

> **WHY this works:** InnoDB takes a row lock for an `UPDATE`. If two agents run it at the same moment, the second one waits, then re-checks `assigned_agent_id IS NULL` against the committed row, finds it set, and updates **0 rows**. The database serializes the race, with no application-level locks.
> **Alternatives you should be able to compare:**
> - `lockForUpdate()` in a transaction (select then update): correct, but two statements and a longer lock.
> - Optimistic locking with a `version` column: good for long edits, more code than needed here.
> - Redis lock: adds infrastructure and still needs the DB guard as the final truth. Not needed.
> **Test note:** with SQLite tests, `lockForUpdate` is ignored, but this conditional UPDATE still behaves correctly, which is another reason to prefer it.

**Automatic (strategy: least open tickets, available agents only):**

```php
Agent::query()
    ->where('is_available', true)
    ->withCount(['tickets as open_tickets_count' => fn ($q) => $q->whereIn('status', TicketStatus::activeValues())])
    ->orderBy('open_tickets_count')      // fewest active tickets first
    ->orderBy('id')                      // stable tie-break
    ->first();
```

Then assign through the **same atomic UPDATE** above. Triggered by `AutoAssignTicketJob` after the ticket is created (`afterCommit`). If no agent is available, the ticket stays unassigned and visible in the claim pool, and an activity entry says so.

> **WHY workload-based?** It balances load without round-robin state to store. It stays correct under races: two concurrent auto-assigns may pick the same agent (a slight imbalance), but they can never double-assign one ticket because the UPDATE guard still holds.

### 8.5 SLA escalation (scheduler)

```php
// routes/console.php
Schedule::command('tickets:check-sla')->everyMinute()->withoutOverlapping();
```

`CheckSlaCommand`, for active tickets only, using `chunkById(200)`:

1. **Breach scan:** `sla_due_at <= now AND sla_breach_notified_at IS NULL`.
2. **Warning scan:** `sla_warning_at <= now AND sla_due_at > now AND sla_warning_notified_at IS NULL`.
3. For each ticket, **claim** it atomically, then act only if you won:

```php
$won = Ticket::whereKey($t->id)->whereNull('sla_breach_notified_at')
        ->update(['sla_breach_notified_at' => now(), 'escalated_at' => now()]);

if ($won === 1) {
    $this->activityLogger->log($t, ActivityEvent::Escalated, actor: null, ...);
    Notification::send($recipients, new SlaBreachedNotification($t));   // queued
}
```

> **WHY the `notified_at` columns and an atomic claim?** The command runs every minute. Without a flag it would re-notify every minute. The conditional UPDATE makes it **idempotent** even if two runs overlap, and `withoutOverlapping()` is a second layer of protection.
> **WHY a scheduled scan and not a delayed job per ticket?** Deadlines move (priority changes, reopen). Re-scanning the current truth is simpler and self-correcting; a delayed job would need cancellation logic.
> If a ticket skips straight past its warning (for example the command was down), the breach handler also sets the warning flag so no stale warning is sent later.

---

## 9. Notifications (all queued, `afterCommit`)

| Event | Recipients | Channels |
|---|---|---|
| Ticket created | Customer (confirmation) | mail + database |
| Ticket assigned | The agent | mail + database |
| Agent replied (public only) | Customer | mail + database |
| Customer replied | Assigned agent (admins if unassigned) | mail + database |
| Ticket resolved | Customer | mail + database |
| SLA approaching | Assigned agent (admins if unassigned) | mail + database |
| SLA breached | Assigned agent + all admins | mail + database |

- Every notification class `implements ShouldQueue` and sets `$afterCommit = true` so no email is sent for a transaction that rolls back.
- `MAIL_MAILER=log` for local runs; the README explains the worker: `php artisan queue:work`.
- Internal notes never trigger a customer notification.

> **WHY queue them?** SMTP calls are slow and can fail. Queueing keeps the request fast and gives retries (`$tries`, `backoff`) for free.

---

## 10. Attachments

- Validation: `file`, `max:5120` KB, `mimes:pdf,png,jpg,jpeg,txt,docx`, at most 5 files per submission (all from config).
- Storage: `Storage::disk('local')->putFileAs("tickets/{$ticket->id}", $file, Str::uuid().'.'.$file->extension())`.
- Download: `GET /attachments/{attachment}` with middleware `auth:customer,agent,admin` and `AttachmentPolicy@download`; responds with `Storage::download()` and the original name.

> **WHY not `public/storage`?** Files in a public folder are reachable by anyone who guesses or leaks a URL. Serving through a controller lets the policy check "can this user see this ticket (and this reply)?" on every download. Random names prevent path guessing and path-traversal tricks through file names.

---

## 11. CSV import

Flow: Admin uploads CSV -> file stored on the private disk -> `imports` row (`pending`) -> `ImportTicketsJob` dispatched -> admin watches progress on the import page.

Expected columns: `external_id, customer_email, customer_name, subject, description, priority, category, status, created_at`

Job logic:
1. Open the file with `fopen` + `fgetcsv` and read **one row at a time** (never `file()` or load the whole file).
2. Validate each row with `Validator::make()` (required fields, valid priority/status enums, valid date).
3. Duplicate check: `external_id` already in `tickets` (or repeated earlier in the file) -> count as skipped.
4. Resolve customer by email (create if missing) and category by name (create if missing).
5. Create the ticket through `TicketService` in its **own** transaction, in silent mode (no emails, no auto-assign, SLA calculated from the row's `created_at`).
6. Invalid rows are appended to an error CSV (`row_number, reason, original data`); the admin can download it.
7. Update progress counters every 500 rows; set `completed`, or `failed` with a message in a `catch`.

> **WHY a queued job and a streamed read?** Memory stays flat regardless of file size, and a 100k-row file would exceed a web request timeout. Counters give visible progress.
> **WHY `external_id` for duplicates?** CSV rows have no natural unique key. A unique external reference makes re-importing the same file safe (idempotent).
> **Known limitation:** each ticket takes the sequence row lock once. For very large imports, reserve a block of numbers per batch. Mention it as a planned improvement.

---

## 12. Search, filters and dashboard

**Filters** (`TicketFilter`, all optional, validated by a Form Request, always paginated): ticket number (prefix), keyword, status, priority, category, assigned agent, customer, date range, SLA status. Always combine with `visibleTo($actor)`. Use `->withQueryString()` on the paginator.

> **WHY `LIKE` for keywords?** It is simple and fine at this scale. For millions of rows the next step is a MySQL `FULLTEXT` index (`whereFullText`) or a search engine. Say that out loud in the interview.

**Dashboard** (`DashboardService`): one conditional-aggregation query for the counters:

```sql
SELECT
  SUM(status IN ('open','in_progress','waiting_for_customer'))                      AS open_total,
  SUM(assigned_agent_id IS NULL AND status IN (...active...))                       AS unassigned,
  SUM(priority = 'critical' AND status IN (...active...))                           AS critical_open,
  SUM(sla_due_at <= :now AND status IN (...active...))                              AS breached,
  SUM(sla_warning_at <= :now AND sla_due_at > :now AND status IN (...active...))    AS approaching,
  SUM(status IN ('resolved','closed'))                                              AS resolved_total
FROM tickets;
```

Plus one `GROUP BY priority` query and one `Agent::withCount(...)` for tickets by agent. About three queries total instead of ten `count()` calls.

> **WHY conditional aggregation?** One table scan (index-assisted) returns all counters instead of one query per card. Optional bonus: cache the result for 30-60 seconds.

---

## 13. Routes

```
/                                  landing page: links to the three portals
/customer/login|logout
/customer/tickets                  index, create, store, show, update, reply, close
/agent/login|logout
/agent/tickets                     index (my tickets / unassigned pool tabs), show
/agent/tickets/{ticket}/claim      POST (atomic accept)
/agent/tickets/{ticket}/reply|note|status|priority
/agent/availability                POST (toggle is_available)
/admin/login|logout
/admin/dashboard
/admin/tickets                     index (all, filters), show, assign, auto-assign
/admin/imports                     index, create, store, show (progress + error report)
/attachments/{attachment}          GET, middleware auth:customer,agent,admin
```

Each group uses `middleware('auth:<guard>')`. Route model binding plus `$this->authorize()` in every action.

---

## 14. Testing strategy

| Area | Test type | What it proves |
|---|---|---|
| `BusinessHoursCalculator` | Unit | Thursday-17:00 example, weekend skip, exact close time, multi-day SLA, before opening |
| `TicketNumberGenerator` | Feature | Format, sequential numbers, new year restarts, no duplicates |
| Claim | Feature | Second claim of the same ticket returns 409 and the first agent stays assigned |
| Auto-assign | Feature | Picks the least-loaded available agent; skips unavailable agents |
| Authorization | Feature | Customer cannot open another customer's ticket; customer never sees internal notes (page and query); agent cannot touch tickets assigned to others; admin-only routes reject agents |
| SLA scopes | Feature | Within / approaching / breached classification |
| Escalation command | Feature | Runs once; running twice does not duplicate activity or notifications (`Notification::fake()`) |
| Priority change | Feature | Deadlines recalculated; activity logged with old/new value |
| CSV import | Feature | Valid rows created, invalid rows reported, duplicates skipped |

> **WHY these?** They are the rules where a bug costs the most (wrong deadlines, double assignment, data leaks). Do not spend time testing Laravel itself or simple CRUD pages.

Test setup: SQLite in-memory for zero-setup (`phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), `RefreshDatabase`, `Notification::fake()`, `Queue::fake()`, `Carbon::setTestNow()` for time-dependent tests.

---

## 15. Interview Q&A (say these in your own words)

1. **Why three user tables?** Different attributes per role, no role-escalation through a column, precise foreign keys. Cost: three guards and polymorphic author columns without DB-level foreign keys.
2. **Why separate login portals?** The same email could exist in two tables, so the system needs to know which provider to check. Separate portals remove ambiguity.
3. **Why polymorphic `author`/`actor`/`uploaded_by`?** A reply can come from three different tables. The alternative is three nullable foreign-key columns per table (`customer_id`, `agent_id`, `admin_id`), which keeps integrity but is clumsier to query. I accepted the loss of FK integrity and enforce it in services plus the morph map.
3b. **How do you protect polymorphic integrity?** Morph map enforcement, authors are always set from the authenticated user (never from request input), and the audit table is append-only.
4. **Why store the SLA deadline?** So SLA state is a simple indexed query and the scheduler does not loop through every ticket.
5. **How do you stop two agents taking the same ticket?** One conditional `UPDATE ... WHERE assigned_agent_id IS NULL`; check the affected-row count; 0 means someone else won, return 409.
6. **Why not Redis locks (you used them at Jatri)?** Seat holds needed a time-limited reservation across a payment step. Here the assignment is instant and permanent, so a database row guard is simpler and is the source of truth anyway.
7. **How is the ticket number safe under load?** A locked sequence row inside the creation transaction plus a `UNIQUE` index as backstop.
8. **How does the business-hours deadline work?** Walk through working windows, consuming minutes; skip non-business days; show the Thursday 17:00 -> Sunday 10:00 example.
9. **How do you avoid sending escalation emails every minute?** Notified timestamps plus an atomic claim; the scan only picks rows where the flag is NULL.
10. **How do you hide internal notes?** Filtered in the query for customers (`is_internal = false`), not just in the view; no notifications and no attachment access either.
11. **Why queue notifications and imports?** Keep requests fast, allow retries, avoid timeouts.
12. **What happens with millions of tickets?** Composite indexes already match the filters; paginate everything; keyset pagination for deep pages; FULLTEXT or a search engine instead of `LIKE %x%`; archive or partition `ticket_activities`; cache the dashboard; move the queue to Redis; read replicas for reports.
13. **What would you improve with more time?** Password reset, FULLTEXT search, batch number reservation for imports, admin UI for SLA rules, real concurrency test against MySQL, API with rate limiting.
14. **What are the weak points of your design?** Polymorphic columns without FKs, `LIKE` search, SQLite tests do not prove row locking, auto-assign selection can pick the same agent twice under a race (imbalance only, never a double assignment).

---

## 16. "Change this live" cheat sheet

| Request in the interview | Where to change |
|---|---|
| New SLA time (e.g. High = 6h) | `config/helpdesk.php` -> `sla_minutes`; existing tickets keep stored deadlines |
| Change business hours or weekend | `config/helpdesk.php` -> `business_hours` |
| Add a holiday | `business_hours.holidays` |
| New priority "Urgent" | `Priority` enum + `sla_minutes` entry |
| New status "On Hold" | `TicketStatus` enum + transitions map + `activeValues()` if it counts as active |
| Customers can close only own tickets | `TicketPolicy@changeStatus` |
| Agents must not see unassigned tickets | `Ticket::scopeVisibleTo` (agent branch) + `TicketPolicy@view` + claim pool query |
| Assign by category instead of workload | `TicketAssignmentService::pickAgent()` |
| Warning at 70% | `warning_threshold` in config |
| Add "resolved by" to the activity text | `ActivityLogger` call in `TicketService::resolve()` |
| Add a filter (e.g. escalated only) | `TicketFilter` + Form Request rule + index if needed |
| Add or fix a test | Matching test class from section 14 |

---

## 17. Assumptions to copy into the README

- Three user tables, three guards, three login portals.
- Timestamps stored in UTC; business hours evaluated in `Asia/Dhaka` (configurable).
- SLA clock keeps running during "Waiting for Customer", stops at "Resolved", is not extended on reopen.
- Priority change recalculates deadlines from the original creation time.
- Auto-assignment: available agent with the fewest active tickets; unassigned tickets stay in the claim pool.
- Customers cannot create tickets for other customers; admins do not create tickets.
- Imported tickets do not send emails and are matched by `external_id`.
- Emails use the `log` mailer by default; run `php artisan queue:work` and `php artisan schedule:work` for full behaviour.