# Property Listings API

A small REST API for property listings, written as the Expert Listing backend
take-home: CRUD for listings plus a search endpoint that filters by type, price
range, bedroom count and distance from a point.

**Stack:** Laravel 13.33, PHP 8.4, MySQL 8 (SQLite works too), PHPUnit.

## What is here

| Method | URI | Description |
| --- | --- | --- |
| `GET` | `/api/v1/listings` | Paginated list, newest first |
| `POST` | `/api/v1/listings` | Create a listing |
| `GET` | `/api/v1/listings/{id}` | Fetch one listing |
| `PUT`/`PATCH` | `/api/v1/listings/{id}` | Update a listing (partial) |
| `DELETE` | `/api/v1/listings/{id}` | Delete a listing |
| `GET` | `/api/v1/listings/search` | List plus radius search |

## Requirements

- PHP 8.3 or newer, with the `pdo_mysql` extension (developed on PHP 8.4)
- Composer
- MySQL 8 / MariaDB 10.6, or SQLite

## Setup

```sh
# 1. Dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database
mysql -u root -e "CREATE DATABASE propapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 4. Schema and sample data
php artisan migrate --seed

# 5. Serve
php artisan serve
```

The API is then available at `http://localhost:8000/api/v1`. `.env.example`
assumes MySQL on `127.0.0.1:3306` with a passwordless `root` user; adjust the
`DB_*` values if your setup differs.

`--seed` creates a test user, five agents and 60 listings: 40 scattered around
Lagos and 20 around Abuja. That is enough data for the radius search to return
believable results.

### Running on SQLite instead

There is no hard dependency on MySQL. Point `DB_CONNECTION` at `sqlite` and
create the file:

```sh
touch database/database.sqlite
# .env: DB_CONNECTION=sqlite and DB_DATABASE=/absolute/path/to/database.sqlite
php artisan migrate --seed
```

The radius search keeps working because the missing trigonometry functions are
registered on the SQLite connection at runtime (see *How the radius search
works* below).

## Tests

```sh
php artisan test
```

The suite runs against in-memory SQLite, so it needs no database setup:

- `tests/Feature/Api/V1/ListingControllerTest` — listing behaviour through the
  HTTP layer: pagination and ordering, every filter, validation failures, 404s,
  create, update and delete.
- `tests/Feature/Api/V1/ListingSearchControllerTest` — radius filtering, the
  reported distance, distance ordering, combined filters, paginated radius
  results and incomplete-location validation.
- `tests/Feature/Api/V1/ErrorResponseTest` — the error contract: clean `404` and
  `405` payloads, `422` field errors, and the rule that framework internals only
  appear inside a `debug` block on `5xx` while `APP_DEBUG` is on.
- `tests/Unit/Services/Geo/GeoMathTest` — the spherical maths on its own:
  distance, bounding box, and the placeholder/binding pairing of the SQL
  expression.

## API

All responses are JSON. A single resource is wrapped in `data`; collections add
`links` and `meta` from the paginator.

### A listing

```json
{
  "data": {
    "id": 1,
    "title": "1 bedroom flat in Ajah",
    "description": "Sint quia aut excepturi dolorum rerum quis rerum.",
    "type": "shortlet",
    "address": "50820 Delores Spur",
    "price": 121306456,
    "bedrooms": 1,
    "location": { "latitude": 6.405289, "longitude": 3.441819 },
    "agent_id": 2,
    "created_at": "2026-09-25T11:19:54+00:00",
    "updated_at": "2026-09-25T11:19:54+00:00"
  }
}
```

`distance_km` appears only on results of a radius search.

### Creating a listing

```sh
curl -X POST http://localhost:8000/api/v1/listings \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
        "agent_id": 2,
        "title": "3 bedroom flat in Lekki",
        "description": "Serviced flat with a sea view.",
        "type": "rent",
        "address": "Admiralty Way, Lekki",
        "price": 2500000,
        "bedrooms": 3,
        "latitude": 6.4413,
        "longitude": 3.4721
      }'
```

Returns `201` with the created resource. `type` is one of `rent`, `sale` or
`shortlet`; `agent_id` must reference an existing user.

Updates accept any subset of the same fields (`PATCH` or `PUT`) and return `200`.
Deletes return `200` with a confirmation body so a client always gets a payload
back and does not have to treat "no content" as success by convention:

```json
{ "message": "Listing deleted successfully." }
```

### Search

`GET /api/v1/listings/search` accepts every filter the list endpoint does, plus
the location trio:

| Parameter | Type | Notes |
| --- | --- | --- |
| `type` | `rent` \| `sale` \| `shortlet` | Exact match |
| `min_price`, `max_price` | number | Inclusive; `max_price` must be at least `min_price` |
| `bedrooms` | integer | Exact match |
| `min_bedrooms` | integer | Lower bound, e.g. "3 or more" |
| `latitude`, `longitude` | number | Centre of the search |
| `radius` | number | Kilometres, greater than 0 and at most 1000 |
| `sort` | `newest` (default), `price_asc`, `price_desc`, `distance` | `distance` requires the location trio |
| `per_page` | integer | 1-100, default 15 |
| `page` | integer | Standard page number |

The three location parameters are all-or-nothing: supplying any one of them
requires the other two, otherwise the request fails validation with `422`.

```sh
curl -G http://localhost:8000/api/v1/listings/search \
  -H "Accept: application/json" \
  --data-urlencode "latitude=6.5" \
  --data-urlencode "longitude=3.4" \
  --data-urlencode "radius=20" \
  --data-urlencode "sort=distance" \
  --data-urlencode "per_page=2"
```

```json
{
  "data": [
    {
      "id": 12,
      "title": "1 bedroom terrace in Ikeja",
      "type": "shortlet",
      "price": 247054268,
      "bedrooms": 1,
      "location": { "latitude": 6.528941, "longitude": 3.414256 },
      "distance_km": 3.58,
      "agent_id": 3,
      "created_at": "2026-09-25T11:19:55+00:00",
      "updated_at": "2026-09-25T11:19:55+00:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 2, "total": 21 }
}
```

### Errors

Every failure under `/api` renders the same envelope, so a client never has to
branch on the error type to find the message:

```json
{ "message": "The requested resource was not found." }
```

Validation failures add an `errors` object keyed by field:

```json
{
  "message": "The selected type is invalid. (and 2 more errors)",
  "errors": {
    "type": ["The selected type is invalid."],
    "price": ["The price field is required."]
  }
}
```

| Status | When | Message |
| --- | --- | --- |
| `401` | Credentials are missing or invalid | `Unauthenticated.` |
| `403` | The action is not allowed | `This action is unauthorized.` |
| `404` | The id does not exist | `The requested resource was not found.` |
| `404` | The route does not exist | `The requested endpoint was not found.` |
| `405` | Wrong method for a known route | `The DELETE method is not supported...` |
| `422` | Validation failed | `The selected type is invalid.` |
| `429` | Rate limited | `Too Many Requests` |
| `500` | Unhandled failure | `Server Error.` |

Framework headers are preserved on the way out: `Allow` on a `405` and
`Retry-After` on a `429`.

Framework internals stay out of the response. A `5xx` carries a nested `debug`
object with the exception class, message, file and line, and only while
`APP_DEBUG=true`. The top-level shape is identical in every environment, and
stack traces always go to `storage/logs/laravel.log` instead of the client.

The mapping lives in `App\Exceptions\ApiExceptionRenderer`, registered in
`bootstrap/app.php`. `shouldRenderJsonWhen()` still forces JSON for any `api/*`
request even when the caller forgets the `Accept: application/json` header.