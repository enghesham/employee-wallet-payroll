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

Run the app:

```bash
php artisan migrate --seed
php artisan serve
```

Run tests:

```bash
php artisan test
```

## Architecture

The application is a modular Laravel monolith. Business workflows live in domain actions/services, while controllers stay thin.

```text
HTTP Controllers
      |
Form Requests -> Actions / Services -> Eloquent Models -> PostgreSQL
      |
API Resources

Domain Modules:
Employees | Wallets | Payroll | Banking | Shared
```

Main modules:

- `Employees`: employee profile, payroll external reference, and status.
- `Wallets`: wallet balances, wallet types, and append-only ledger entries.
- `Payroll`: simulated payroll provider events such as onboarding and salary runs.
- `Banking`: withdrawal requests, bank payment requests, and async bank callbacks.
- `Shared`: idempotency and shared infrastructure concepts.

## Design Decisions

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
```

Filters:

- `currency`
- `type`
- `status`
- `per_page`

### Payroll Events

```http
POST /payroll/events
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

### Bank Callbacks

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
- No FX conversion is implemented.
- No real payroll provider or bank provider is called.
- Salary wallets are auto-created when a valid salary event arrives for an existing employee.
- Employee onboarding events create or update employees by external reference.
- Employee status changes are accepted from payroll events and update the local employee status.
- Authentication and authorization are out of scope for this take-home task.
- Tests run on SQLite for speed; PostgreSQL is the intended runtime database.

## Concurrency Testing Note

The implementation uses PostgreSQL row-level locking through `lockForUpdate()` inside transactions. SQLite-based tests cannot faithfully prove concurrent row locking behavior.

In a production CI setup, I would add a PostgreSQL integration test that starts two parallel workers attempting to debit or withdraw from the same wallet and asserts only one operation can consume the available balance.

## Improvements With More Time

- Queue payroll event processing and bank callback handling.
- Add an outbox pattern for reliable provider communication.
- Build real provider adapters for payroll and banking.
- Add webhook signature verification.
- Add PostgreSQL-backed concurrency tests.
- Add structured logging, correlation IDs, and tracing.
- Add reconciliation reports for payroll, bank payments, and wallet ledger entries.
- Add an admin dashboard.
- Introduce a multi-tenant employer/company model.
- Add FX support with explicit conversion ledger entries.
- Add richer operational metrics and alerting.
