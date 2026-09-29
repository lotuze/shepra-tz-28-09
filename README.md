# Product Import

Full-stack XLSX product importer: Slim 4 and Doctrine ORM expose a JWT-protected
API, Symfony Messenger sends import jobs through RabbitMQ, and an Angular 20
standalone application uses NgRx for the product list. PostgreSQL stores the
catalog, import state, users, and per-user import rate limits.

## Clean start

```bash
git clone <repository-url>
cd shepra
cp .env.example .env
make build
make up
make migrate
make fixtures
```

- Frontend: http://localhost:4200
- Backend health: http://localhost:8080/api/health
- RabbitMQ management: http://localhost:15672

Demo login: `admin@example.com` / `ChangeMe123!` (development only).

Useful runtime commands: `make logs`, `make shell`, `make migrate`,
`make fixtures`, `make worker-logs`, `make worker-restart`, and `make down`.

## Quality checks

```bash
make test
make phpstan
make cs-check
make cs-fix
make openapi-validate
make schema-validate
```

`cs-check` is a read-only PHP CS Fixer check; `cs-fix` applies formatting.
The OpenAPI 3.0.3 document is at `docs/openapi.yaml`, with Redocly configuration
in `docs/redocly.yaml`.

GitHub Actions runs Composer validation, PHP CS Fixer, PHPStan level 5,
Doctrine migrations and schema validation, PHPUnit, Angular unit tests and
production build, and OpenAPI validation. CI uses PHP 8.3, Node.js 22, and
PostgreSQL 16; it does not deploy or publish images.

## Authentication and API

Authenticate first with `POST /api/auth/login`. Pass the returned token as
`Authorization: Bearer <token>`. Public endpoints are `GET /api/health`,
`POST /api/auth/login`, and `GET /api/product-images/{id}/content`. Product and
import endpoints are protected.

Protected read-only product API:

- `GET /api/products?page=1&limit=20`
- `GET /api/products?name=бермуды`
- `GET /api/products?minPrice=500&maxPrice=1500`
- `GET /api/products/{id}`

## Async XLSX import

Start an import with multipart field `file`:

```bash
curl -H "Authorization: Bearer TOKEN" \
  -F file=@backend/tests/Fixtures/import-example.xlsx \
  http://localhost:8080/api/imports
```

Poll `GET /api/imports/{id}` for counters, progress and the first 100 errors.
The HTTP request only validates/stores the upload and publishes an
`ImportProductsMessage`; `php bin/console messenger:consume async` in the
`worker` service reads it from RabbitMQ. Use `make worker-logs` or
`make worker-restart` to operate the worker. The async transport has a retry
strategy with exponential delay. Expected row-level problems are converted to
`ImportError`, while fatal file errors move the job to `failed`, so these errors
do not escape the handler for an automatic retry. If delivery is repeated for
an interrupted `processing` job, its previous progress and errors are reset and
the idempotent upsert is run again from the first row. A separate failure
transport is not configured yet.

The upload limit is configured by `IMPORT_MAX_FILE_SIZE` (PHP's own upload and
POST limits still apply). Imports are stored under `backend/storage/imports`.
Downloaded images are stored under `backend/storage/images` using a URL hash;
database paths are relative to `backend/storage`.

Every non-empty `Доп. поле: ...` column becomes an attribute whose key is the
suffix after `Доп. поле: `. `Ссылка на упаковку` and `Ссылки на фото` are the
two exceptions: they are combined and interpreted as image URLs. An unavailable
image produces a warning and a `ProductImage` with `path = null`; it does not
prevent the product from being imported. External image servers may return HTTP
403; this is recorded as a warning, not a failed row.

Import creation is limited per authenticated user by a PostgreSQL-backed fixed
window. Defaults are 5 requests per 60 seconds and can be changed with
`IMPORT_RATE_LIMIT` and `IMPORT_RATE_WINDOW_SECONDS`. A rejected request returns
HTTP 429 with `Retry-After` before a file, job, or queue message is created.

The Angular application stores the short-lived JWT and user data in
`localStorage`. It has no refresh token, roles, registration, or password reset;
an expired token requires logging in again. Configure the signing key and token
lifetime with `JWT_SECRET` and `JWT_TTL`; the provided secret is only for local
development.
