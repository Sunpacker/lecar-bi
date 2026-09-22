# AutoBI Backend

Independent Laravel analytics microservice. It contains only PHP/Laravel code, domain modules, API presentation and infrastructure adapters.

```bash
composer install
php artisan serve --host=0.0.0.0 --port=8080
```

Default URL: `http://localhost:8080`. Technical endpoint: `/api/v1/health`.

Quality checks:

```bash
composer validate --strict
composer lint
composer test
```

The test suite verifies the health response against the published OpenAPI schema, bounded-context registration and Domain dependency boundaries, including `Shared/Domain`.
