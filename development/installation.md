# 01 - Fresh Laravel Installation Guide

Goal: a clean Laravel project with **no starter kit, no template**, ready for the helpdesk build.

---

## 1. Prerequisites

| Tool | Version | Check |
|---|---|---|
| PHP | 8.2+ (8.3 recommended) | `php -v` |
| Composer | 2.x | `composer -V` |
| MySQL | 8.x | `mysql --version` |
| Git | any | `git --version` |
| Node | optional (see UI decision) | `node -v` |

PHP extensions needed: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `ctype`, `json`, `tokenizer`, `xml`.

---

## 2. Create the project

Use Composer directly. It is non-interactive, so there is no starter-kit prompt and nothing extra gets installed.

```bash
composer create-project laravel/laravel helpdesk
cd helpdesk
php artisan --version     # note the version for your README
```

> Why not `laravel new`? It asks about starter kits (Breeze, Livewire, etc.). You want none, and `composer create-project` gives exactly the bare skeleton.

---

## 3. Create the database

```sql
CREATE DATABASE helpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## 4. Configure `.env`

```dotenv
APP_NAME="Helpdesk"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=UTC            # store UTC; business hours are converted in code (see 02-ARCHITECTURE)

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=helpdesk
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database   # no Redis needed for reviewers
SESSION_DRIVER=database     # or file
CACHE_STORE=database        # or file
MAIL_MAILER=log             # emails written to storage/logs/laravel.log
FILESYSTEM_DISK=local       # private disk, files are NOT publicly reachable
```

Check these values exist in your skeleton version. Names changed slightly across Laravel versions (for example `CACHE_STORE` vs `CACHE_DRIVER`).

```bash
php artisan key:generate    # usually already done by create-project
```

---

## 5. Remove the default single-user setup

You are using three separate tables (`admins`, `agents`, `customers`), so the default `User` is not needed.

1. Delete `app/Models/User.php` and `database/factories/UserFactory.php`.
2. Open the default migration `0001_01_01_000000_create_users_table.php`:
   - **Remove** the `users` table and the `password_reset_tokens` table (password reset is not required).
   - **Keep** the `sessions` table (only if `SESSION_DRIVER=database`).
3. In `database/seeders/DatabaseSeeder.php`, remove the `User::factory()` calls (you will add your own seeders later).
4. In `config/auth.php`, you will replace guards and providers in Step 9 of the implementation guide. Until then, do not run tests that reference `User`.
5. Delete `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` or adjust them.

---

## 6. UI decision (fresh install, no template)

- **Plain Blade + Bootstrap 5 from CDN** in one layout file. No `npm install`, no build step. Reviewers run fewer commands.
- Do **not** use `@vite` in layouts. The default `resources/css/app.css` and `app.js` can stay unused.
- Optional small interactions: Alpine.js from CDN.
- Mention the CDN use in the README.

> Trade-off to know: CDN assets need internet. That is fine for a review project and removes the Node requirement.

---

## 7. Run it once to verify

```bash
php artisan migrate         # should run without errors
php artisan serve           # http://127.0.0.1:8000 shows the Laravel welcome page
```

Open a second terminal later for the queue worker and scheduler:

```bash
php artisan queue:work      # processes emails/imports
php artisan schedule:work   # runs the scheduler locally (every minute)
```

---

## 8. Generate the file skeleton

Run these once. Empty files with the right names make the structure clear before you write logic.

```bash
# Models + migrations (one by one, in this order)
php artisan make:model Admin -mf
php artisan make:model Agent -mf
php artisan make:model Customer -mf
php artisan make:model Category -mf
php artisan make:model TicketSequence -m
php artisan make:model Ticket -mf
php artisan make:model TicketReply -mf
php artisan make:model Attachment -m
php artisan make:model TicketActivity -m
php artisan make:model Import -m

# Database notifications table
php artisan notifications:table     # (if your version needs it; some versions ship it by default)

# Policies
php artisan make:policy TicketPolicy --model=Ticket
php artisan make:policy ImportPolicy --model=Import
php artisan make:policy AttachmentPolicy --model=Attachment

# Form Requests
php artisan make:request StoreTicketRequest
php artisan make:request UpdateTicketRequest
php artisan make:request StoreReplyRequest
php artisan make:request AssignTicketRequest
php artisan make:request ImportTicketsRequest

# Notifications (queued)
php artisan make:notification TicketCreatedNotification
php artisan make:notification TicketAssignedNotification
php artisan make:notification AgentRepliedNotification
php artisan make:notification CustomerRepliedNotification
php artisan make:notification TicketResolvedNotification
php artisan make:notification SlaWarningNotification
php artisan make:notification SlaBreachedNotification

# Jobs + console command
php artisan make:job AutoAssignTicketJob
php artisan make:job ImportTicketsJob
php artisan make:command CheckSlaCommand --command=tickets:check-sla

# Controllers (separate portals)
php artisan make:controller Auth/LoginController
php artisan make:controller Customer/TicketController
php artisan make:controller Agent/TicketController
php artisan make:controller Admin/TicketController
php artisan make:controller Admin/DashboardController
php artisan make:controller Admin/ImportController
php artisan make:controller AttachmentController

# Tests
php artisan make:test BusinessHoursCalculatorTest --unit
php artisan make:test TicketNumberGeneratorTest
php artisan make:test TicketAssignmentTest
php artisan make:test TicketAuthorizationTest
php artisan make:test SlaEscalationTest
php artisan make:test TicketImportTest
```

Create these folders and files manually (no artisan generator):

```
app/Enums/            Role (optional), TicketStatus.php, Priority.php, SlaStatus.php, ActivityEvent.php
app/Services/         TicketService, TicketNumberGenerator, BusinessHoursCalculator,
                      SlaService, TicketAssignmentService, ActivityLogger, TicketFilter, DashboardService
app/Exceptions/       TicketAlreadyAssignedException.php
config/helpdesk.php
```

---

## 9. Git setup

```bash
git init
git add .
git commit -m "chore: fresh Laravel skeleton"
# create an empty repo on GitHub, then:
git remote add origin <your-repo-url>
git branch -M main
git push -u origin main
```

- `.env` is already in `.gitignore`. Commit a clean `.env.example` instead (no secrets).
- Use a feature-style commit message for each module. Reviewers read your history.

---

## 10. Final check before you start coding

- [ ] `php artisan migrate` works on a clean DB
- [ ] `php artisan serve` shows the welcome page
- [ ] Default `User` model, factory and users migration are removed
- [ ] `.env.example` updated with the keys above
- [ ] First commit pushed to GitHub

Next: read `02-ARCHITECTURE.md`, then follow `03-AI-AGENT-IMPLEMENTATION-GUIDE.md`.