# AGENTS.md — Artemisia

Especificaciones técnicas del proyecto. Las reglas innegociables están en `docs/constitution.md`
y tienen prioridad sobre este archivo. Ante conflicto, gana la constitución.

## 1. Producto

Aplicación para una agencia de diseño y marketing digital, con tres superficies en una sola app Laravel:

| Superficie | Ruta | Implementación | Acceso |
|---|---|---|---|
| Landing pública | `/` | Blade + Vite | Anónimo |
| Portal de staff | `/staff` | Panel Filament `staff` | Roles `admin`, `staff` |
| Portal de clientes | `/portal` | Panel Filament `client` | Rol `client` |

Dominio: presupuestos compuestos por piezas gráficas, categorías de trabajo (branding, redes,
papelería), clientes con contratos y facturación, proveedores (imprentas, gráficas), organización
interna del trabajo (delegación, agenda, estados de piezas, sesiones de grabación) y, del lado del
cliente, aprobación de piezas, historial de pagos e información propia.

## 2. Stack y versiones

- PHP >= 8.3 (Laravel 13 lo exige; se recomienda 8.4+)
- Laravel 13 + Laravel Boost
- Filament 5
- MySQL 8
- spatie/laravel-permission (roles y permisos)
- laravel/socialite (login con Google)
- Pest (tests), Laravel Pint (estilo)
- Node 20+ y Vite para assets

No se agregan dependencias fuera de esta lista sin justificación escrita en el PR (constitución, principio 1).
Sin frameworks JS adicionales: Livewire y Alpine ya vienen con Filament y alcanzan.

## 3. Entorno local

Stack clásico en Windows (XAMPP / WAMP / Laragon): Apache y MySQL ya corriendo, PHP en el PATH.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev            # en otra terminal
php artisan serve      # si no se sirve por Apache/vhost
```

Variables de entorno propias del proyecto:

```
DB_CONNECTION=mysql
DB_DATABASE=artemisia
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

Comandos habituales:

```bash
php artisan test                 # o ./vendor/bin/pest
./vendor/bin/pint                # estilo antes de commitear
php artisan make:filament-panel  # alta de panel
php artisan boost:mcp            # servidor MCP de Boost
```

## 4. Estructura y convenciones de código

```
app/
  Actions/          # una acción por caso de uso, método público handle()
  Models/
  Enums/            # backed enums para estados (PieceStatus, BudgetStatus, ...)
  Policies/
  Filament/
    Staff/          # Resources, Pages, Widgets del panel de staff
    Client/         # Resources, Pages, Widgets del portal de clientes
  Providers/Filament/   # StaffPanelProvider, ClientPanelProvider
database/migrations|factories|seeders/
resources/views/landing/
lang/es/
tests/Unit|Feature/
docs/constitution.md, docs/specs/
```

- Modelos en inglés y singular (`Budget`, `BudgetItem`, `Client`, `Contract`, `Supplier`, `Piece`,
  `RecordingSession`); tablas en plural snake_case (`budget_items`).
- Estados siempre en backed enum, nunca strings sueltos ni magic numbers.
- Reglas de negocio en `app/Actions` y en los modelos. Las clases Filament solo orquestan y presentan
  (constitución, principio 3): nada de cálculo de totales, precios, estados ni permisos ahí dentro.
- Autorización con Policies + spatie/laravel-permission. Cada panel decide acceso en `canAccessPanel()`.
- Todo texto visible sale de `lang/es`; nunca hardcodeado.
- `./vendor/bin/pint` (preset Laravel) antes de cada commit.

## 5. Flujo git y PR

Rama base: `main`. Toda rama sale de `main` y vuelve por PR.

**Formato obligatorio de rama:** `vXX.XX.XX-[problema o solución]`

- `XX.XX.XX` = SemVer del proyecto, dos dígitos por segmento: `major.minor.patch`.
  - **major**: cambio que rompe datos o contratos existentes (migración destructiva, rediseño de un portal).
  - **minor**: funcionalidad o módulo nuevo (una spec nueva).
  - **patch**: corrección de bug, ajuste o refactor sin cambio de comportamiento.
- Slug en español, kebab-case, sin acentos ni ñ, máximo ~50 caracteres, describiendo el problema o la solución.

```
v01.02.00-flujo-aprobacion-presupuesto
v01.02.01-fix-exportar-pdf
v02.00.00-portal-clientes
```

Commits en inglés, imperativo, una idea por commit. El PR indica la spec de `docs/specs/` que lo
origina (constitución, principio 2), qué cambia y cómo probarlo.

## 6. Testing

Pest, con TDD estricto (constitución, principio 4): el test se escribe primero, debe fallar antes de
implementar, y su commit precede al de la implementación.

- `tests/Unit/` — reglas de negocio puras: Actions, cálculos de presupuesto, transiciones de estado.
- `tests/Feature/` — flujos completos: alta de presupuesto, aprobación de pieza, registro de pago,
  login con Google, acceso a cada panel según rol.
- Un factory por modelo; seeders solo para datos de referencia (categorías, roles, permisos).
- Tests nombrados por comportamiento y prefijados con el RF o RNF que cubren, tomado de la spec
  correspondiente: `it('RF-3: no permite aprobar una pieza ya facturada')`. Si un test cubre más
  de uno, se listan separados por coma: `it('RF-3, RF-4: ...')`. Es lo que le permite al validador
  de specs recorrer los requisitos y decir qué test cubre cada uno.
- La suite tiene que estar verde para mergear.

## 7. Nota sobre Laravel Boost

`php artisan boost:install` escribe sus propias guidelines dentro de este archivo, delimitadas por
sus marcadores. **No editar ese bloque a mano**: se regenera. Todo contenido propio va fuera de él.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.3. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
