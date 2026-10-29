# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
