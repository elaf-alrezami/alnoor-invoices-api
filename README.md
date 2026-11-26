# Al-Noor Grocery — Invoices API

A clean, lightweight RESTful API built with **Laravel** and **SQLite** for **Al-Noor Grocery** to create, calculate, persist, retrieve, search, and paginate customer invoices. All line totals, subtotals, 15% tax (`YER`), and grand totals are computed strictly on the server after validating incoming payloads.

---

## Requirements

- **PHP 8.3+** (with the `pdo_sqlite` extension enabled)
- **Composer**

---

## Quick Setup & Run

Run the following commands from your terminal (works on Linux, macOS, and Windows):

```bash
# 1. Clone the repository
git clone https://github.com/elaf-alrezami/alnoor-invoices-api.git && cd alnoor-invoices-api

# 2. Install PHP dependencies
composer install

# 3. Create environment file (Windows CMD: copy .env.example .env)
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Create the SQLite database file
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"

# 6. Run database migrations
php artisan migrate

# 7. Start the local development server (http://127.0.0.1:8000)
php artisan serve
```

> **Configuration defaults:** `config/invoice.php` defaults to `INVOICE_TAX_RATE=0.15` (15%) and `INVOICE_CURRENCY=YER`. `.env.example` configures `DB_CONNECTION=sqlite` with `DB_FOREIGN_KEYS=true`.

---

## Running Automated Tests

```bash
php artisan test
```

Tests run against an in-memory SQLite database (`:memory:`) via `phpunit.xml` without touching `database/database.sqlite`. The feature suite (`tests/Feature/InvoiceApiTest.php`) verifies:

- Exact server-side calculations and minor-unit persistence for all sample invoices.
- Silently ignoring client-supplied summary totals (`subtotal`, `tax`, `total`, `line_total`).
- Fractional price/quantity calculations with half-up rounding (`PHP_ROUND_HALF_UP`).
- `422 Unprocessable Entity` validation rejections with **zero** database writes for invalid payloads (negative/zero qty, missing/zero/negative price, empty `items`, missing `customer`).
- Arabic customer search (`?search=` and `?customer=`), newest-first ordering, pagination metadata, and `per_page` bounds sanitization (`1..100`, defaulting to `15`).
- Forced JSON `422` and `404` responses on `/api/*` routes even when the `Accept: application/json` header is omitted.

---

## Database Schema Summary

All monetary values are stored as **integer minor units** (`amount × 100`) to prevent SQLite decimal and floating-point precision drift, and converted back to clean JSON numbers in API Resources.

### `invoices`

| Column | Type | Notes |
| :--- | :--- | :--- |
| `id` | `id` (PK) | Auto-increment invoice number |
| `customer` | `string(255)` | Indexed for customer search |
| `subtotal` | `unsignedBigInteger` | Stored in minor units (`value * 100`) |
| `tax` | `unsignedBigInteger` | Stored in minor units (`value * 100`) |
| `total` | `unsignedBigInteger` | Stored in minor units (`value * 100`) |
| `tax_rate` | `decimal(5, 4)` | Snapshots applied rate (default `0.1500`) |
| `currency` | `string(3)` | Default `'YER'` |
| `created_at` / `updated_at` | `timestamps` | Standard Laravel timestamps |

### `invoice_items`

| Column | Type | Notes |
| :--- | :--- | :--- |
| `id` | `id` (PK) | Auto-increment item ID |
| `invoice_id` | `foreignId` | References `invoices.id` (`ON DELETE CASCADE`) |
| `name` | `string(255)` | Free-form item description |
| `qty` | `decimal(10, 3)` | Supports whole and fractional quantities |
| `price` | `unsignedBigInteger` | Unit price in minor units (`value * 100`) |
| `line_total` | `unsignedBigInteger` | Persisted line total in minor units (`value * 100`) |
| `created_at` / `updated_at` | `timestamps` | Standard Laravel timestamps |

---

## API Endpoints & `curl` Examples

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `POST` | `/api/invoices` | Validate payload, calculate totals server-side, and create an invoice |
| `GET` | `/api/invoices/{id}` | Retrieve a single invoice with its line items |
| `GET` | `/api/invoices` | List invoices (newest first) with customer search and pagination |

### 1. Create Invoice (`POST /api/invoices`) — Valid Request

```bash
curl -X POST http://127.0.0.1:8000/api/invoices \
  -H "Content-Type: application/json" \
  -d '{
    "customer": "محمد الحميري",
    "items": [
      { "name": "رز بسمتي 10 كيلو", "qty": 1, "price": 18000 },
      { "name": "سكر 5 كيلو", "qty": 2, "price": 6500 }
    ]
  }'
```

**Response (`201 Created`):**

```json
{
  "id": 1,
  "customer": "محمد الحميري",
  "currency": "YER",
  "tax_rate": 0.15,
  "subtotal": 31000,
  "tax": 4650,
  "total": 35650,
  "items": [
    {
      "id": 1,
      "name": "رز بسمتي 10 كيلو",
      "qty": 1,
      "price": 18000,
      "line_total": 18000
    },
    {
      "id": 2,
      "name": "سكر 5 كيلو",
      "qty": 2,
      "price": 6500,
      "line_total": 13000
    }
  ],
  "created_at": "2026-11-26T10:00:00.000000Z"
}
```

### 2. Create Invoice — Client-Supplied Totals Ignored

```bash
curl -X POST http://127.0.0.1:8000/api/invoices \
  -H "Content-Type: application/json" \
  -d '{
    "customer": "خالد العولقي",
    "total": 1,
    "items": [
      { "name": "حليب نيدو 900 جرام", "qty": 1, "price": 9800 }
    ]
  }'
```

The server ignores `"total": 1`, computes the authoritative totals, and responds with `201 Created` (`subtotal: 9800`, `tax: 1470`, `total: 11270`).

### 3. Create Invoice — Invalid Payload (`422 Unprocessable Entity`)

```bash
curl -X POST http://127.0.0.1:8000/api/invoices \
  -H "Content-Type: application/json" \
  -d '{
    "customer": "سارة المقطري",
    "items": [
      { "name": "زيت طبخ 1.5 لتر", "qty": -2, "price": 4500 }
    ]
  }'
```

**Response (`422 Unprocessable Entity` — nothing written to the database):**

```json
{
  "message": "Each item quantity must be greater than zero.",
  "errors": {
    "items.0.qty": [
      "Each item quantity must be greater than zero."
    ]
  }
}
```

### 4. Show Invoice by ID (`GET /api/invoices/{id}`)

```bash
curl http://127.0.0.1:8000/api/invoices/1
```

Returns `200 OK` with the same top-level JSON structure as `POST /api/invoices`.

**Non-existent ID (`GET /api/invoices/99999` -> `404 Not Found`):**

```json
{
  "message": "Invoice not found."
}
```

### 5. List Invoices with Search & Pagination (`GET /api/invoices`)

Query parameters:
- `search` (or `customer`): optional partial match on customer name.
- `per_page`: optional integer (default `15`, clamped between `1` and `100`).
- `page`: optional integer (default `1`).

```bash
curl -G http://127.0.0.1:8000/api/invoices \
  --data-urlencode "search=فاطمة" \
  --data "per_page=10" \
  --data "page=1"
```

**Response (`200 OK`):**

```json
{
  "data": [
    {
      "id": 2,
      "customer": "فاطمة العريقي",
      "currency": "YER",
      "tax_rate": 0.15,
      "subtotal": 9300,
      "tax": 1395,
      "total": 10695,
      "items": [
        {
          "id": 3,
          "name": "شاهي 450 جرام",
          "qty": 3,
          "price": 3100,
          "line_total": 9300
        }
      ],
      "created_at": "2026-11-26T10:05:00.000000Z"
    }
  ],
  "links": {
    "first": "http://127.0.0.1:8000/api/invoices?page=1",
    "last": "http://127.0.0.1:8000/api/invoices?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 10,
    "to": 1,
    "total": 1
  }
}
```

---

## Design Decisions

- **Minor-Unit Integer Arithmetic (`× 100`):** Prices, line totals, subtotals, tax, and totals are calculated and stored as integer minor units using `PHP_ROUND_HALF_UP`, guaranteeing deterministic precision without requiring optional extensions like `bcmath`.
- **Atomic Persistence:** Invoice header creation and bulk item insertion run inside `DB::transaction()` in `InvoiceService` so partial writes or orphan rows can never occur.
- **Forced JSON API Responses:** `bootstrap/app.php` forces JSON rendering for all `/api/*` routes regardless of the caller's `Accept` header and normalizes missing invoices to a clean `404` JSON payload.
- **Preventing Duplicate Submissions (Idempotency-Key Pattern):** In production, clients should generate a UUID per checkout attempt in an `Idempotency-Key` header persisted with a database-level `UNIQUE` constraint so retried or concurrent requests return the existing invoice instead of creating duplicates, complemented by disabling the submit button while in flight.
