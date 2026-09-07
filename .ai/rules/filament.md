---
paths:
  - 'app/Filament/**'
---

# Filament

## Three Filament panels by domain: admin, cliente, and the marketing home
This app has two Filament panels registered in `bootstrap/providers.php`, each scoped by `Panel::domain()` with an empty `path('')`:
- `AdminPanelProvider` (id `admin`) → `admin.artemisia.pez.com.ar`, discovers `app/Filament/Resources/**` — full CRUD, staff only.
- `ClientePanelProvider` (id `cliente`) → `clientes.artemisia.pez.com.ar`, discovers `app/Filament/Cliente/Resources/**` — read-only (List+View only, `canCreate() => false`, no Edit page), one resource class per model, kept deliberately separate from the admin resource classes for the same model (not shared/reused) so admin-only actions never leak into the client portal.
- The public home lives outside Filament entirely: `routes/web.php` wraps it in `Route::domain('artemisia.pez.com.ar')`, view at `resources/views/home.blade.php`.

Auth: single `users` table/guard, no separate guard per panel. `User::tipo` (`App\Enums\TipoUsuario`: Staff/Cliente) + nullable `cliente_id` decide access via `User::canAccessPanel()` — this is the real security boundary, not just the domain.

Every Cliente-panel resource must override `getEloquentQuery()` to scope by `auth()->user()->cliente_id` (e.g. `whereHas('presupuesto', fn ($q) => $q->where('cliente_id', ...))`) — a new Cliente resource without this scope leaks every client's data to every other client.
