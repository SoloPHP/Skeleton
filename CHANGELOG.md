# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- solophp/dotenv 2.0. `bootstrap/app.php` loads `.env` and `.env.local` as before, but a malformed line,
  such as one without `=` or with an unterminated quote, now stops the boot with the file and the line
  instead of being skipped, and nothing from either file is applied.
- Variables set by the server or the shell override both files whatever `variables_order` says, and
  `HTTP_*` request headers never count as configuration.
- A `#` starts a comment only after a space or a tab, and double quotes decode `\n`, `\t` and `\"`, so
  a Windows path belongs in single quotes. `DB_PORT` must be a decimal integer: `3306.0` falls back to
  the default port.

## [1.2.0] - 2026-11-01

### Changed

- solophp/logger 2.0. `LoggingServiceProvider` builds the logger with `Logger::file()`: the file still
  rotates before it passes 5 MiB and keeps 5 archives, now under one lock that every process shares, and
  the records keep the time of `APP_TIMEZONE`.
- A log line carries microsecond times and its context as JSON. The error line of `ErrorMiddleware` reads
  `GET /tasks: RuntimeException: Connection refused`, the exception's trace in the context.
- Archives are named `app.log.<time>.<n>` instead of `app_<time>.log`; old archives are left alone, so
  delete them yourself.
- An unknown `LOG_LEVEL`, such as `WARNING` in capitals, throws instead of being accepted.
- solophp/repository 1.3. `GET /tasks` reads its page with `ReadQuery::page()`: the id ends the
  requested order, so tasks created in the same second keep their place across pages, and a page
  shorter than its size gives the total without a `COUNT`. `JsonResponse::page()` takes the `Page`,
  and `TaskResource::collection()` is gone; the response is unchanged.

### Upgrading from 1.1.0

- `create-project` gives the new version. An existing copy needs `"solophp/logger": "^2.0"` and
  `"solophp/repository": "^1.3"` in `composer.json` and `LoggingServiceProvider` from this release.
- A controller that pages a list calls `ReadQuery::page()` and passes `JsonResponse::page()` the
  `Page`, mapped by its resource, in place of `findBy()` with an offset and a separate `count()`.

## [1.1.0] - 2026-10-29

### Added

- A module layout: `src/Modules/Task` and `src/Modules/Home`, each one folder that holds its own
  models, inputs, repository, controller, resource and routes.
- Subtasks as a JSON column, so a task and its list stay one table: the `Subtask` model,
  `SubtaskInput`, `SubtaskListNormalizer` (a subtask written as a plain string) and the `subtasks`
  column.
- A `uuid` column and `UuidGenerator`: every task gets one, the client never sends it.
- `GET /tasks/schema`, the input contract of `POST /tasks` as JSON.
- A date-range filter, `?created[from]=2026-10-01&created[to]=2026-10-29`, through
  `DateRangePostProcessor`.
- The application's own `unique` rule (`src/Validation/UniqueRule.php`): `unique:tasks,title`,
  `unique:tasks,title,{id}` for a write that keeps its own value, and a fourth parameter naming the
  key the row is found by.
- Unique indexes on `tasks.title` and `tasks.uuid`, and an index on `tasks.created_at`.
- `ConflictException`: a 409 for a write the rule passed but the index refused.
- `RequireJsonMiddleware`: a 415 for a write body that is not JSON, attached to the routes that have
  a body.
- `src/Support/Records::findOrFail()`: a row or its 404, in one place instead of a private `find()`
  in every controller.
- `src/Input/`: the casters, post-processors and generators of the stages of reading a request,
  reusable by any module.
- 20 feature tests; the suite is 34 tests, 325 assertions.

### Changed

- Namespaces follow the folders: `App\Task\*` is `App\Modules\Task\*`, and `App\Home\HomeController`
  is `App\Modules\Home\HomeController`.
- `SortCaster` moved from `src/Http/Casters/` to `src/Input/Casters/`, next to the other stages of
  reading a request.
- `PATCH /tasks/{id}` lets the input read its id from the route parameters, so `{id}` in a `unique`
  rule is the row being changed.
- `GET /tasks` builds its criteria from `#[Group('criteria')]` on the input's fields instead of a
  `criteria()` method.
- Route ids are canonical (`{id:[1-9][0-9]*}`), so `/tasks/007` is no row's path either.
- `POST /tasks` and `PATCH /tasks/{id}` answer 415 to a body that is not JSON.
- The README explains the two tiers (`Modules/` for the application's own code, the rest for what
  every module shares) and has a template for a new module.
- GitHub Actions runs `composer check` on PHP 8.4 and 8.5; this changelog keeps the history.

### Fixed

- A subtask stored without a `done` key reads back as `done: false` instead of failing.
- A write that sends `subtasks: null` stores a list, not null.
- A repository write below HTTP, with no `subtasks` key at all, stores an empty list.
- An id no primary key can take is a 404, not a type error.
- `.gitignore` covers `.idea/`, `.vscode/` and `.DS_Store`.

### Upgrading from 1.0.0

`create-project` is the upgrade: an existing copy has its folders and namespaces renamed, so a merge
is not the way. If you keep a copy, this is the work:

- `src/Task/` → `src/Modules/Task/`, `src/Home/` → `src/Modules/Home/`, and the `App\Task` /
  `App\Home` namespaces with them.
- `src/Http/Casters/SortCaster.php` → `src/Input/Casters/SortCaster.php`.
- `config/routes.php`: its two `use` lines.
- The `tasks` table gained `uuid`, `subtasks` and three indexes: delete `storage/database/app.sqlite`
  and run `composer migrate`. Migrations are applied once, by name.

## [1.0.0] - 2026-10-28

### Added

- The first release: the Solo packages wired into a JSON API with one CRUD module, on SQLite.
