# ColdChain EMS — Backend API

> Laravel 12 · PHP 8.4 · MySQL 8 · Redis · JWT  
> Smart Cold-Storage & Energy Management System for Bangladesh / developing-market operators.

---

## Table of Contents

1. [Architecture overview](#architecture-overview)
2. [Tech stack](#tech-stack)
3. [Prerequisites](#prerequisites)
4. [Quick start (Docker)](#quick-start-docker)
5. [Manual / local setup](#manual--local-setup)
6. [Environment variables](#environment-variables)
7. [Database & seeding](#database--seeding)
8. [Running tests](#running-tests)
9. [API overview](#api-overview)
10. [Demo credentials](#demo-credentials)
11. [Project structure](#project-structure)
12. [Key design decisions](#key-design-decisions)

---

## Architecture overview

```
┌─────────────────────────────────────────────────────────┐
│                    ColdChain EMS                        │
│                                                         │
│   React Admin SPA  ←─── REST API ───→  Flutter App     │
│                           │                             │
│                    Laravel 12 Backend                   │
│                     ┌─────┴────────┐                   │
│                     │  Multi-tenant│                    │
│                     │  row-scoped  │                    │
│                     │  RBAC        │                    │
│                     └─────┬────────┘                   │
│              ┌────────────┼────────────┐               │
│            MySQL        Redis       (MQTT broker)       │
│           (primary)   (cache/queue)  (IoT telemetry)   │
└─────────────────────────────────────────────────────────┘
```

**Multi-tenancy** is row-level: every business record carries a `tenant_id`. A global `TenantScope` Eloquent scope enforces isolation automatically; an EnsureTenantContext middleware rejects requests that lack a resolved tenant.

**RBAC** uses a Permission enum (50+ slugs like `stock.stock_in`) grouped into Role bundles (Owner, Admin, BranchManager, Operator, Accountant, Technician, Auditor). Permissions are stored in the `roles.permissions` JSON column so tenants can create custom roles without a code deployment.

**Money** is always stored as **poisha** (integer, 1 BDT = 100 poisha) to avoid floating-point drift.

---

## Tech stack

| Layer | Choice |
|-------|--------|
| Framework | Laravel 12 (streamlined bootstrap, no `Kernel.php`) |
| Runtime | PHP 8.4 (strict types, readonly properties, enums) |
| Auth | `php-open-source-saver/jwt-auth` v2 — HS256 JWT + rotating opaque refresh tokens |
| 2FA | `pragmarx/google2fa` TOTP + encrypted recovery codes |
| Database | MySQL 8.0 (primary), SQLite in-memory (tests) |
| Cache / Queue | Redis (Laravel Cache + Queue drivers) |
| Tests | Pest v3 + `pestphp/pest-plugin-laravel` |
| Container | Docker Compose (php-fpm 8.4, Nginx, MySQL 8, Redis 7) |

---

## Prerequisites

| Tool | Minimum version |
|------|----------------|
| Docker | 24+ |
| Docker Compose | v2 (plugin or standalone) |
| Git | any recent |

For **local development without Docker** you additionally need:

- PHP 8.4 with extensions: `pdo_mysql`, `pdo_sqlite`, `redis`, `bcmath`, `mbstring`, `json`, `openssl`
- Composer 2.7+
- MySQL 8.0 (or MariaDB 10.11+)
- Redis 7+

---

## Quick start (Docker)

```bash
# 1. Clone
git clone <repo-url> coldchain-ems
cd coldchain-ems/backend

# 2. Copy environment
cp .env.example .env

# 3. Build and start services (detached)
docker compose up -d --build

# 4. Wait ~10 s for MySQL to initialise, then:
docker compose exec app php artisan migrate --seed

# 5. Verify
curl http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"owner@arcturus.test","password":"password"}'
```

The API is now reachable at **http://localhost:8080/api**.

---

## Manual / local setup

```bash
# Install PHP dependencies
composer install

# Copy & edit environment
cp .env.example .env
# Edit DB_*, REDIS_*, JWT_SECRET …

# Generate application key
php artisan key:generate

# Run migrations and seed
php artisan migrate --seed

# Start the dev server
php artisan serve          # http://localhost:8000
# or use php-fpm behind nginx for production-like behaviour
```

---

## Environment variables

All variables are documented in `.env.example`. The most critical ones:

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_ENV` | `local` / `production` | `local` |
| `APP_KEY` | Laravel encryption key (generated) | `base64:…` |
| `DB_HOST` | MySQL host | `mysql` |
| `DB_DATABASE` | MySQL database name | `coldchain` |
| `DB_USERNAME` / `DB_PASSWORD` | MySQL credentials | `coldchain` / `secret` |
| `REDIS_HOST` | Redis host | `redis` |
| `JWT_SECRET` | HMAC secret for JWT signing — **must be at least 32 chars** | `change-me-…` |
| `JWT_TTL` | Access-token TTL in minutes | `15` |
| `JWT_REFRESH_TTL` | Refresh-token TTL in minutes | `20160` (14 days) |
| `QUEUE_CONNECTION` | `redis` in production, `sync` for local | `redis` |

---

## Database & seeding

### Migrations

```bash
php artisan migrate            # run all pending migrations
php artisan migrate:fresh      # drop all tables and re-run
php artisan migrate:status     # see migration state
```

### Seeding

```bash
php artisan db:seed                         # RoleSeeder + DemoSeeder (non-production)
php artisan db:seed --class=RoleSeeder      # refresh system roles for all tenants
php artisan db:seed --class=DemoSeeder      # demo data only (dev / staging)
```

**RoleSeeder** is idempotent (`updateOrCreate` on tenant_id + slug). Run it after adding new permissions to the `Permission` enum to refresh every tenant's role records.

### Schema overview

```
tenants                      # cold-storage operators
  └── branches               # physical facilities (per-tenant)
       └── chambers          # temperature-controlled rooms
            └── storage_units# pallet positions / racks / bins

users                        # staff (tenant-scoped or platform-admin)
roles                        # permission bundles (system or custom)
role_user                    # user ↔ role pivot
branch_user                  # user ↔ branch restriction pivot

customers                    # depositors (also portal login principals)
products                     # commodity catalogue

stock_lots                   # batches in storage (live quantity)
stock_movements              # append-only ledger (all in/out/transfer/adjust)

rate_plans                   # tariff definitions (per-kg/day, per-slot/month…)
invoices                     # issued charges (draft → issued → paid / void)
invoice_lines                # line items
payments                     # money received
payment_allocations          # payment ↔ invoice split
```

---

## Running tests

```bash
# Using Docker (recommended)
docker compose exec app ./vendor/bin/pest

# Locally (SQLite in-memory, no external DB required)
./vendor/bin/pest

# With coverage (requires Xdebug or PCOV)
./vendor/bin/pest --coverage

# Run a specific suite
./vendor/bin/pest tests/Feature/Auth
./vendor/bin/pest tests/Feature/Billing
./vendor/bin/pest tests/Feature/Inventory
./vendor/bin/pest tests/Feature/Rbac

# Run a single test
./vendor/bin/pest --filter "operator can view stock lots"
```

Tests use `RefreshDatabase` (SQLite `:memory:`) so they are fast and isolated with no external service dependencies.

### Test matrix

| Suite | File | What it covers |
|-------|------|----------------|
| Auth | `Feature/Auth/LoginTest.php` | Login, refresh-token rotation, logout, /me |
| Isolation | `Feature/Auth/TenantIsolationTest.php` | Cross-tenant data cannot leak via list/show/mutate |
| RBAC | `Feature/Rbac/PermissionMatrixTest.php` | Each role has exactly the right permissions |
| Inventory | `Feature/Inventory/StockTest.php` | Intake, partial release, adjustment, movements |
| Billing | `Feature/Billing/InvoiceTest.php` | Draft → issue → void, lines, payments, balance sync |

---

## API overview

Full spec: [`docs/openapi.yaml`](docs/openapi.yaml)

**Base URL:** `/api/v1`

**Authentication:** `Authorization: Bearer <access_token>`  
**Branch context:** `X-Branch-Id: <branch_id>` (required for all tenant-scoped operations)

### Endpoints at a glance

```
POST   /auth/login                    — obtain tokens (public)
POST   /auth/refresh                  — rotate refresh token (public)
GET    /auth/me                       — current user
POST   /auth/logout                   — revoke tokens

GET    /branches                      — list branches (physical facilities)
POST   /branches                      — create branch
GET    /branches/{id}                 — show branch
PUT    /branches/{id}                 — update branch
DELETE /branches/{id}                 — delete branch (409 if it still has chambers)

GET    /products                      — list commodity catalogue
POST   /products                      — create product
GET    /products/{id}                 — show product
PUT    /products/{id}                 — update product
DELETE /products/{id}                 — delete product

GET    /customers                     — list depositors
POST   /customers                     — create depositor
GET    /customers/{id}                — show depositor
PUT    /customers/{id}                — update depositor
DELETE /customers/{id}                — delete depositor

GET    /chambers                      — list chambers
POST   /chambers                      — create chamber
GET    /chambers/{id}                 — show chamber
PUT    /chambers/{id}                 — update chamber
DELETE /chambers/{id}                 — delete chamber

GET    /storage-units                 — list storage units
POST   /storage-units                 — create storage unit
GET    /storage-units/{id}            — show storage unit
PUT    /storage-units/{id}            — update storage unit
DELETE /storage-units/{id}            — delete storage unit

GET    /stock-lots                    — list lots (filterable by status/customer)
POST   /stock-lots                    — intake (stock in)
GET    /stock-lots/{id}               — show lot + latest movement
GET    /stock-lots/{id}/movements     — movement ledger (paginated)
POST   /stock-lots/{id}/release       — stock out (partial or full)
POST   /stock-lots/{id}/adjust        — positive/negative quantity correction
POST   /stock-lots/{id}/transfer      — relocate to another chamber/unit

GET    /rate-plans                    — list tariffs
POST   /rate-plans                    — create tariff
GET    /rate-plans/{id}               — show tariff
PUT    /rate-plans/{id}               — update tariff
DELETE /rate-plans/{id}               — delete tariff

GET    /invoices                      — list invoices (filterable by status/customer/date)
POST   /invoices                      — create draft invoice
GET    /invoices/{id}                 — show invoice with lines
PUT    /invoices/{id}                 — update draft fields (dates, notes, discount)
DELETE /invoices/{id}                 — delete draft invoice
POST   /invoices/{id}/issue           — issue invoice (draft → issued)
POST   /invoices/{id}/void            — void invoice
POST   /invoices/{id}/lines           — add line to draft
PUT    /invoices/{id}/lines/{lineId}  — update line on draft
DELETE /invoices/{id}/lines/{lineId}  — remove line from draft

GET    /payments                      — list payments
POST   /payments                      — record payment (with optional allocations)
GET    /payments/{id}                 — show payment + allocations
POST   /payments/{id}/allocate        — apply additional allocations to existing payment
```

### Common response envelope

**Success (list)**
```json
{
  "data": [ { … } ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta": { "current_page": 1, "per_page": 20, "total": 45 }
}
```

**Success (single)**
```json
{ "data": { … } }
```

**Error**
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The quantity field is required.",
    "details": { "quantity": ["The quantity field is required."] },
    "request_id": "01J…"
  }
}
```

### HTTP status codes

| Status | Meaning |
|--------|---------|
| 200 | OK |
| 201 | Created |
| 401 | Unauthenticated / token invalid or expired |
| 403 | Permission denied (`permission:xxx` gate failed) |
| 404 | Record not found (or belongs to another tenant) |
| 409 | Conflict (e.g. delete issued invoice, void already-voided) |
| 422 | Validation error |
| 429 | Rate limit exceeded |

### Rate limits

| Limiter | Limit | Scope |
|---------|-------|-------|
| `auth` | 10 req / min | per email + IP |
| `api` | 120 req / min | per user ID + IP |

---

## Demo credentials

After running `php artisan db:seed` (non-production):

| Role | Email | Password |
|------|-------|----------|
| Platform Admin | `admin@coldchain.test` | `password` |
| Tenant Owner | `owner@arcturus.test` | `password` |
| Admin | `admin@arcturus.test` | `password` |
| Branch Manager | `manager@arcturus.test` | `password` |
| Operator | `operator@arcturus.test` | `password` |
| Accountant | `accountant@arcturus.test` | `password` |
| Technician | `tech@arcturus.test` | `password` |
| Auditor | `auditor@arcturus.test` | `password` |

Demo tenant: **Arcturus Cold Storage Ltd.** (`arcturus-cold`)  
Branches: **DHK-01** (Dhaka Central) · **CTG-01** (Chittagong Port)  
5 customers · 8 products · 4 chambers · 24 storage units · 8 stock lots · 5 invoices

---

## Project structure

```
backend/
├── app/
│   ├── Enums/                   # Permission, Role, InvoiceStatus, StockMovementType
│   ├── Http/
│   │   ├── Controllers/Api/     # Auth, Billing (Invoice/Payment/RatePlan), Stock, Ops
│   │   ├── Middleware/          # ResolveTenant, EnsureTenantContext, RequirePermission
│   │   └── Requests/            # FormRequest classes with RBAC-aware validation
│   ├── Models/
│   │   ├── Concerns/            # BelongsToTenant, BelongsToBranch traits
│   │   └── Scopes/              # TenantScope, BranchScope
│   ├── Services/
│   │   ├── AuditLogger.php
│   │   ├── Billing/BillingService.php
│   │   └── Inventory/StockService.php
│   └── Support/
│       ├── ApiError.php         # Uniform JSON error envelope
│       └── TenantContext.php    # Per-request tenant/permission/branch state
├── database/
│   ├── factories/               # Model factories (Tenant, Branch, User, Customer, …)
│   ├── migrations/              # DDL — 6 migration files
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── RoleSeeder.php       # Idempotent system-role upsert
│       └── DemoSeeder.php       # Realistic demo tenant + data
├── docs/
│   └── openapi.yaml             # OpenAPI 3.1 specification
├── routes/
│   └── api.php                  # All REST routes with permission middleware
└── tests/
    ├── Pest.php                 # Helpers: createTenant(), createUser(), apiHeaders()
    └── Feature/
        ├── Auth/
        ├── Billing/
        ├── Inventory/
        └── Rbac/
```

---

## Key design decisions

### JWT + rotating refresh tokens
Access tokens (15 min TTL) are short-lived JWTs. Refresh tokens are 80-char opaque secrets stored as SHA-256 hashes. Tokens belong to a **family**; consuming a revoked token in the same family triggers revocation of the entire family (reuse detection).

### Money as poisha integers
All monetary values are stored as `BIGINT` poisha (1 BDT = 100 poisha). No floats, no rounding surprises. Columns are suffixed `_poisha` to make the unit explicit.

### Append-only stock ledger
`stock_movements` is never updated or deleted. Each event (stock_in, stock_out, adjustment, transfer) appends a row with a `balance_after` snapshot. The lot's live `quantity` is maintained in sync on the lot row for fast reads; movements are the audit trail.

### Database-authoritative RBAC
Permission slugs are stored in `roles.permissions` JSON column. Middleware reads from DB (via `TenantContext` booted by `ResolveTenant`) on every request, so role changes take effect immediately without a token re-issue or cache flush. The JWT embeds roles as a performance hint only.

### Concurrency
All multi-step writes (intake, release, payment allocation) run inside `DB::transaction()` with `lockForUpdate()` on the rows being modified to prevent race conditions under concurrent API calls.
