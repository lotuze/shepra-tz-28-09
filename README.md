# Product Import

Minimal full-stack environment for a future XLSX product importer.

## Start

```bash
cp .env.example .env
make build
make up
```

- Backend health: http://localhost:8080/api/health
- Angular dev server: http://localhost:4200
- RabbitMQ management: http://localhost:15672

Useful commands: `make logs`, `make shell`, `make migrate`, `make down`.

Backend data commands:

```bash
make migrate
make fixtures
make schema-validate
make test
```

Read-only product API:

- `GET /api/products?page=1&limit=20`
- `GET /api/products?name=бермуды`
- `GET /api/products?minPrice=500&maxPrice=1500`
- `GET /api/products/{id}`

The worker intentionally stays idle until Messenger routing and the `async`
transport are configured. Then replace its Compose command with:

```yaml
command: ["php", "bin/console", "messenger:consume", "async", "--time-limit=3600"]
```
