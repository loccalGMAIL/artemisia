# NNN — Tareas: \<Nombre de la funcionalidad\>

| | |
|---|---|
| **Spec** | docs/specs/NNN-nombre/spec.md |
| **Plan** | docs/specs/NNN-nombre/plan.md |
| **Estado** | En curso / Completado |
| **Fecha** | AAAA-MM-DD |

## Convenciones

- Checkbox al inicio de cada tarea. Se marca al terminarla, no antes.
- Orden por dependencias: cada tarea depende solo de las anteriores.
- TDD estricto: el test va antes que la implementación.
- La suite queda verde después de cada tarea de `impl`.

## Resumen

- Total: \<N\> tareas.
- Por tipo: \<X\> test, \<Y\> impl, \<Z\> migration, \<W\> ui, …
- Cubre: RF-1 a RF-\<N\>, RNF-1 a RNF-\<M\>.

---

## Fase 1: \<Nombre de la fase\>

### - [ ] T1: \<Título imperativo\>

- **Tipo**: migration
- **Cubre**: RF-1
- **Depende de**: —
- **Hecho cuando**: `php artisan migrate:fresh` corre sin errores y la tabla \<X\> existe con las
  columnas y claves foráneas del plan §4.

### - [ ] T2: Escribir test de \<comportamiento\>

- **Tipo**: test
- **Cubre**: RF-2
- **Depende de**: T1
- **Hecho cuando**: `pest --filter=\<nombre\>` corre, falla con mensaje claro (no por falta de
  clase), commit del test hecho.

### - [ ] T3: Implementar \<comportamiento\>

- **Tipo**: impl
- **Cubre**: RF-2
- **Depende de**: T2
- **Hecho cuando**: el test de T2 pasa, la suite completa queda verde.

---

## Fase 2: \<Nombre de la fase\>

### - [ ] T4: …

…

---

## Mapa RF → tareas

Al terminar, cada RF y RNF de la spec tiene que estar cubierto por al menos una tarea.

| RF | Tareas |
|---|---|
| RF-1 | T1 |
| RF-2 | T2, T3 |
| RNF-1 | T\<N\> |

## Cambios en el plan detectados al descomponer

Cosas que se hicieron visibles al bajar a tareas y que idealmente vuelven al plan antes de
implementar (no se resuelven acá).

- …
