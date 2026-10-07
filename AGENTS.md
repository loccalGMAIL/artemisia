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
CACHE_STORE=database
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

El `RateLimiter` de login usa cache persistente. En local y tests se usa cache en base de datos
(`CACHE_STORE=database`) para que los limites de intentos sean reproducibles entre solicitudes.

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

Orden canonico de implementacion de specs aprobado por dependencias:

1. `001-acceso-roles-y-paneles`
2. `003-clientes`
3. `002-presupuestos`
4. `004-piezas`

El bootstrap inicial de Laravel es un prerequisito fundacional fuera de las specs funcionales; ver
`docs/adr/0001-bootstrap-laravel-fuera-de-specs.md`.

## 5. Flujo git y PR

Rama base: `master`. Toda rama sale de `master` y vuelve por PR.

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
