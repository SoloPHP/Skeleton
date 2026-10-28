# Solo Skeleton

A starting point for an application built from [SoloPHP](https://github.com/SoloPHP) packages.

Solo is not a monolithic framework. It is a set of small, independent PSR packages, and each of them can be used alone or replaced by another PSR implementation. This skeleton shows how they fit together: a JSON API with one CRUD module, input validation, a database, error handling, logging and tests. It runs on SQLite, so there is no database server to set up first.

## Requirements

- PHP 8.4 or later
- Composer
- `pdo_sqlite` for the default database, or `pdo_mysql` / `pdo_pgsql`

## Quick start

```bash
composer create-project solophp/skeleton my-app
cd my-app
composer serve
```

`create-project` copies `.env.example` to `.env` and runs the migrations. The API is then at http://127.0.0.1:8000:

```bash
curl -X POST http://127.0.0.1:8000/tasks -H 'Content-Type: application/json' -d '{"title": "Try Solo"}'
curl 'http://127.0.0.1:8000/tasks?q=solo&done=false&sort=-created_at'
curl -X PATCH http://127.0.0.1:8000/tasks/1 -H 'Content-Type: application/json' -d '{"done": true}'
curl -X DELETE http://127.0.0.1:8000/tasks/1
```

## What is inside

| Package | Role here | Where |
|---|---|---|
| [solophp/application](https://solophp.github.io/Application/) | PSR-15 kernel: providers, middleware pipeline, routing, route cache | `bootstrap/app.php` |
| [solophp/router](https://solophp.github.io/Router/) | Routes, groups, parameter patterns such as `{id:\d+}`; `UrlGenerator` builds a path from a route's name | `config/routes.php`, `src/Task/` |
| [solophp/container](https://solophp.github.io/Container/) | PSR-11 container with autowiring; to PHPStan, `get(Foo::class)` is a `Foo` | `src/Providers/` |
| [solophp/http-emitter](https://github.com/SoloPHP/HTTP-Emitter) | Sends the PSR-7 response | `HttpServiceProvider` |
| [solophp/dotenv](https://github.com/SoloPHP/Dotenv) | `.env` files and typed access to variables | `bootstrap/app.php`, providers |
| [solophp/input](https://solophp.github.io/Input/) | Typed, immutable request DTOs built from attributes; a field sent blank has no value, like one not sent | `src/Task/Inputs/` |
| [solophp/validator](https://solophp.github.io/Validator/) | The rules behind `#[Validate]` | `InputServiceProvider` |
| [solophp/repository](https://solophp.github.io/Repository/) | Typed table repositories over Doctrine DBAL; `Like` makes search text match literally | `src/Task/TaskRepository.php`, `TaskIndexInput` |
| [solophp/logger](https://github.com/SoloPHP/Logger) | PSR-3 file logger with rotation | `LoggingServiceProvider` |
| [solophp/contracts](https://github.com/SoloPHP/Contracts) | The interfaces the packages share | providers |

[Nyholm PSR-7](https://github.com/Nyholm/psr7) is the HTTP message implementation. Only `HttpServiceProvider` refers to it: the rest of the code sees PSR interfaces.

## Layout

```
bin/migrate                   applies database/migrations
bootstrap/app.php             builds the application; shared by the web, the CLI and the tests
config/
  providers.php               service providers, in order
  middleware.php              global middleware, outermost first
  routes.php                  every route, one line per module
database/migrations/          forward-only migrations, DBAL schema API
public/index.php              front controller
src/
  Database/Migrator.php
  Home/HomeController.php     an invokable handler
  Http/
    JsonResponse.php          the {"data": ...} / {"error": ...} shape
    Exceptions/               HttpException, NotFoundException
    Middleware/               ErrorMiddleware, JsonBodyMiddleware
    Casters/SortCaster.php    ?sort=-created_at → ['created_at' => 'DESC']
  Providers/                  Http, Logging, Database, Input
  Task/                       one module: model, repository, inputs, controller, resource, routes
storage/                      SQLite database, logs, route cache
tests/Feature/                requests through the whole application
```

## How a request runs

1. `public/index.php` gets the application from `bootstrap/app.php`. That file loads `.env`, registers the providers and builds the router.
2. The global middleware run in order. `ErrorMiddleware` wraps everything below it. `JsonBodyMiddleware` decodes JSON bodies. `RoutingMiddleware` matches the route, or throws 404/405.
3. The container builds the controller and injects its dependencies by type.
4. The controller turns the request into an input DTO, for example `TaskStoreInput`. Invalid input throws `ValidationException`, and the response is a 422 with the failed rules per field.
5. The repository reads or writes the table and hydrates `Task` models.
6. `TaskResource` turns a model into an array, and `JsonResponse` writes it into the response.

Errors are thrown, never built by hand. `ErrorMiddleware` maps them to responses: 422 for validation, the status of an `HttpException`, 404/405 from routing, and 500 for anything else. A 500 is logged, and its details reach the response only when `APP_DEBUG=true`.

## Responses

```jsonc
{"data": {"id": 1, "title": "Try Solo", "done": false, ...}}           // one record
{"data": [...], "meta": {"pagination": {"page": 1, "per_page": 20, "total": 3, "pages": 1}}}
{"error": "NOT_FOUND"}
{"error": "VALIDATION_FAILED", "fields": {"title": [{"rule": "required"}]}}
```

## Adding a module

Take `src/Task` as the template:

1. Add a migration to `database/migrations/` and run `composer migrate`.
2. Add a readonly model and a repository that extends `Solo\Repository\Repository` with its `Mapping`.
3. Add input classes for each endpoint that reads the request.
4. Add a controller and a resource.
5. Register the routes in a `XxxRoutes` class, then add `(new XxxRoutes())->register($r);` to `config/routes.php`.
6. Add a feature test.

## Configuration

| Variable | Default | |
|---|---|---|
| `APP_ENV` | `production` | `production` caches the routes in `storage/cache/routes.php` |
| `APP_DEBUG` | `false` | exception details in 500 responses |
| `APP_TIMEZONE` | `UTC` | |
| `LOG_FILE` | `storage/logs/app.log` | relative to the project root |
| `LOG_LEVEL` | `warning` | PSR-3 level |
| `DB_DRIVER` | `pdo_sqlite` | `pdo_sqlite`, `pdo_mysql` or `pdo_pgsql` |
| `DB_PATH` | `storage/database/app.sqlite` | SQLite only; `:memory:` for an in-memory database |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | | MySQL and PostgreSQL |

`.env.local` overrides `.env`. Variables set by the server or the shell override both.

## Tests and checks

```bash
composer test    # PHPUnit: every test boots the app on a fresh in-memory database
composer stan    # PHPStan, level max
composer cs      # PHP_CodeSniffer, PSR-12
composer check   # all three
```

## Production

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Point the web server's document root to `public/` and send every request to `public/index.php`.
- Delete `storage/cache/routes.php` on each deploy. The next request builds it again.
- Make `storage/` writable by PHP.

## More Solo packages

Add them when the application needs them: [Event-Dispatcher](https://github.com/SoloPHP/Event-Dispatcher), [Cache](https://github.com/SoloPHP/Cache), [Job-Queue](https://github.com/SoloPHP/Job-Queue), [HTTP-Client](https://github.com/SoloPHP/HTTP-Client), [Session](https://github.com/SoloPHP/Session), [Inertia](https://github.com/SoloPHP/Inertia). Each one gets a provider in `src/Providers/` and a line in `config/providers.php`.

## License

MIT. See [LICENSE](LICENSE).
