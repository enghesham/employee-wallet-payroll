# Employee Wallet & Payroll Integration API

Backend take-home implementation for an Employee Wallet and Payroll Integration system. The service manages employees, wallets, auditable wallet ledger entries, simulated payroll events, withdrawal requests, and simulated asynchronous bank callbacks.

The implementation focuses on financial correctness, idempotency, explainable database history, and clean Laravel architecture without over-engineering.

## Tech Stack

- PHP 8.4
- Laravel 13
- PostgreSQL
- REST API
- PHPUnit
- Laravel Pint

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure PostgreSQL in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=employee_wallet_payroll
DB_USERNAME=postgres
DB_PASSWORD=
```

Set demo provider tokens for local testing:

```env
PAYROLL_PROVIDER_TOKEN=local-payroll-token
BANK_PROVIDER_TOKEN=local-bank-token
```

Run the app:

```bash
php artisan migrate --seed
php artisan serve
```

The default seeder includes optional demo/local review data:

- Demo employee: `demo.employee@example.com` / `EMP-DEMO-001`
- Salary wallet in `USD` funded with `1000.00`
- Savings wallet in `USD` funded with `200.00`

The opening balances are created through `WalletLedgerService`, so ledger entries and idempotency records are created just like normal money movements. To create or refresh the demo records after migrations have already run:

```bash
php artisan db:seed
```

Run tests:

```bash
php artisan test
```

## API Docs UI

Start the Laravel server, then open:

```text
http://127.0.0.1:8000/docs
```

The UI uses Swagger UI with the local OpenAPI spec at:

```text
public/docs/openapi.json
```

Swagger UI assets are loaded from a CDN, so the browser needs internet access for the documentation UI to render.

## Architecture

The application is a modular Laravel monolith. Business workflows live in domain actions/services, while controllers stay thin.

```text
Clients / Postman / Swagger UI
          |
          v
Laravel HTTP Layer
Routes -> Form Requests -> Thin Controllers -> API Resources
          |
          v
Domain Actions / Services
Employees | Payroll | Banking | Wallets | Shared
          |
          v
WalletLedgerService
centralized credits, debits, reserves, releases, captures, transfers
          |
          v
Eloquent Models + PostgreSQL
employees, wallets, wallet_ledger_entries, payroll_events,
withdrawal_requests, bank_payment_requests, idempotency_records
```

Integration flow:

```text
Payroll Provider Stub
   -> Payroll Event API
   -> Payroll Actions
   -> WalletLedgerService
   -> Salary wallet ledger credit

Employee Withdrawal Request
   -> Banking Action
   -> WalletLedgerService reserve
   -> Bank Payment Request
   -> Bank Callback
   -> WalletLedgerService capture or release
```

Main modules:

- `Employees`: employee profile, payroll external reference, and status.
- `Wallets`: wallet balances, wallet types, and append-only ledger entries.
- `Payroll`: simulated payroll provider events such as onboarding and salary runs.
- `Banking`: withdrawal requests, bank payment requests, and async bank callbacks.
- `Shared`: idempotency and shared infrastructure concepts.

## Design Decisions

### Authentication Scope

For the take-home scope, full end-user authentication is intentionally omitted to keep the focus on wallet correctness and integrations. Public employee and wallet endpoints are therefore left unguarded.

Simulated provider endpoints use a simple bearer-token middleware:

- `PAYROLL_PROVIDER_TOKEN` protects `POST /api/v1/payroll/events`
- `BANK_PROVIDER_TOKEN` protects `POST /api/v1/integrations/bank/callbacks`

In production, these endpoints would be protected using service-to-service authentication, webhook signatures, mTLS, IP allowlists, or Sanctum/JWT depending on the consuming clients.

### Ledger Entries

Wallet balances are supported by `wallet_ledger_entries`. Every money movement records:

- amount and currency
- direction and type
- available/reserved balance before and after
- source type/source id
- reason, reference, metadata, and idempotency key

This makes financial history explainable from the database alone.

### Row Locking

All balance mutations go through `WalletLedgerService`. The service wraps changes in `DB::transaction()` and locks wallet rows with `lockForUpdate()` before checking or changing balances. This prevents race conditions where concurrent withdrawals could overspend the same wallet.

Transfers lock both wallet rows in ascending wallet ID order to reduce deadlock risk.

### Idempotency

External and retryable operations use idempotency keys so repeated requests do not duplicate money movement.

Examples:

- Withdrawal requests use `Idempotency-Key`.
- Payroll salary deposits use deterministic keys like `payroll:{provider_event_id}:{employee_external_reference}:{period}`.
- Bank callbacks use provider reference based keys.

### Pending Withdrawals

Withdrawals do not immediately remove money from the wallet. The system moves funds from `available_balance` to `reserved_balance`.

- Bank success: reserved funds are captured.
- Bank failure: reserved funds are released back to available balance.

Reserved funds are not spendable.

### Payroll Simulation

Payroll events are accepted through the API and stored before processing. Supported events:

- `employee.onboarded`
- `employee.status_changed`
- `salary_run.completed`

Duplicate `provider_event_id` values are idempotent. Salary runs credit the employee salary wallet through `WalletLedgerService`.

If a salary wallet does not exist, the system creates one automatically for the salary currency. If the employee cannot be resolved, the event is stored as failed with a failure reason.

Failed payroll events are not automatically retried through the inbound event endpoint. They can be retried explicitly through `POST /api/v1/integrations/payroll/events/{payrollEvent}/retry`, which only accepts events currently in `failed` status. Processing and processed events return a conflict response.

### Bank Simulation

The bank integration is local and stubbed. A withdrawal creates a `withdrawal_request` and a `bank_payment_request`. The simulated bank callback endpoint resolves payment requests by provider reference, not internal database ID.

This is closer to production behavior because real providers do not know internal IDs.

## API Overview

Base prefix:

```http
/api/v1
```

### Health

```http
GET /health
```

### Employees

```http
POST /employees
GET  /employees
GET  /employees/{employee}
```

Filters:

- `status`
- `search`
- `per_page`

### Wallets

```http
POST /employees/{employee}/wallets
GET  /employees/{employee}/wallets
GET  /wallets
GET  /wallets/{wallet}
POST /wallets/{wallet}/transfers
```

Filters:

- `currency`
- `type`
- `status`
- `per_page`

### Payroll Events

Requires:

```http
Authorization: Bearer <PAYROLL_PROVIDER_TOKEN>
```

```http
POST /payroll/events
POST /integrations/payroll/events/{payrollEvent}/retry
```

Example salary event:

```json
{
  "provider_event_id": "salary-run-2026-05-emp-123",
  "event_type": "salary_run.completed",
  "payload": {
    "employee_external_reference": "emp-123",
    "period": "2026-05",
    "amount": "2500.00",
    "currency": "USD"
  }
}
```

### Withdrawals

```http
POST /wallets/{wallet}/withdrawals
Idempotency-Key: withdrawal-user-123-001
```

```json
{
  "amount": "250.00",
  "currency": "USD"
}
```

### Wallet Transfers

Transfers are allowed between two different active wallets owned by the same employee and in the same currency.

```http
POST /wallets/{wallet}/transfers
Idempotency-Key: transfer-demo-001
```

```json
{
  "to_wallet_id": 2,
  "amount": "125.00",
  "currency": "USD",
  "reason": "Move part of salary to savings"
}
```

### Bank Callbacks

Requires:

```http
Authorization: Bearer <BANK_PROVIDER_TOKEN>
```

```http
POST /integrations/bank/callbacks
```

Success:

```json
{
  "provider_reference": "bank_pay_123",
  "status": "succeeded",
  "occurred_at": "2026-05-02T12:00:00Z"
}
```

Failure:

```json
{
  "provider_reference": "bank_pay_123",
  "status": "failed",
  "occurred_at": "2026-05-02T12:00:00Z",
  "failure_reason": "Rejected by simulated bank"
}
```

### Transaction History

```http
GET /wallets/{wallet}/ledger-entries
```

Default order is newest first.

Filters:

- `type`
- `source_type`
- `date_from`
- `date_to`
- `amount_min`
- `amount_max`
- `per_page`

## Assumptions

- Transfers are same-currency only.
- Transfers are currently limited to wallets owned by the same employee. Employer-level wallet ownership is not modeled in this take-home scope.
- No FX conversion is implemented.
- No real payroll provider or bank provider is called.
- Salary wallets are auto-created when a valid salary event arrives for an existing employee.
- Employee onboarding events create or update employees by external reference.
- Employee status changes are accepted from payroll events and update the local employee status.
- Full end-user authentication and authorization are out of scope for this take-home task.
- Tests run on SQLite for speed; PostgreSQL is the intended runtime database.

## Concurrency Testing Note

Concurrency safety relies on database transactions and row-level locking using `SELECT ... FOR UPDATE`. SQLite does not fully represent this behavior, so production concurrency should be validated against PostgreSQL/MySQL.

The implementation uses PostgreSQL row-level locking through `lockForUpdate()` inside transactions. SQLite-based tests cannot faithfully prove concurrent row locking behavior.

In a production CI setup, I would add a PostgreSQL integration test that starts two parallel workers attempting to debit or withdraw from the same wallet and asserts only one operation can consume the available balance.

## Improvements With More Time

Redis, message brokers, and background jobs are intentionally not required for the current scope. The core flows are processed synchronously to keep financial behavior easy to reason about. In production, queues would be introduced for provider event processing, retry backoff, reconciliation jobs, notifications, and outbox-driven integrations once those needs justify the operational complexity.

- Queue payroll event processing and bank callback handling.
- Add full queues with retries, dead-letter handling, and operational monitoring.
- Add an outbox pattern for reliable provider communication.
- Build real provider adapters for payroll and banking.
- Add full HMAC webhook signatures.
- Publish an OpenAPI specification.
- Add PostgreSQL-backed concurrency tests.
- Add structured logging, correlation IDs, and tracing.
- Add reconciliation reports for payroll, bank payments, and wallet ledger entries.
- Add scheduled reconciliation jobs.
- Add an admin dashboard.
- Introduce a multi-tenant employer/company model.
- Add FX support with explicit conversion ledger entries.
- Add richer operational metrics and alerting.
