# Employee Wallet & Payroll Integration API

Laravel backend take-home implementation for employee wallets, simulated payroll events, simulated bank withdrawals, and auditable wallet ledger history.

## Stack

- Laravel 13
- PHP 8.4
- PostgreSQL target database
- REST API
- PHPUnit feature and domain tests

## Architecture

The application is organized as a modular Laravel monolith under `app/Domain`.

- `Employees`: employee identity and payroll reference.
- `Wallets`: wallet balances and append-only ledger entries.
- `Payroll`: simulated inbound payroll provider events.
- `Banking`: withdrawal requests and simulated bank payment callbacks.
- `Shared`: idempotency and external event primitives.

Controllers are intentionally thin. Validation lives in Form Requests, response shape lives in API Resources, and business workflows live in Actions/Services.

## Money Safety

All wallet balance changes go through `App\Domain\Wallets\Services\WalletLedgerService`.

The service guarantees:

- no direct controller balance mutation
- `DB::transaction()` around each money movement
- `lockForUpdate()` on wallet rows before balance changes
- decimal string arithmetic using BCMath
- no negative available or reserved balances
- same-currency validation
- wallet active-state validation
- idempotent operations through `idempotency_records`
- append-only `wallet_ledger_entries` with before/after available and reserved balances

Pending withdrawals move money from `available_balance` to `reserved_balance`. Reserved money is not spendable. Bank success captures reserved funds; bank failure releases them.

## Concurrency Approach

The production safety model relies on PostgreSQL row-level locks:

1. Start a database transaction.
2. Select the affected wallet row using `lockForUpdate()`.
3. Re-check available/reserved balances after the lock is acquired.
4. Apply the balance mutation.
5. Insert the ledger entry inside the same transaction.
6. Commit.

For transfers, both wallet rows are locked in ascending wallet ID order to reduce deadlock risk.

The automated test suite runs on SQLite for speed, so it cannot faithfully simulate PostgreSQL concurrent row locking. The critical concurrency behavior is therefore documented here and implemented in `WalletLedgerService`; in a production CI pipeline, an additional PostgreSQL integration test should run two parallel workers attempting debits against the same wallet and assert that only one can consume the available balance.

## Main Endpoints

```http
GET  /api/v1/health

POST /api/v1/employees
GET  /api/v1/employees
GET  /api/v1/employees/{employee}

POST /api/v1/employees/{employee}/wallets
GET  /api/v1/employees/{employee}/wallets
GET  /api/v1/wallets
GET  /api/v1/wallets/{wallet}
GET  /api/v1/wallets/{wallet}/ledger-entries

POST /api/v1/payroll/events

POST /api/v1/wallets/{wallet}/withdrawals
POST /api/v1/integrations/bank/callbacks
```

## Withdrawal Example

```http
POST /api/v1/wallets/{wallet}/withdrawals
Idempotency-Key: withdrawal-user-123-001
```

```json
{
  "amount": "250.00",
  "currency": "USD"
}
```

## Bank Callback Example

```http
POST /api/v1/integrations/bank/callbacks
```

```json
{
  "provider_reference": "bank_pay_123",
  "status": "succeeded",
  "occurred_at": "2026-05-02T12:00:00Z"
}
```

## Tests

Run:

```bash
php artisan test
```

The test suite covers employee/wallet APIs, payroll event idempotency, salary credits, bank withdrawal reserve/capture/release, duplicate callbacks, transaction history, and core wallet ledger behavior.
