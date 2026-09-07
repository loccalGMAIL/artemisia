---
paths:
  - 'app/Models/*.php'
---

# Models

## Re-query the parent in cascading recalculation hooks, don't use the cached relation
When a child model's `saved`/`deleted` event recalculates and saves an aggregate on its parent (e.g. `DetallePresupuesto` -> `Presupuesto::recalcularTotal()`, `ResumenDetalle` -> `ResumenMensual::recalcularMontos()`), fetch the parent fresh: `$child->parent()->first()?->recalcularX()` — not `$child->parent->recalcularX()`.

Why: `$child->parent` caches the belongsTo result on first access. If a sibling row (created/updated/deleted earlier in the same request) already changed the parent's aggregate in the DB, this cached instance is stale. Recomputing the sum and assigning it back can coincidentally match the stale cached value, so Eloquent's dirty-check sees no change and silently skips the UPDATE — the parent's real aggregate in the DB is then wrong. Always resolve the parent via a fresh query in these hooks.
