# Solo Skeleton

[![CI](https://github.com/SoloPHP/Skeleton/actions/workflows/ci.yml/badge.svg)](https://github.com/SoloPHP/Skeleton/actions/workflows/ci.yml)

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
curl -X POST http://127.0.0.1:8000/tasks -H 'Content-Type: application/json' \
  -d '{"title": "Ship it", "subtasks": ["Write the code", {"title": "Write the docs", "done": true}]}'
curl 'http://127.0.0.1:8000/tasks?q=solo&done=false&created[from]=2026-01-01&sort=-created_at'
curl 'http://127.0.0.1:8000/tasks/schema'
curl -X PATCH http://127.0.0.1:8000/tasks/1 -H 'Content-Type: application/json' -d '{"done": true}'
curl -X DELETE http://127.0.0.1:8000/tasks/1
```

## What is inside

| Package | Role here | Where |
|---|---|---|
| [solophp/application](https://solophp.github.io/Application/) | PSR-15 kernel: providers, middleware pipeline, routing, route cache | `bootstrap/app.php` |
| [solophp/router](https://solophp.github.io/Router/) | Routes, groups, parameter patterns such as `{id:[1-9][0-9]*}`, middleware attached to a route; `UrlGenerator` builds a path from a route's name | `config/routes.php`, `src/Modules/Task/` |
| [solophp/container](https://solophp.github.io/Container/) | PSR-11 container with autowiring; to PHPStan, `get(Foo::class)` is a `Foo` | `src/Providers/` |
| [solophp/http-emitter](https://github.com/SoloPHP/HTTP-Emitter) | Sends the PSR-7 response | `HttpServiceProvider` |
| [solophp/dotenv](https://github.com/SoloPHP/Dotenv) | `.env` files and typed access to variables | `bootstrap/app.php`, providers |
| [solophp/input](https://solophp.github.io/Input/) | Typed, immutable request DTOs built from attributes: a field sent blank has no value, `#[Normalize]` / `#[Caster]` / `#[PostProcess]` change the value at the three stages of reading it, `#[Items]` builds a list of nested DTOs, `#[Generator]` a value the client never sends, `#[Group]` collects the fields a read filters by; `schema()` prints the contract | `src/Modules/Task/Inputs/`, `src/Input/`, `GET /tasks/schema` |
| [solophp/validator](https://solophp.github.io/Validator/) | The rules behind `#[Validate]`; `unique` is the application's own rule, registered in `InputServiceProvider` | `src/Validation/` |
| [solophp/repository](https://solophp.github.io/Repository/) | Typed table repositories over Doctrine DBAL; `Like` makes search text match literally, and criteria are `'column op' => value` keys | `src/Modules/Task/TaskRepository.php`, `src/Modules/Task/Inputs/TaskIndexInput.php` |
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
  Modules/                    the application's own: a folder per module
    Task/                     a module: everything about a task, in one folder
      Models/                 Task (a row of `tasks`), Subtask (a value in its JSON column)
      Inputs/                 TaskStoreInput, TaskIndexInput, TaskUpdateInput, SubtaskInput,
                              SubtaskListNormalizer (a subtask written as a plain string)
      TaskRepository.php      the table
      TaskController.php      the four endpoints
      TaskResource.php        a model as the JSON of a response
      TaskRoutes.php          the module's routes
    Home/HomeController.php   the root endpoint: a module of one invokable handler
  Http/                       the transport, the same for every module
    JsonResponse.php          the {"data": ...} / {"error": ...} shape
    Exceptions/               HttpException, NotFoundException, ConflictException
    Middleware/               ErrorMiddleware, JsonBodyMiddleware, RequireJsonMiddleware
  Input/                      the stages of reading a request, reusable by any module
    Casters/                  SortCaster (?sort=-created_at), ContainsCaster (?q → %q%)
    PostProcessors/           DateRangePostProcessor (?created[from|to] → conditions)
    Generators/               UuidGenerator (a version 4 uuid)
  Validation/UniqueRule.php   a rule of the application's own: unique:tasks,title,{id}
  Support/Records.php         a row or a 404, in one place
  Database/Migrator.php
  Providers/                  Http, Logging, Database, Input
storage/                      SQLite database, logs, route cache
tests/Feature/                requests through the whole application
```

`src/Modules/` holds the application's own code: a module (`Task`, `Home`) owns the classes of one thing — a table or a single endpoint — and nothing else uses them, so a module may have no model at all. Everything outside `Modules/` is the framework of the application, the same for every module: `Http` is the transport, and `Input`, `Validation`, `Support`, `Database` and `Providers` are what a module may reach for. A class of one module, such as `SubtaskListNormalizer`, lives in that module even if it extends a package's interface; a class of a folder of a role lives there even if only one module calls it yet.

## How a request runs

1. `public/index.php` gets the application from `bootstrap/app.php`. That file loads `.env`, registers the providers and builds the router.
2. The global middleware run in order. `ErrorMiddleware` wraps everything below it. `JsonBodyMiddleware` decodes JSON bodies. `RoutingMiddleware` matches the route, or throws 404/405, and then runs the middleware the route itself names — `RequireJsonMiddleware` answers 415 to a write that sends something other than JSON.
3. The container builds the controller and injects its dependencies by type.
4. The controller turns the request into an input DTO, for example `TaskStoreInput`. A DTO reads the body by default, and `#[FromRoute]` reads the route parameters, as `TaskUpdateInput` does for its id. A field passes four stages, in this order: `#[Normalize]` on the raw value, the validator's rules, `#[Caster]` to the property's type, and `#[PostProcess]` once the whole input is valid. Invalid input throws `ValidationException`, and the response is a 422 with the failed rules per field.
5. The repository reads or writes the table and hydrates `Task` models.
6. `TaskResource` turns a model into an array, and `JsonResponse` writes it into the response.

Errors are thrown, never built by hand. `ErrorMiddleware` maps them to responses: 422 for validation, the status of an `HttpException`, 404/405 from routing, and 500 for anything else. A 500 is logged, and its details reach the response only when `APP_DEBUG=true`.

## Filtering a list

`GET /tasks` narrows by the fields `TaskIndexInput` groups: `#[Group('criteria')]` makes them the criteria of a repository read, and `Inputs::group($input, 'criteria')` is what the controller passes to `findBy()`.

```php
#[Caster(ContainsCaster::class)]
#[Group('criteria', mapTo: 'title LIKE')]
public ?string $q = null,               // ?q=solo → ['title LIKE' => '%solo%']

#[Validate('boolean')]
#[Group('criteria')]
public ?bool $done = null,              // ?done=false → ['done' => false]

#[Validate('array:from,to')]
#[PostProcess(DateRangePostProcessor::class, config: ['field' => 'created_at'])]
#[Group('criteria')]
public ?array $created = null,          // ?created[from]=2026-10-01&created[to]=2026-10-29 → two conditions
```

A field left null is not in the group, so a filter nobody sent narrows nothing. `mapTo` names the condition when the field's name is not the column's, and the caster or post-processor gives the value the read takes: `ContainsCaster` escapes the search text, so a `%` or `_` the client types matches only itself, and `DateRangePostProcessor` drops a bound that is not a day rather than rejecting the request.

## A partial write and a JSON column

`TaskUpdateInput` is `#[Input(partial: true)]`, so `Inputs::toArray()` gives only the fields the client sent, those of a nested input included. `subtasks` is one JSON value replaced as a whole, so a PATCH without `done` would store a document no later read can build. The repository fills the defaults in: `Subtask::rows()` for a list being written, `Subtask::fromRow()` for a document already stored without them. A `subtasks` sent as `null` is an empty list, and so is a write that has no list at all: the column keeps a list.

```php
['title' => 'Write the docs']                    // what a PATCH exports
['title' => 'Write the docs', 'done' => false]   // what the column keeps
```

## Responses

```jsonc
{"data": {"id": 1, "uuid": "1f0c…", "title": "Try Solo", "done": false, "subtasks": [...]}}   // one record
{"data": [...], "meta": {"pagination": {"page": 1, "per_page": 20, "total": 3, "pages": 1}}}
{"error": "NOT_FOUND"}
{"error": "VALIDATION_FAILED", "fields": {"title": [{"rule": "required"}]}}
{"error": "UNSUPPORTED_MEDIA_TYPE"}
{"error": "CONFLICT"}   // a unique value another request took first
```

An id no primary key can take — `/tasks/007`, a number past the PHP integer range — names no row, so it is a 404, not a 500.

## Validation

A rule string follows the validator's grammar: rules separated by `|`, a rule's parameters after `:`, as in `required|string|max:255`. `nullable` is a flag, not a rule — the field's type, `?string`, is what allows null.

The application's own rules are registered by name in `InputServiceProvider`, so a rule can take a service of its own. `unique:tasks,title` asks the table through the DBAL connection; a third parameter names the changed row — `unique:tasks,title,{id}` — and `{id}` is read from the validation payload, which holds the id `#[FromRoute]` took from the path, so a PATCH that keeps its title passes while another task's title does not. A fourth parameter names the key that row is found by, for a table whose key is not `id`: `unique:users,email,{uuid},uuid`. A `{field}` the payload does not carry skips the lookup: the absence is that field's own rules' to report.

A rule is a check, not a guarantee. The `tasks` table carries a unique index on `title` as well, and a write that passes the rule while another request takes the value in between is answered with a 409 `CONFLICT`, not with a duplicate row.

`GET /tasks/schema` prints that contract as JSON, the fields the client never sends left out, so a frontend or a code generator does not repeat it by hand.

## Adding a module

Take `src/Modules/Task` as the template:

1. Add a migration to `database/migrations/` and run `composer migrate`. Migrations are applied once, by name: an edited migration does not run again, so delete `storage/database/app.sqlite` (or re-create your database) after changing one, and run `composer migrate` on the empty database.
2. Add a readonly model in `Models/` and a repository that extends `Solo\Repository\Repository` with its `Mapping`.
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

MIT. See [LICENSE](LICENSE). Release history is in [CHANGELOG.md](CHANGELOG.md).
