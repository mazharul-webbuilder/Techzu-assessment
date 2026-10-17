# 03 - AI Agent Implementation Guide

For the AI coding agent in your IDE (Antigravity, Cursor, Copilot, etc.).
Idea: you drive, the agent builds in **small, reviewed steps**. You must be able to explain every line, so you read, run and commit after each phase.

> Reading order for the agent: `CONTEXT.md` -> `02-ARCHITECTURE.md` -> this file.
> The architecture file is the source of truth. If the agent wants to deviate, it must say so and ask first.

---

## 0. How to work (rules for you and the agent)

**For you**
1. One phase at a time. Do not paste the next prompt until the current phase passes its checklist.
2. After each phase: run it, read the diff, commit. If you cannot explain a file, ask the agent to explain it before moving on.
3. Phases marked **YOU TYPE** are the likeliest live-change questions. Write that code yourself and use the agent only to review, explain and write tests.
4. If the agent starts adding packages, repositories, interfaces or features not in the architecture file, stop it.

**Agent rules (paste at the start of every new session)**

```
You are helping me build a Laravel "Support Ticket & SLA Management System" for a job assessment.
Read CONTEXT.md and 02-ARCHITECTURE.md first and follow them exactly.

Rules:
- Work on ONE phase at a time. Stop when it is done and wait for my next instruction.
- Keep it simple. No extra packages, no repository pattern, no abstractions not in the architecture file.
- Controllers stay thin: Form Request -> authorize -> service -> response.
- Put business rules in services/config/enums, never in controllers or Blade.
- Add short comments that explain WHY, not what.
- After each phase give me: files created/changed, a 3-line explanation of the design, how to run/test it,
  and 2-3 lines I can paste into the README.
- I must understand every line. If something is clever, explain it or make it simpler.
- Tell me honestly about risks, mistakes or trade-offs. No flattery.
- Keep replies short: bullets and code blocks.
```

**Per-phase footer (add to every prompt)**

```
When finished: list the files you changed, explain the key decision in 3 lines, show the command to test it,
and give 2-3 README lines. Then stop.
```

---

## Phase overview

| # | Phase | Day | Type |
|---|---|---|---|
| 1 | Foundation: config, enums, morph map, migrations, models | Sat | Agent |
| 2 | Auth: three guards, login portals, layout | Sat | Agent |
| 3 | Seeders and factories | Sat | Agent |
| 4 | Ticket number generator | Sat | **YOU TYPE** |
| 5 | Ticket creation + activity logger + customer views | Sat | Agent |
| 6 | Policies, scopes, ticket lists and detail pages (all portals) | Sat/Sun | Agent |
| 7 | Replies, internal notes, status transitions | Sun | Agent |
| 8 | Assignment: manual, claim, auto-assign | Sun | **YOU TYPE** (claim query) |
| 9 | Business-hours calculator + SLA service | Sun | **YOU TYPE** (calculator) |
| 10 | Notifications + queue | Sun | Agent |
| 11 | Scheduler: SLA escalation | Sun | Agent |
| 12 | Search, filters, pagination | Mon | Agent |
| 13 | Dashboard | Mon | Agent |
| 14 | Attachments | Mon | Agent |
| 15 | CSV import | Mon | Agent |
| 16 | Tests review, README, CI, final polish | Mon | You + agent |

Tests are written **inside each phase**, not at the end. Phase 16 only fills gaps.

If time runs short, cut in this order: CSV import (do the small version), attachments, CI. Never cut 4, 8, 9, 11 or authorization tests.

---

## Phase 1 - Foundation

**Goal:** config, enums, morph map, all migrations and models with relationships.

**Prompt**

```
Phase 1 - Foundation. Follow section 3 (schema), 4 (relationships) and 5 (enums/config) of 02-ARCHITECTURE.md.
1. Create config/helpdesk.php exactly as in section 5.
2. Create enums TicketStatus, Priority, SlaStatus, ActivityEvent in app/Enums.
   TicketStatus needs: activeValues() (open, in_progress, waiting_for_customer), canTransitionTo(self $to): bool using the map in section 5, label().
   Priority needs: slaMinutes() reading config, label().
3. Write migrations for admins, agents, customers, categories, ticket_sequences, tickets, ticket_replies,
   attachments, ticket_activities, imports. Use the exact columns, indexes and foreign keys in section 3.
   Edit the default users migration as described in 01-INSTALLATION.md (remove users and password_reset_tokens, keep sessions).
4. Create the models with $fillable, enum casts, datetime casts and all relationships from section 4.
   Admin/Agent/Customer extend Authenticatable and use Notifiable. Hash passwords with the 'hashed' cast.
5. Register the morph map and preventLazyLoading in AppServiceProvider (section 2.4).
Do NOT write controllers or views yet.
```

**Done when**
- [ ] `php artisan migrate:fresh` runs clean
- [ ] `php artisan tinker`: `Ticket::first()?->customer` works without errors (after seeding later)
- [ ] You can explain every index using the table in section 3.1

**Commit:** `feat: schema, enums, models and config`

---

## Phase 2 - Authentication (three guards)

**Prompt**

```
Phase 2 - Auth. Follow section 2 and 13 of 02-ARCHITECTURE.md.
1. Configure config/auth.php with guards admin, agent, customer and providers (remove the users provider and passwords config).
2. Create one LoginController with methods that take the guard name from the route (or three small invokable controllers)
   for: show login form, login, logout. Use Auth::guard($guard)->attempt(), regenerate the session on login, invalidate on logout.
3. Routes: /customer/login, /agent/login, /admin/login (guest middleware per guard) and logout routes.
   Customers can also self-register at /customer/register (name, email, password); agents and admins are created by seeders only.
4. bootstrap/app.php: redirectGuestsTo by URL prefix and redirectUsersTo per guard (section 2.3).
5. Layout: resources/views/layouts/app.blade.php with Bootstrap 5 CDN (no @vite), a navbar that shows the correct links per guard,
   flash messages and validation errors. Create simple placeholder dashboards for each portal after login.
6. Feature tests: each guard can log in to its own portal and is rejected from the other portals.
```

**Done when**
- [ ] Customer logged in cannot open `/agent/...` or `/admin/...` (redirected to the right login)
- [ ] Logging in as an agent does not log you in as a customer
- [ ] Auth tests pass

**You must understand:** how `auth:agent` calls `Auth::shouldUse('agent')` so policies receive the agent.

**Commit:** `feat: multi-guard authentication with separate portals`

---

## Phase 3 - Seeders and factories

**Prompt**

```
Phase 3 - Seeders. Create factories for Admin, Agent, Customer, Category, Ticket (with states for priority/status) and TicketReply.
DatabaseSeeder must create: 1 admin (admin@example.com), 3 agents (agent1-3@example.com), 5 customers,
6 categories, and about 40 tickets in mixed statuses/priorities through the TicketService created later
(for now create them directly, and leave a TODO to switch to the service).
All demo passwords are "password". Print the demo accounts in the README later.
```

**Done when**
- [ ] `php artisan migrate:fresh --seed` works
- [ ] You can log in to all three portals with seeded accounts

**Commit:** `feat: factories and demo seeders`

---

## Phase 4 - Ticket number generator (YOU TYPE)

Write `app/Services/TicketNumberGenerator.php` yourself using section 8.1 as reference, then ask the agent to review and test it.

**Prompt (after you wrote it)**

```
Review my TicketNumberGenerator against section 8.1 of 02-ARCHITECTURE.md. Do not rewrite it; list problems and risks only.
Then write TicketNumberGeneratorTest (feature):
- first number of a year is TKT-<year>-000001, then 000002
- a new year restarts at 000001
- 50 numbers generated in a loop are all unique
- the format matches /^TKT-\d{4}-\d{6}$/
Use Carbon::setTestNow() for the year tests.
```

**Done when**
- [ ] Tests pass
- [ ] You can explain: why `lockForUpdate`, why `insertOrIgnore`, why it must run inside the ticket-creation transaction, why `UNIQUE(ticket_number)` still exists

**Commit:** `feat: ticket number generator with tests`

---

## Phase 5 - Ticket creation, activity logger, customer views

**Prompt**

```
Phase 5 - Ticket creation.
1. ActivityLogger service (section 6): log(Ticket $ticket, ActivityEvent $event, ?Model $actor, array $old = [], array $new = [], ?string $description = null).
   actor null means the system. It must be called inside the caller's transaction.
2. TicketService::create(Customer $customer, array $data): in ONE DB::transaction:
   generate the ticket number, create the ticket with status open, calculate SLA deadlines through SlaService
   (create SlaService now with a temporary simple calculation using plain addMinutes, with a TODO that Phase 9 replaces it with business hours),
   log the 'created' activity.
3. StoreTicketRequest (subject required|max:255, description required, priority in enum, category exists and is active).
4. Customer portal: ticket create form, store action, redirect to the ticket show page.
5. Feature test: a customer can create a ticket; the number format is right; an activity row exists; invalid input is rejected.
```

**Done when**
- [ ] Creating a ticket writes the ticket row and a `created` activity in one transaction
- [ ] You can explain why the number is generated inside the transaction

**Commit:** `feat: ticket creation with audit log`

---

## Phase 6 - Policies, scopes, lists and detail pages

**Prompt**

```
Phase 6 - Authorization and views. Follow section 7 of 02-ARCHITECTURE.md exactly.
1. TicketPolicy with methods view, update, reply, addNote, claim, assign, changeStatus, changePriority.
   Methods accept Customer|Agent|Admin $user and use instanceof. Follow the matrix in 7.1.
2. Query scopes: Ticket::scopeVisibleTo($actor), TicketReply::scopeVisibleTo($actor) (customers never get is_internal rows),
   Ticket::scopeActive(), Ticket::scopeUnassigned().
3. Controllers per portal (Customer/Agent/Admin TicketController): index (paginate 15, eager load customer/assignedAgent/category), show.
   Every action calls $this->authorize(). Lists always use visibleTo().
4. Views: ticket list table with status/priority badges, and a show page with details, conversation thread and the activity timeline.
   Share partials in resources/views/tickets/_*.blade.php.
5. Feature tests (TicketAuthorizationTest):
   - customer A cannot open customer B's ticket (decide 403 or 404 and keep it consistent)
   - customer cannot list other customers' tickets
   - agent cannot view a ticket assigned to another agent, but can view unassigned
   - admin can view everything
Do not add reply or assignment actions yet.
```

**Done when**
- [ ] Changing the ID in the URL to someone else's ticket is blocked for every role
- [ ] Authorization tests pass
- [ ] You can explain policy vs scope and why you need both

**Commit:** `feat: ticket policies, scopes and views`

---

## Phase 7 - Replies, internal notes, status transitions

**Prompt**

```
Phase 7 - Conversation and status. Follow sections 6, 7 and 5 (transitions) of 02-ARCHITECTURE.md.
1. TicketService methods: reply(Ticket, Model $author, string $body, bool $internal = false),
   changeStatus(Ticket, TicketStatus $to, Model $actor), changePriority(Ticket, Priority $to, Model $actor), resolve(), close().
   Each runs in a transaction and logs an activity with old/new values. Invalid transitions throw a clear exception.
   Customer reply on waiting_for_customer moves the ticket to in_progress; on resolved it reopens (in_progress if assigned, else open).
   changePriority must call SlaService to recalculate deadlines (temporary version for now) and reset the notified flags.
2. StoreReplyRequest: body required; is_internal allowed ONLY for agent/admin (enforce server-side, ignore it for customers).
3. Controllers/routes: reply, note, status, priority per the route list in section 13, all authorized through TicketPolicy.
4. Views: reply form (customer: reply only; agent/admin: reply + internal note toggle), visibly styled internal notes for staff.
5. Tests: customer cannot post an internal note even by forging is_internal=1; customer never sees internal notes in the page
   HTML; invalid status transition is rejected; each action writes an activity row with old/new values.
```

**Done when**
- [ ] Forging `is_internal=1` as a customer does nothing
- [ ] The customer's page HTML never contains internal note text (test asserts it)

**Commit:** `feat: replies, internal notes and status transitions`

---

## Phase 8 - Assignment (claim query: YOU TYPE)

Write the atomic claim yourself in `TicketAssignmentService::claim()` using section 8.4. Let the agent build everything around it.

**Prompt (after you wrote claim)**

```
Phase 8 - Assignment. I wrote claim() myself in TicketAssignmentService (conditional UPDATE where assigned_agent_id IS NULL).
1. Review claim() and list problems only (do not rewrite).
2. Add to the same service: assign(Ticket, Agent, Admin $by) (manual, admin only, reassignment allowed),
   pickAgent(): ?Agent (available agents, fewest active tickets, tie-break by id, as in section 8.4),
   autoAssign(Ticket): bool that uses the SAME conditional update, and logs 'assigned' with actor null (system) or the admin.
   When nobody is available, log an activity and leave the ticket unassigned.
3. TicketAlreadyAssignedException, rendered as HTTP 409 with a friendly flash message.
4. AutoAssignTicketJob (afterCommit), dispatched from TicketService::create() when config('helpdesk.auto_assign_on_create') is true.
5. Agent availability toggle (POST /agent/availability). Agent portal: "Unassigned pool" tab with a Claim button; admin: Assign dropdown.
6. TicketAssignmentTest:
   - claim succeeds on an unassigned ticket and sets status in_progress
   - a second claim of the same ticket throws TicketAlreadyAssignedException and the first agent stays assigned
   - pickAgent chooses the agent with the fewest active tickets and skips unavailable agents
   - agents cannot assign tickets to others (policy); admin can reassign
```

**Done when**
- [ ] You can explain why the second UPDATE affects 0 rows
- [ ] You can name 2 alternatives (`lockForUpdate`, optimistic locking, Redis) and why you did not choose them

**Commit:** `feat: manual, automatic and atomic ticket assignment`

---

## Phase 9 - Business hours + SLA (calculator: YOU TYPE)

Write `BusinessHoursCalculator` yourself from the algorithm in section 8.2. Do the Thursday example on paper first.

**Prompt (after you wrote it)**

```
Phase 9 - SLA. I wrote BusinessHoursCalculator myself (section 8.2). Review it and list bugs/edge cases only.
Then write BusinessHoursCalculatorTest (unit, Carbon::setTestNow not needed, pass explicit dates, config in the test):
- Thursday 17:00 + 120 min (Sun-Thu 09:00-18:00, Asia/Dhaka) = Sunday 10:00
- created exactly at 18:00 behaves like next business day opening
- created Friday 11:00 + 60 min = Sunday 10:00
- created Sunday 08:00 + 30 min = Sunday 09:30
- 2880 minutes (Low) spans multiple days and lands on the correct day/time
- a configured holiday is skipped
- result is returned in UTC
Then:
1. Replace the temporary logic in SlaService with deadlinesFor() using the calculator for BOTH due and warning (section 8.3).
2. Ticket query scopes: slaBreached(), slaApproaching(), slaWithin() (active tickets only) as in section 8.3.
3. Add Ticket::slaStatus(): SlaStatus accessor and show an SLA badge + due time in the list and detail pages (display in the business timezone).
4. Update TicketService create/changePriority to use the new SlaService. Add a test that a priority change recalculates the deadlines.
```

**Done when**
- [ ] You can calculate a deadline by hand on a whiteboard
- [ ] You can say which assumptions are documented (clock keeps running while waiting for customer, etc.)

**Commit:** `feat: business-hours SLA calculation`

---

## Phase 10 - Notifications and queue

**Prompt**

```
Phase 10 - Notifications. Follow section 9 of 02-ARCHITECTURE.md.
1. Implement the 7 notification classes: all implements ShouldQueue, $afterCommit = true, channels mail + database,
   $tries = 3 and a sensible backoff. Mail content: short, with the ticket number, subject and a link.
2. A small NotificationRecipients helper (or methods on TicketService) that resolves recipients per the table:
   assigned agent, or all admins when unassigned, etc.
3. Dispatch from TicketService / TicketAssignmentService after the transaction commits.
   Internal notes must NEVER notify customers.
4. Make sure the notifications table migration exists (database channel).
5. Tests with Notification::fake(): ticket created -> customer notified; assigned -> agent notified; agent public reply -> customer notified;
   internal note -> customer NOT notified; customer reply -> assigned agent notified; resolved -> customer notified.
```

**Done when**
- [ ] With `php artisan queue:work` running, emails appear in `storage/logs/laravel.log`
- [ ] The internal-note test proves customers are not notified

**Commit:** `feat: queued notifications`

---

## Phase 11 - Scheduler: SLA escalation

**Prompt**

```
Phase 11 - Escalation. Follow section 8.5 of 02-ARCHITECTURE.md.
1. CheckSlaCommand (tickets:check-sla): for ACTIVE tickets only, chunkById(200):
   a) breach scan: sla_due_at <= now AND sla_breach_notified_at IS NULL
   b) warning scan: sla_warning_at <= now AND sla_due_at > now AND sla_warning_notified_at IS NULL
   Use the atomic "claim the flag with a conditional update" pattern; only the winner logs the activity and sends notifications.
   On breach also set escalated_at and (if null) sla_warning_notified_at. Activity events: sla_warning, escalated, actor null (system).
2. Schedule it every minute with withoutOverlapping() in routes/console.php.
3. SlaEscalationTest with Carbon::setTestNow() and Notification::fake():
   - a ticket past its warning time gets one warning, running the command twice sends only one
   - a ticket past its due time is escalated once (escalated_at set, one activity, notifications to agent and admins)
   - resolved/closed tickets are ignored
   - unassigned breached ticket notifies admins
4. Show an "Escalated" badge in lists and the escalation in the activity timeline.
```

**Done when**
- [ ] Running `php artisan tickets:check-sla` twice never duplicates an escalation
- [ ] You can explain why idempotency flags exist and why the scan is better than a delayed job per ticket

**Commit:** `feat: scheduled SLA warning and escalation`

---

## Phase 12 - Search, filters, pagination

**Prompt**

```
Phase 12 - Filters. Follow section 12 of 02-ARCHITECTURE.md.
1. TicketFilter class that accepts validated input and applies: ticket number (prefix LIKE 'TKT-2026-%'), keyword (LIKE on subject),
   status, priority, category, assigned agent (including "unassigned"), customer, created date range, SLA status (reuse the scopes).
2. TicketIndexRequest validates the query string (enums, exists rules, dates).
3. Use it in all three portals together with visibleTo($actor). Customers only get the filters that make sense for them.
4. Pagination with withQueryString(); filter form that keeps its values; show active/upcoming vs resolved/closed views (tabs or a status filter).
5. Tests: each filter returns the right tickets; a customer applying customer=<other id> still only gets their own tickets;
   query count stays constant with 30 tickets (use DB::listen or assertQueryCount style check to catch N+1).
```

**Done when**
- [ ] Filters combine correctly and never widen what an actor may see
- [ ] No N+1 on the list page

**Commit:** `feat: ticket search, filters and pagination`

---

## Phase 13 - Dashboard & UI/UX Experience

**Prompt**

```
Phase 13 - Dashboard & Modern UI/UX. Follow section 12 of 02-ARCHITECTURE.md.
1. Design System & Layout (resources/views/layouts/app.blade.php):
   - Modern glassmorphism sticky header with live SLA beacon and portal identity badges.
   - Portal themes: Indigo (Customer), Teal (Agent Workstation), Rose/Crimson (Admin Console).
   - Elevated stat-card components with subtle hover micro-animations and soft badges.
   - Interactive Priority Cards with live SLA target calculation for ticket submission.

2. Backend Aggregations (DashboardService / Queries):
   - One conditional-aggregation query for the counters:
     (open total, unassigned, critical open, breached, approaching, resolved total)
   - One GROUP BY priority query, and tickets by agent through Agent::withCount (active tickets).
   - Query count: 3 or fewer queries total (never 10+ separate count() queries).
   - Optional: Cache::remember for 30-60 seconds on admin aggregate statistics.

3. Portal Dashboards:
   - Admin Dashboard: Executive command center with 6 KPI cards, infrastructure capacity, and live ticket stream.
   - Agent Dashboard: Workload overview (my assigned, unassigned triage pool, approaching SLA risks, personal resolved).
   - Customer Dashboard: 4 key metrics (submitted, in progress, awaiting customer, resolved), quick search filter, and SLA deadline tracker.

4. Tests: counters match known seeded data; an agent only sees their own numbers; query count per page stays minimal.
```

**UI/UX Layout Standards**
- **Typography & Canvas:** Inter + Plus Jakarta Sans with subtle radial ambient gradients on `#f8fafc`.
- **Card Hierarchy:** Rounded corners (`1rem`), layered drop-shadows, soft borders (`#e2e8f0`).
- **Interactive Forms:** Split 2-column layout, live SLA guarantee widget, visual radio cards for priorities.
- **SLA Visualization:** Dynamic colored badges (`badge-soft-*`), live pulsing indicators for active/breached timers.

**Done when**
- [ ] Layout and forms feel modern, polished, and responsive (no bare default Bootstrap components)
- [ ] Counter queries are 3 or fewer for the admin dashboard using conditional aggregation
- [ ] You can explain conditional aggregation (`SUM(CASE WHEN ... THEN 1 ELSE 0 END)`)

**Commit:** `feat: dashboard with aggregated queries and modern UI/UX`

---

## Phase 14 - Attachments

**Prompt**

```
Phase 14 - Attachments. Follow section 10 of 02-ARCHITECTURE.md.
1. Add attachment upload to ticket creation and to replies (multiple files). Validate with config values: file, max size, mimes, max files.
2. Store on the private 'local' disk at tickets/{ticket_id}/{uuid}.{ext}; save original_name, mime_type, size_bytes, uploaded_by morph.
3. AttachmentPolicy@download: allowed if the actor can view the ticket AND (the file is not attached to an internal reply OR the actor is staff).
4. Route GET /attachments/{attachment} with middleware auth:customer,agent,admin returning Storage::download with the original name.
5. Tests with Storage::fake(): upload OK; oversize and wrong type rejected; another customer gets 403/404;
   a customer cannot download a file attached to an internal note; files are not reachable by a public URL.
```

**Done when**
- [ ] Nothing is stored under `public/`
- [ ] You can explain why files are served through a controller

**Commit:** `feat: secure ticket attachments`

---

## Phase 15 - CSV import (keep it small but correct)

**Prompt**

```
Phase 15 - CSV import. Follow section 11 of 02-ARCHITECTURE.md.
1. Admin page: upload form (ImportTicketsRequest: file, mimes csv/txt, max 10 MB), import list, import detail page with progress
   and a download link for the error report.
2. ImportTicketsJob: read with fopen/fgetcsv one row at a time, validate each row, skip duplicates by external_id,
   find-or-create customer by email and category by name, create tickets through TicketService in silent mode
   (no notifications, no auto-assign, SLA from the row's created_at). Write invalid rows to an error CSV with row number and reason.
   Update processed/failed/skipped counters every 500 rows. Catch exceptions, mark failed, store the message.
3. ImportPolicy: admin only.
4. Provide a sample CSV in the repo at docs/sample-import.csv.
5. TicketImportTest: a small CSV with valid rows, one invalid row and one duplicate -> counts are exact, error report content is right,
   re-importing the same file creates nothing new.
```

**Done when**
- [ ] Memory stays flat for large files (no `file()` / `Collection` of all rows)
- [ ] Re-import is safe

**Commit:** `feat: queued CSV import with validation and error report`

---

## Phase 16 - Final checks, README, CI

**Prompt**

```
Phase 16 - Finish.
1. Run the whole test suite and fix failures. List which important areas still have no test; do not add tests for trivial CRUD.
2. Generate README.md with: overview, features checklist, tech stack, requirements, setup steps (clone, composer install, .env, key, migrate --seed),
   running (serve, queue:work, schedule:work), demo accounts, running tests, architecture summary (link to docs/ARCHITECTURE.md),
   the assumptions list from section 17 of 02-ARCHITECTURE.md, SLA and business-hours design, assignment strategy, concurrency solution,
   known limitations and next steps. No secrets.
3. Copy 02-ARCHITECTURE.md to docs/ARCHITECTURE.md. Do not commit CONTEXT.md or the agent guide if they contain personal information.
4. Add .github/workflows/tests.yml: PHP setup, composer install, copy .env.example, key:generate, run php artisan test.
5. Check for: debug leftovers (dd, dump), unused files, hard-coded secrets, missing indexes on columns used in filters, N+1 on list pages (preventLazyLoading).
```

**Final checklist before submitting**
- [ ] Fresh clone works with only the README steps (test it in a new folder)
- [ ] `.env.example` is complete; no real `.env` or credentials in Git
- [ ] `php artisan test` is green
- [ ] Demo accounts listed; seeders create realistic data
- [ ] README explains queue worker and scheduler
- [ ] Assumptions and known limitations are written down
- [ ] You rehearsed the "change this live" table (section 16) and the Q&A (section 15)
- [ ] Reply to HR with the repo link before the deadline (aim for Monday night)

---

## Interview rehearsal (do this the night before the interview)

Without looking at the code, explain out loud:
1. The request flow of "customer creates a ticket" from route to database to queue.
2. The Thursday 17:00 Critical-ticket deadline calculation.
3. The claim query and why two agents cannot both win.
4. How internal notes are kept from customers (query level).
5. The escalation command and why it never double-sends.
6. Three weaknesses of your design and how you would fix them.

Then practice three live changes (for example: change the weekend to Friday-Saturday, make High = 6 hours, add an "On Hold" status) and time yourself.

---

## Troubleshooting

| Problem | Likely cause / fix |
|---|---|
| Policy gets the wrong user type | Route group missing `auth:<guard>`; the guard was not set before `authorize()` |
| Redirect loop on login | `redirectGuestsTo` / `redirectUsersTo` match the wrong prefix |
| Emails not appearing | Queue worker not running, or `afterCommit` inside a test without transactions |
| Scheduler does nothing locally | Run `php artisan schedule:work` in a separate terminal |
| Times off by hours | App timezone is UTC; convert to `Asia/Dhaka` only for calculation and display |
| `lockForUpdate` seems ignored in tests | SQLite ignores it; the conditional UPDATE does not depend on it |
| Class not found for morph | Missing entry in `Relation::enforceMorphMap` |