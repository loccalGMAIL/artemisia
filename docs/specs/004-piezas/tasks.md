# 004 — Tareas: Piezas

| | |
|---|---|
| **Spec** | docs/specs/004-piezas/spec.md |
| **Plan** | docs/specs/004-piezas/plan.md |
| **Estado** | En curso |
| **Fecha** | 2026-09-21 |

## Convenciones

- Checkbox al inicio de cada tarea. Se marca al terminarla, no antes.
- Orden por dependencias: cada tarea depende solo de las anteriores.
- TDD estricto: el test va antes que la implementación en reglas de negocio y flujos críticos.
- La suite queda verde después de cada tarea de `impl` o `ui`.
- Cada tarea de `impl` o `ui` que produce mensajes visibles agrega también sus claves en `lang/es/pieces.php`.
- Se asume el orden canónico ya cumplido: `001-acceso-roles-y-paneles` → `003-clientes` → `002-presupuestos` → `004-piezas`.
- No hay tarea de bootstrap Laravel: es un prerrequisito fundacional fuera de esta spec.

## Resumen

- Total: 38 tareas.
- Por tipo: 17 test, 13 impl, 3 migration, 5 ui.
- Cubre: RF-1 a RF-48, RNF-1 a RNF-3.
- Prerrequisitos asumidos: roles y paneles de `001`, clientes y vínculo cuenta-cliente de `003`, presupuestos aceptados, ítems, servicios y categorías de trabajo de `002`.

---

## Fase 1: Esquema y base de dominio

### - [x] T1: Crear migración de `pieces`

- **Tipo**: migration
- **Cubre**: RF-3, RF-5, RF-9, RF-10, RF-12, RF-16, RF-18, RF-19, RF-27, RF-40, RNF-2
- **Depende de**: —
- **Hecho cuando**: `php artisan migrate:fresh` crea `pieces` con las columnas, claves foráneas, índices y `SoftDeletes` del plan §4, sin cascada destructiva sobre `budget_item_id`.

### - [x] T2: Crear migración de `piece_approval_submissions`

- **Tipo**: migration
- **Cubre**: RF-23, RF-28, RF-29, RF-30, RF-33, RF-34, RF-35, RF-36, RF-37, RNF-3
- **Depende de**: T1
- **Hecho cuando**: `php artisan migrate:fresh` crea `piece_approval_submissions` con archivo, resolución, cuenta resolutora, fecha de envío, fecha de resolución y motivo de rechazo, sin `deleted_at`.

### - [x] T3: Crear migración de `piece_histories`

- **Tipo**: migration
- **Cubre**: RF-38, RF-39, RF-40, RNF-3
- **Depende de**: T2
- **Hecho cuando**: `php artisan migrate:fresh` crea `piece_histories` con `field`, `old_value`, `new_value`, `author_id`, `created_at`, índice `(piece_id, created_at)` y sin `updated_at`.

### - [x] T4: Crear enums, modelos y factories de piezas

- **Tipo**: impl
- **Cubre**: RF-18, RF-30, RF-38, RF-40, RNF-3
- **Depende de**: T3
- **Hecho cuando**: existen `PieceStatus`, `PieceApprovalResolution`, `PieceHistoryField`, los modelos `Piece`, `PieceApprovalSubmission`, `PieceHistory`, sus relaciones, casts y factories, y un test de humo confirma que las relaciones cargan correctamente.

---

## Fase 2: Generación de piezas

### - [x] T5: Escribir test de propuesta desde presupuesto aceptado

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-6, RF-7, RF-10
- **Depende de**: T4
- **Hecho cuando**: el test falla y comprueba que un presupuesto aceptado genera una propuesta en memoria con una línea por ítem, nombre, cantidad y categoría heredada, y que un presupuesto no aceptado se rechaza con motivo; commit del test hecho.

### - [x] T6: Implementar `ProposePiecesFromBudgetAction`

- **Tipo**: impl
- **Cubre**: RF-1, RF-2, RF-6, RF-7, RF-10
- **Depende de**: T5
- **Hecho cuando**: el test de T5 pasa, la action no persiste ninguna fila y la suite completa queda verde.

### - [x] T7: Escribir test de creación desde propuesta confirmada

- **Tipo**: test
- **Cubre**: RF-2, RF-3, RF-4, RF-6, RF-9, RF-10, RF-18, RF-19, RF-38
- **Depende de**: T6
- **Hecho cuando**: el test falla y comprueba división de cantidades, eliminación de líneas de la propuesta, asociación con `budget_item_id`, estado `pending` e historial de creación; commit del test hecho.

### - [x] T8: Implementar `CreatePiecesFromProposalAction`

- **Tipo**: impl
- **Cubre**: RF-2, RF-3, RF-4, RF-6, RF-9, RF-10, RF-18, RF-19, RF-38
- **Depende de**: T7
- **Hecho cuando**: el test de T7 pasa, cada pieza creada registra su historial y la suite completa queda verde.

### - [x] T9: Escribir test de pieza suelta

- **Tipo**: test
- **Cubre**: RF-5, RF-6, RF-7, RF-9, RF-10, RF-16, RF-18, RF-19, RF-38
- **Depende de**: T8
- **Hecho cuando**: el test falla y cubre creación sin `budget_item_id`, categoría obligatoria, fecha de entrega opcional, rechazo de presupuesto no aceptado e historial de creación; commit del test hecho.

### - [x] T10: Implementar `CreateLoosePieceAction`

- **Tipo**: impl
- **Cubre**: RF-5, RF-6, RF-7, RF-9, RF-10, RF-16, RF-18, RF-19, RF-38
- **Depende de**: T9
- **Hecho cuando**: el test de T9 pasa y la suite completa queda verde.

### - [x] T11: Escribir test de independencia frente a cambios posteriores del presupuesto

- **Tipo**: test
- **Cubre**: RF-8
- **Depende de**: T10
- **Hecho cuando**: el test falla y comprueba que una pieza ya creada no cambia si el presupuesto vuelve a `sent` o si se modifica o quita el ítem de origen; commit del test hecho.

### - [x] T12: Implementar la independencia de piezas ya generadas

- **Tipo**: impl
- **Cubre**: RF-8
- **Depende de**: T11
- **Hecho cuando**: el test de T11 pasa, no existe observer ni listener que sincronice piezas desde presupuesto o ítems, y la suite completa queda verde.

---

## Fase 3: Delegación, agenda y estados

### - [x] T13: Escribir test de delegación, reasignación y fecha comprometida

- **Tipo**: test
- **Cubre**: RF-11, RF-12, RF-13, RF-14, RF-15, RF-16, RF-17, RF-25, RF-38
- **Depende de**: T12
- **Hecho cuando**: el test falla y comprueba responsable único, reasignación, rechazo sobre pieza entregada, fecha nullable, cálculo de atraso e historial de responsable y fecha; commit del test hecho.

### - [x] T14: Implementar `AssignPieceOwnerAction`, `SetPieceDueDateAction` y scope de atraso

- **Tipo**: impl
- **Cubre**: RF-11, RF-12, RF-13, RF-14, RF-15, RF-16, RF-17, RF-25, RF-38
- **Depende de**: T13
- **Hecho cuando**: el test de T13 pasa, `Piece::overdue()` excluye piezas entregadas y la suite completa queda verde.

### - [x] T15: Escribir test de transiciones de estado de producción

- **Tipo**: test
- **Cubre**: RF-18, RF-20, RF-21, RF-22, RF-24, RF-25, RF-38
- **Depende de**: T14
- **Hecho cuando**: el test falla y cubre `pending` → `in_production` → `in_review`, bloqueo de transiciones inválidas, `approved` → `delivered`, e imposibilidad de cambiar estado de una entregada; commit del test hecho.

### - [x] T16: Implementar actions de transición de estado

- **Tipo**: impl
- **Cubre**: RF-18, RF-20, RF-21, RF-22, RF-24, RF-25, RF-38
- **Depende de**: T15
- **Hecho cuando**: existen `MarkPieceInProductionAction`, `MarkPieceInReviewAction` y `MarkPieceDeliveredAction`, el test de T15 pasa y la suite completa queda verde.

### - [x] T17: Escribir test de descarte de pieza pendiente

- **Tipo**: test
- **Cubre**: RF-26, RF-27, RF-40
- **Depende de**: T16
- **Hecho cuando**: el test falla y comprueba que solo una pieza `pending` se descarta, que el descarte usa `SoftDeletes`, que no borra historial ni envíos, y que deja de aparecer en el listado normal; commit del test hecho.

### - [x] T18: Implementar `DiscardPieceAction`

- **Tipo**: impl
- **Cubre**: RF-26, RF-27, RF-40
- **Depende de**: T17
- **Hecho cuando**: el test de T17 pasa, no hay eliminación física de piezas ni historiales y la suite completa queda verde.

---

## Fase 4: Envíos a aprobación y resolución del cliente

### - [x] T19: Escribir test de envío a aprobación con archivo

- **Tipo**: test
- **Cubre**: RF-22, RF-23, RF-28, RF-29, RF-30, RF-31, RNF-1, RNF-3
- **Depende de**: T18
- **Hecho cuando**: el test falla y cubre envío solo desde `in_review`, un único archivo por envío, formatos JPG/PNG/PDF/MP4, tope de 100 MB, rechazo con motivo y conservación de múltiples envíos; commit del test hecho.

### - [x] T20: Implementar `SendPieceForClientApprovalAction`

- **Tipo**: impl
- **Cubre**: RF-22, RF-23, RF-28, RF-29, RF-30, RF-31, RNF-1, RNF-3
- **Depende de**: T19
- **Hecho cuando**: el test de T19 pasa, el archivo se guarda con el filesystem nativo de Laravel, la pieza queda en `client_approval` y la suite completa queda verde.

### - [x] T21: Escribir test de aprobación y rechazo desde cuenta cliente vinculada

- **Tipo**: test
- **Cubre**: RF-32, RF-33, RF-34, RF-35, RF-47, RF-48
- **Depende de**: T20
- **Hecho cuando**: el test falla y cubre aprobación con fecha, rechazo con motivo vacío o informado, vuelta automática a `in_production`, rechazo si la pieza no está en aprobación y rechazo si pertenece a otro cliente; commit del test hecho.

### - [ ] T22: Implementar `ApprovePieceAction`, `RejectPieceAction` y `PieceApprovalPortalPolicy`

- **Tipo**: impl
- **Cubre**: RF-32, RF-33, RF-34, RF-35, RF-47, RF-48
- **Depende de**: T21
- **Hecho cuando**: el test de T21 pasa, las resoluciones actualizan el envío vigente sin crear duplicados y la suite completa queda verde.

### - [ ] T23: Escribir test de consulta de envíos e historial

- **Tipo**: test
- **Cubre**: RF-36, RF-37, RF-38, RF-39, RF-40, RNF-3
- **Depende de**: T22
- **Hecho cuando**: el test falla y comprueba historial cronológico para staff, envíos completos para staff y envíos resueltos por la propia cuenta cliente aunque la pieza haya vuelto a producción; commit del test hecho.

### - [ ] T24: Implementar consultas de historial y envíos conservados

- **Tipo**: impl
- **Cubre**: RF-36, RF-37, RF-38, RF-39, RF-40, RNF-3
- **Depende de**: T23
- **Hecho cuando**: el test de T23 pasa, no existe comando ni job de purga de historiales o envíos y la suite completa queda verde.

---

## Fase 5: Staff panel

### - [ ] T25: Escribir test del flujo staff para generar piezas

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-6, RF-7, RF-9, RF-10
- **Depende de**: T24
- **Hecho cuando**: el test falla y cubre que `admin` o `staff` abre la propuesta desde un presupuesto aceptado, divide o quita líneas, confirma piezas, crea pieza suelta y ve el motivo al intentar hacerlo sobre presupuesto no aceptado; commit del test hecho.

### - [ ] T26: Crear UI staff de generación y alta de piezas

- **Tipo**: ui
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-6, RF-7, RF-9, RF-10
- **Depende de**: T25
- **Hecho cuando**: el test de T25 pasa, la UI solo orquesta las actions existentes y la suite completa queda verde.

### - [ ] T27: Escribir test del listado staff y sus filtros

- **Tipo**: test
- **Cubre**: RF-13, RF-17, RF-41, RF-42, RF-43, RF-44, RNF-2
- **Depende de**: T26
- **Hecho cuando**: el test falla y comprueba columnas requeridas, filtros por estado, responsable, cliente, mis piezas delegadas y atrasadas, con presupuesto y cliente cargados sin consultas innecesarias evidentes; commit del test hecho.

### - [ ] T28: Crear `PieceResource` con listado y filtros

- **Tipo**: ui
- **Cubre**: RF-13, RF-17, RF-41, RF-42, RF-43, RF-44, RNF-2
- **Depende de**: T27
- **Hecho cuando**: el test de T27 pasa, la tabla usa eager loading e índices del plan y la suite completa queda verde.

### - [ ] T29: Escribir test de acciones staff sobre una pieza

- **Tipo**: test
- **Cubre**: RF-11, RF-14, RF-15, RF-20, RF-21, RF-22, RF-23, RF-24, RF-25, RF-26, RF-28, RF-31, RF-36, RF-39
- **Depende de**: T28
- **Hecho cuando**: el test falla y cubre delegar, reasignar, cargar fecha, cambiar estados, enviar archivo, marcar entregada, descartar pendiente y consultar envíos e historial; commit del test hecho.

### - [ ] T30: Crear acciones y relation managers en `PieceResource`

- **Tipo**: ui
- **Cubre**: RF-11, RF-14, RF-15, RF-20, RF-21, RF-22, RF-23, RF-24, RF-25, RF-26, RF-28, RF-31, RF-36, RF-39
- **Depende de**: T29
- **Hecho cuando**: el test de T29 pasa, las acciones delegan en Actions de dominio, los relation managers son de consulta cuando corresponde y la suite completa queda verde.

### - [ ] T31: Escribir test de permisos staff sobre piezas

- **Tipo**: test
- **Cubre**: RF-4, RF-11, RF-15, RF-28, RF-36, RF-39
- **Depende de**: T30
- **Hecho cuando**: el test falla y comprueba que `admin` y `staff` pueden operar piezas desde `/staff`, y que una cuenta `client` no puede acceder a esas operaciones; commit del test hecho.

### - [ ] T32: Implementar `PiecePolicy`

- **Tipo**: impl
- **Cubre**: RF-4, RF-11, RF-15, RF-28, RF-36, RF-39
- **Depende de**: T31
- **Hecho cuando**: el test de T31 pasa, la policy no distingue entre `admin` y `staff` para esta spec y la suite completa queda verde.

---

## Fase 6: Portal de clientes

### - [ ] T33: Escribir test del listado del portal de clientes

- **Tipo**: test
- **Cubre**: RF-37, RF-45, RF-46, RF-48
- **Depende de**: T32
- **Hecho cuando**: el test falla y comprueba que la cuenta cliente solo ve piezas de su cliente en `client_approval`, `approved` o `delivered`, no ve `pending`, `in_production` ni `in_review`, y no ve piezas de otros clientes; commit del test hecho.

### - [ ] T34: Crear `PieceApprovalPage` del portal de clientes

- **Tipo**: ui
- **Cubre**: RF-37, RF-45, RF-46, RF-48
- **Depende de**: T33
- **Hecho cuando**: el test de T33 pasa, la página muestra piezas visibles y envíos resueltos propios, y la suite completa queda verde.

### - [ ] T35: Escribir test de aprobar y rechazar desde el portal

- **Tipo**: test
- **Cubre**: RF-32, RF-33, RF-34, RF-35, RF-47, RF-48
- **Depende de**: T34
- **Hecho cuando**: el test falla y cubre botones de aprobar y rechazar, motivo opcional de rechazo, mensaje de permiso insuficiente por estado inválido y por cliente ajeno; commit del test hecho.

### - [ ] T36: Conectar acciones de aprobación y rechazo en el portal

- **Tipo**: ui
- **Cubre**: RF-32, RF-33, RF-34, RF-35, RF-47, RF-48
- **Depende de**: T35
- **Hecho cuando**: el test de T35 pasa, la UI delega en `ApprovePieceAction` y `RejectPieceAction`, y la suite completa queda verde.

---

## Fase 7: Rendimiento, textos y cierre

### - [ ] T37: Escribir test de rendimiento del listado con 5.000 piezas

- **Tipo**: test
- **Cubre**: RNF-2
- **Depende de**: T36
- **Hecho cuando**: el test falla si el listado staff con 5.000 piezas supera 2 segundos en el entorno de test definido, incluyendo filtros de estado, responsable, cliente y atrasadas; commit del test hecho.

### - [ ] T38: Optimizar consultas, validar textos y correr suite completa

- **Tipo**: impl
- **Cubre**: RF-7, RF-31, RF-47, RF-48, RNF-2
- **Depende de**: T37
- **Hecho cuando**: el test de T37 pasa, todos los mensajes visibles de piezas están en `lang/es/pieces.php`, `php artisan test` y `./vendor/bin/pint` corren sin errores.

---

## Mapa RF/RNF → tareas

| RF/RNF | Tareas |
|---|---|
| RF-1 | T5, T6, T25, T26 |
| RF-2 | T5, T6, T7, T8, T25, T26 |
| RF-3 | T1, T7, T8, T25, T26 |
| RF-4 | T7, T8, T25, T26, T31, T32 |
| RF-5 | T1, T9, T10, T25, T26 |
| RF-6 | T5, T6, T7, T8, T9, T10, T25, T26 |
| RF-7 | T5, T6, T9, T10, T25, T26, T38 |
| RF-8 | T11, T12 |
| RF-9 | T1, T7, T8, T9, T10, T25, T26 |
| RF-10 | T1, T5, T6, T7, T8, T9, T10, T25, T26 |
| RF-11 | T13, T14, T29, T30, T31, T32 |
| RF-12 | T1, T13, T14 |
| RF-13 | T13, T14, T27, T28 |
| RF-14 | T13, T14, T29, T30 |
| RF-15 | T13, T14, T29, T30, T31, T32 |
| RF-16 | T1, T9, T10, T13, T14 |
| RF-17 | T13, T14, T27, T28 |
| RF-18 | T1, T4, T7, T8, T9, T10, T15, T16 |
| RF-19 | T1, T7, T8, T9, T10 |
| RF-20 | T15, T16, T29, T30 |
| RF-21 | T15, T16, T29, T30 |
| RF-22 | T15, T16, T19, T20, T29, T30 |
| RF-23 | T2, T19, T20, T29, T30 |
| RF-24 | T15, T16, T29, T30 |
| RF-25 | T13, T14, T15, T16, T29, T30 |
| RF-26 | T17, T18, T29, T30 |
| RF-27 | T1, T17, T18 |
| RF-28 | T2, T19, T20, T29, T30, T31, T32 |
| RF-29 | T2, T19, T20 |
| RF-30 | T2, T4, T19, T20 |
| RF-31 | T19, T20, T29, T30, T38 |
| RF-32 | T21, T22, T35, T36 |
| RF-33 | T2, T21, T22, T35, T36 |
| RF-34 | T2, T21, T22, T35, T36 |
| RF-35 | T2, T21, T22, T35, T36 |
| RF-36 | T2, T23, T24, T29, T30, T31, T32 |
| RF-37 | T2, T23, T24, T33, T34 |
| RF-38 | T3, T4, T7, T8, T9, T10, T13, T14, T15, T16, T23, T24 |
| RF-39 | T3, T23, T24, T29, T30, T31, T32 |
| RF-40 | T1, T3, T17, T18, T23, T24 |
| RF-41 | T27, T28 |
| RF-42 | T27, T28 |
| RF-43 | T27, T28 |
| RF-44 | T27, T28 |
| RF-45 | T33, T34 |
| RF-46 | T33, T34 |
| RF-47 | T21, T22, T35, T36, T38 |
| RF-48 | T21, T22, T33, T34, T35, T36, T38 |
| RNF-1 | T19, T20 |
| RNF-2 | T1, T27, T28, T37, T38 |
| RNF-3 | T2, T3, T4, T19, T20, T23, T24 |

## Cambios en el plan detectados al descomponer

- **El plan no define el punto exacto de entrada desde `002-presupuestos` para abrir la propuesta al aceptar un presupuesto.** Las tareas lo cubren desde la UI de staff, pero conviene precisar si aparece como acción en `BudgetResource`, redirección posterior a aceptar, o acción manual sobre presupuestos aceptados.
- **Hay una tensión en `budget_item_id`: el plan pide FK a `budget_items` sin cascada, pero también admite que el ítem pueda dejar de existir.** Si `budget_items` se borra físicamente, una FK restrictiva impediría ese borrado; si usa soft delete, no hay problema. Conviene aclarar el comportamiento esperado de `budget_items` en la spec `002` o en este plan.
- **El plan no fija el tamaño máximo de la columna ni normalización del nombre de archivo original.** Solo define `file_path` y `file_extension`; si se necesita mostrar nombre original al staff o cliente, eso no está cubierto por el modelo de datos actual.
- **RNF-2 depende del entorno de medición.** La tarea T37 lo hace verificable, pero el plan no define base de datos, hardware ni tolerancia para medir “menos de 2 segundos”; conviene explicitar que se valida en el entorno de test/local del proyecto.
