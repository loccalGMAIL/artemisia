# 004 — Tareas: Piezas

| | |
|---|---|
| **Spec** | docs/specs/004-piezas/spec.md |
| **Plan** | docs/specs/004-piezas/plan.md |
| **Estado** | En curso |
| **Fecha** | 2026-09-10 |

## Convenciones

- Checkbox al inicio de cada tarea. Se marca al terminarla, no antes.
- Orden por dependencias: cada tarea depende solo de las anteriores.
- TDD estricto: el test va antes que la implementación.
- La suite queda verde después de cada tarea de `impl`.
- Cada tarea de `impl` que produce un mensaje visible crea también sus claves en
  `lang/es/pieces.php` (constitución, principio 6). No hay una tarea separada de traducciones.
- **Precondiciones**: requiere `001-acceso-roles-y-paneles`, `003-clientes` (vínculo cuenta-
  cliente) y `002-presupuestos` (presupuestos aceptados con sus ítems y categorías de trabajo)
  implementadas.
- **Orden de implementación entre specs**: 003 → 002 → 004. Esta spec va última porque depende de
  las otras tres.
- **T7 cubre RF-8 con un test que puede pasar en verde sin ninguna implementación nueva**: ver
  "Cambios en el plan detectados".

## Resumen

- Total: 36 tareas.
- Por tipo: 13 test, 14 impl, 3 migration, 6 ui.
- Cubre: RF-1 a RF-48, RNF-1 a RNF-3.

---

## Fase 1: Esquema y modelos

### - [ ] T1: Crear migración de `pieces`

- **Tipo**: migration
- **Cubre**: RF-9, RF-10, RF-18, RF-19
- **Depende de**: —
- **Hecho cuando**: `php artisan migrate:fresh` corre sin errores y `pieces` existe con
  `budget_id`, `budget_item_id` nullable, `name`, `description`, `work_category_id`, `status`
  (default `pending`), `assignee_id`, `due_date`, `created_by`, `deleted_at`, según el plan §4.

### - [ ] T2: Crear migración de `piece_approval_submissions`

- **Tipo**: migration
- **Cubre**: RF-23, RF-30
- **Depende de**: T1
- **Hecho cuando**: la tabla existe con `piece_id`, `file_path`, `file_extension`,
  `submitted_by`, `submitted_at`, `resolution` nullable, `resolved_by`, `resolved_at`,
  `rejection_reason`, sin `deleted_at` (plan D-3).

### - [ ] T3: Crear migración de `piece_histories`

- **Tipo**: migration
- **Cubre**: RF-38
- **Depende de**: T1
- **Hecho cuando**: la tabla existe con `piece_id`, `field`, `old_value`, `new_value`,
  `author_id`, `created_at`, índice `(piece_id, created_at)`, sin columna `updated_at`.

### - [ ] T4: Crear enums, modelos y factories

- **Tipo**: impl
- **Cubre**: RF-18, RF-19
- **Depende de**: T2, T3
- **Hecho cuando**: existen `PieceStatus`, `PieceApprovalResolution`, `PieceHistoryField` como
  backed enums; `Piece` (`SoftDeletes`), `PieceApprovalSubmission` y `PieceHistory` resuelven sus
  relaciones en un test de humo verde; `PieceFactory`, `PieceApprovalSubmissionFactory` y
  `PieceHistoryFactory` existen y producen registros válidos.

---

## Fase 2: Generación de piezas

### - [ ] T5: Escribir test de propuesta de piezas desde un presupuesto aceptado

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-6, RF-7
- **Depende de**: T4
- **Hecho cuando**: `pest --filter=ProposePieces` falla porque `ProposePiecesFromBudgetAction` no
  existe; cubre la propuesta en memoria (una línea por ítem con nombre y cantidad), que la propia
  propuesta no persiste nada, y el rechazo con motivo si el presupuesto no está `accepted`; commit
  del test hecho.

### - [ ] T6: Implementar `ProposePiecesFromBudgetAction`

- **Tipo**: impl
- **Cubre**: RF-1, RF-2, RF-6, RF-7
- **Depende de**: T5
- **Hecho cuando**: el test de T5 pasa y la suite completa queda verde.

### - [ ] T7: Escribir test de independencia frente a cambios posteriores del presupuesto

- **Tipo**: test
- **Cubre**: RF-8
- **Depende de**: T6
- **Hecho cuando**: el test comprueba que revertir el presupuesto a `sent` o modificar/quitar uno
  de sus ítems no altera ninguna pieza ya generada; commit del test hecho aunque pase en verde
  desde el principio (ver "Cambios en el plan detectados": es una guarda de regresión, no un caso
  que hoy falle por falta de código).

### - [ ] T8: Confirmar que ninguna Action de `002-presupuestos` toca `pieces`

- **Tipo**: impl
- **Cubre**: RF-8
- **Depende de**: T7
- **Hecho cuando**: el test de T7 sigue verde y una revisión de `RevertBudgetToSentAction` y
  `RemoveBudgetItemAction` (spec `002`) confirma que ninguna referencia a `Piece` se agregó (plan
  D-7): no hay listener ni evento de sincronización.

### - [ ] T9: Escribir test de confirmación de la propuesta con divisiones y quites

- **Tipo**: test
- **Cubre**: RF-3, RF-4
- **Depende de**: T8
- **Hecho cuando**: el test falla y comprueba que confirmar la propuesta editada (con una línea
  dividida en varias piezas, o con líneas quitadas) crea una `Piece` por unidad resultante,
  asociada a su `budget_item_id`, en estado `pending`, con su asiento de alta en el historial;
  commit del test hecho.

### - [ ] T10: Implementar `CreatePiecesFromProposalAction`

- **Tipo**: impl
- **Cubre**: RF-3, RF-4
- **Depende de**: T9
- **Hecho cuando**: el test de T9 pasa y la suite completa queda verde.

### - [ ] T11: Escribir test de pieza suelta y agregar `CreateLoosePieceAction`

- **Tipo**: test
- **Cubre**: RF-5, RF-6, RF-7, RF-9, RF-10
- **Depende de**: T10
- **Hecho cuando**: el test falla y comprueba que una pieza suelta se crea sin `budget_item_id`
  asociada a un presupuesto aceptado, exige categoría de trabajo, admite nombre y descripción
  opcional, y nace `pending`; luego pasa con `CreateLoosePieceAction` implementada; suite completa
  verde; commit del test hecho antes del de implementación.

---

## Fase 3: Delegación y agenda

### - [ ] T12: Escribir test de delegación y reasignación de responsable

- **Tipo**: test
- **Cubre**: RF-11, RF-12, RF-13, RF-14, RF-38
- **Depende de**: T11
- **Hecho cuando**: el test falla y cubre: delegar una pieza a una cuenta `admin`/`staff`, que
  admite a lo sumo un responsable, que aparece bajo "mis piezas delegadas" para esa cuenta, que se
  puede reasignar mientras no esté entregada, y el asiento de historial; commit del test hecho.

### - [ ] T13: Implementar `AssignPieceOwnerAction`

- **Tipo**: impl
- **Cubre**: RF-11, RF-12, RF-13, RF-14, RF-38
- **Depende de**: T12
- **Hecho cuando**: el test de T12 pasa y la suite completa queda verde.

### - [ ] T14: Escribir test de fecha de entrega comprometida y de piezas atrasadas

- **Tipo**: test
- **Cubre**: RF-15, RF-16, RF-17, RF-38
- **Depende de**: T13
- **Hecho cuando**: el test falla y cubre: crear una pieza sin fecha de entrega, cargarla y
  modificarla después, que una pieza no entregada con esa fecha vencida se señala como atrasada, y
  el asiento de historial; commit del test hecho.

### - [ ] T15: Implementar `SetPieceDueDateAction` y el scope `Piece::overdue()`

- **Tipo**: impl
- **Cubre**: RF-15, RF-16, RF-17, RF-38
- **Depende de**: T14
- **Hecho cuando**: el test de T14 pasa y la suite completa queda verde.

---

## Fase 4: Estados de producción

### - [ ] T16: Escribir test de transición pendiente → producción → revisión

- **Tipo**: test
- **Cubre**: RF-18, RF-19, RF-20, RF-21, RF-38
- **Depende de**: T15
- **Hecho cuando**: el test falla y cubre: una pieza nace `pending`, se puede marcar `in_production`
  solo desde `pending`, y `in_review` solo desde `in_production`, con rechazo de cualquier salto
  fuera de ese orden y asiento de historial; commit del test hecho.

### - [ ] T17: Implementar `MarkPieceInProductionAction` y `MarkPieceInReviewAction`

- **Tipo**: impl
- **Cubre**: RF-18, RF-19, RF-20, RF-21, RF-38
- **Depende de**: T16
- **Hecho cuando**: el test de T16 pasa y la suite completa queda verde.

### - [ ] T18: Escribir test de entrega y bloqueo total de la pieza entregada

- **Tipo**: test
- **Cubre**: RF-24, RF-25, RF-38
- **Depende de**: T17
- **Hecho cuando**: el test falla y comprueba que una pieza `approved` se puede marcar `delivered`,
  y que una pieza `delivered` rechaza cualquier cambio de estado, de archivo o de responsable;
  commit del test hecho.

### - [ ] T19: Implementar `MarkPieceDeliveredAction` y las guardas sobre pieza entregada

- **Tipo**: impl
- **Cubre**: RF-24, RF-25, RF-38
- **Depende de**: T18
- **Hecho cuando**: el test de T18 pasa y la suite completa queda verde.

### - [ ] T20: Escribir test de descarte de pieza pendiente

- **Tipo**: test
- **Cubre**: RF-26, RF-27, RF-40
- **Depende de**: T19
- **Hecho cuando**: el test falla y comprueba que solo una pieza `pending` se puede descartar, que
  queda conservada con su historial fuera del listado, y que nunca se elimina físicamente; commit
  del test hecho.

### - [ ] T21: Implementar `DiscardPieceAction`

- **Tipo**: impl
- **Cubre**: RF-26, RF-27, RF-40
- **Depende de**: T20
- **Hecho cuando**: el test de T20 pasa y la suite completa queda verde.

---

## Fase 5: Envío a aprobación

### - [ ] T22: Escribir test de envío a aprobación con validación de archivo

- **Tipo**: test
- **Cubre**: RF-22, RF-23, RF-28, RF-29, RF-30, RF-31, RNF-1
- **Depende de**: T21
- **Hecho cuando**: el test falla y cubre: solo una pieza `in_review` se envía a aprobación,
  queda en `client_approval` con el envío registrado (archivo y fecha), formatos admitidos
  (JPG/PNG/PDF/MP4) y rechazo de un archivo de más de 100 MB o de formato distinto con motivo, y
  que cada envío se conserva aunque la pieza reciba más de uno; commit del test hecho.

### - [ ] T23: Implementar `SendPieceForClientApprovalAction`

- **Tipo**: impl
- **Cubre**: RF-22, RF-23, RF-28, RF-29, RF-30, RF-31, RNF-1
- **Depende de**: T22
- **Hecho cuando**: el test de T22 pasa y la suite completa queda verde.

### - [ ] T24: Escribir test de historial de envíos para el staff

- **Tipo**: test
- **Cubre**: RF-36
- **Depende de**: T23
- **Hecho cuando**: el test falla y comprueba que un `admin` o `staff` consulta el historial
  completo de envíos de una pieza, con su archivo, su resultado y su motivo de rechazo cuando
  corresponde; commit del test hecho.

### - [ ] T25: Agregar relation manager de envíos a la ficha de staff

- **Tipo**: ui
- **Cubre**: RF-36
- **Depende de**: T24
- **Hecho cuando**: el test de T24 pasa, la ficha de `PieceResource` muestra ese historial y la
  suite completa queda verde.

---

## Fase 6: Aprobación del cliente

### - [ ] T26: Escribir test de aprobación y rechazo con reversión a producción

- **Tipo**: test
- **Cubre**: RF-32, RF-33, RF-34, RF-35
- **Depende de**: T25
- **Hecho cuando**: el test falla y cubre: una cuenta `cliente` vinculada aprueba una pieza en
  `client_approval` y la deja `approved` con fecha; rechazarla la devuelve a `in_production` con
  motivo (aceptando vacío) registrado contra el envío correspondiente; commit del test hecho.

### - [ ] T27: Implementar `ApprovePieceAction` y `RejectPieceAction`

- **Tipo**: impl
- **Cubre**: RF-32, RF-33, RF-34, RF-35
- **Depende de**: T26
- **Hecho cuando**: el test de T26 pasa y la suite completa queda verde.

### - [ ] T28: Escribir test de `PieceApprovalPortalPolicy`

- **Tipo**: test
- **Cubre**: RF-47, RF-48
- **Depende de**: T27
- **Hecho cuando**: el test falla y comprueba que una cuenta `cliente` no puede aprobar/rechazar
  una pieza que no está en `client_approval`, ni actuar sobre una pieza de un cliente distinto al
  que está vinculada, en ambos casos con mensaje de permiso insuficiente; commit del test hecho.

### - [ ] T29: Implementar `PieceApprovalPortalPolicy`

- **Tipo**: impl
- **Cubre**: RF-47, RF-48
- **Depende de**: T28
- **Hecho cuando**: el test de T28 pasa y la suite completa queda verde.

---

## Fase 7: Panel de staff

### - [ ] T30: Escribir test de `PiecePolicy`

- **Tipo**: test
- **Cubre**: — (autorización transversal a RF-4, RF-11, RF-15, RF-20, RF-26, RF-39)
- **Depende de**: T29
- **Hecho cuando**: el test falla y comprueba que `admin` y `staff` tienen los mismos permisos
  para generar, delegar, cambiar estado, descartar y consultar piezas; commit del test hecho.

### - [ ] T31: Implementar `PiecePolicy`

- **Tipo**: impl
- **Cubre**: — (autorización transversal)
- **Depende de**: T30
- **Hecho cuando**: el test de T30 pasa y la suite completa queda verde.

### - [ ] T32: Escribir test de listado con filtros de estado, responsable, cliente y atraso

- **Tipo**: test
- **Cubre**: RF-41, RF-42, RF-43, RF-44, RNF-2
- **Depende de**: T31
- **Hecho cuando**: el test falla y cubre: columnas del listado, filtro por estado/responsable/
  cliente, el filtro "mis piezas delegadas" acotado a la cuenta propia, el filtro de atrasadas, y
  que el listado con 5.000 piezas responde en menos de 2 segundos; commit del test hecho.

### - [ ] T33: Implementar la tabla de `PieceResource` con sus filtros

- **Tipo**: ui
- **Cubre**: RF-41, RF-42, RF-43, RF-44, RNF-2
- **Depende de**: T32
- **Hecho cuando**: el test de T32 pasa, la tabla del recurso Filament refleja todos los filtros y
  la suite completa queda verde.

### - [ ] T34: Crear la ficha de la pieza con transiciones, relation manager de historial y pantalla de generación

- **Tipo**: ui
- **Cubre**: RF-1, RF-2, RF-4, RF-5, RF-11, RF-14, RF-15, RF-20, RF-21, RF-24, RF-26, RF-39
- **Depende de**: T33
- **Hecho cuando**: desde `PieceResource` un `admin` o `staff` abre la pantalla de generación de
  piezas de un presupuesto aceptado (edita la propuesta, divide o quita líneas y confirma), crea
  una pieza suelta, y desde la ficha ejecuta las transiciones de estado, delega/reasigna
  responsable, carga fecha de entrega, descarta y consulta el historial cronológico — todo
  delegando en las Actions de las fases 2 a 4.

---

## Fase 8: Portal de clientes

### - [ ] T35: Escribir test de visibilidad acotada en el portal de clientes

- **Tipo**: test
- **Cubre**: RF-37, RF-45, RF-46
- **Depende de**: T34
- **Hecho cuando**: el test falla y cubre: una cuenta `cliente` vinculada ve solo las piezas de su
  cliente que están en `client_approval`, `approved` o `delivered` (nunca `pending`,
  `in_production` ni `in_review`), y puede consultar los envíos que ella misma resolvió, incluidos
  los rechazados aunque la pieza haya vuelto a producción; commit del test hecho.

### - [ ] T36: Crear `PieceApprovalPage` en el panel de clientes

- **Tipo**: ui
- **Cubre**: RF-37, RF-45, RF-46
- **Depende de**: T35
- **Hecho cuando**: el test de T35 pasa, la página lista esas piezas con acciones de
  aprobar/rechazar delegando en las Actions de la fase 6, y la suite completa queda verde.

---

## Mapa RF → tareas

| RF | Tareas |
|---|---|
| RF-1 | T5, T6, T34 |
| RF-2 | T5, T6, T34 |
| RF-3 | T9, T10 |
| RF-4 | T9, T10, T34 |
| RF-5 | T11, T34 |
| RF-6 | T1, T5, T6, T11 |
| RF-7 | T5, T6, T11 |
| RF-8 | T7, T8 |
| RF-9 | T1, T11 |
| RF-10 | T1, T11 |
| RF-11 | T12, T13, T34 |
| RF-12 | T12, T13 |
| RF-13 | T12, T13 |
| RF-14 | T12, T13, T34 |
| RF-15 | T14, T15, T34 |
| RF-16 | T14, T15 |
| RF-17 | T14, T15 |
| RF-18 | T1, T4, T16, T17 |
| RF-19 | T1, T4, T16, T17 |
| RF-20 | T16, T17, T34 |
| RF-21 | T16, T17, T34 |
| RF-22 | T22, T23 |
| RF-23 | T2, T22, T23 |
| RF-24 | T18, T19, T34 |
| RF-25 | T18, T19 |
| RF-26 | T20, T21, T34 |
| RF-27 | T20, T21 |
| RF-28 | T22, T23 |
| RF-29 | T22, T23 |
| RF-30 | T2, T22, T23 |
| RF-31 | T22, T23 |
| RF-32 | T26, T27 |
| RF-33 | T26, T27 |
| RF-34 | T26, T27 |
| RF-35 | T26, T27 |
| RF-36 | T24, T25 |
| RF-37 | T35, T36 |
| RF-38 | T3, T12, T13, T14, T15, T16, T17, T18, T19 |
| RF-39 | T34 |
| RF-40 | T20, T21 |
| RF-41 | T32, T33 |
| RF-42 | T32, T33 |
| RF-43 | T32, T33 |
| RF-44 | T32, T33 |
| RF-45 | T35, T36 |
| RF-46 | T35, T36 |
| RF-47 | T28, T29 |
| RF-48 | T28, T29 |
| RNF-1 | T22, T23 |
| RNF-2 | T32, T33 |
| RNF-3 | *sin test automatizado — ver "Cambios en el plan detectados"* |

## Cambios en el plan detectados al descomponer

- **T7 rompe la regla "el test debe fallar antes de implementar"**: RF-8 exige que nada posterior
  altere una pieza ya generada, y esa propiedad ya se cumple por la ausencia deliberada de
  acoplamiento (plan D-7) desde que existen las Actions de las fases 2-4. El test de T7 es una
  guarda de regresión que puede pasar en verde desde que se escribe. Se mantiene como tarea propia
  porque documenta un requisito explícito (RF-8) que de otro modo ningún test cubre, y T8 lo deja
  asentado con una revisión puntual del código de la spec `002`.
- **RF-1 no define si la pantalla de generación de piezas se abre automáticamente al aceptar un
  presupuesto o si el staff la abre a mano**: el plan (D-2) tampoco lo resuelve, solo dice que la
  propuesta se calcula "cuando el staff abre la pantalla". T34 asume que se abre a mano desde
  `BudgetResource` o `PieceResource`, no como efecto automático de `AcceptBudgetAction` (spec
  `002`). Si se prefiere el disparo automático, la decisión vuelve al plan antes de tocar T34.
- **Ningún RF pide un enlace de navegación entre `BudgetResource` (spec `002`) y las piezas de ese
  presupuesto**, ni entre `ClientResource` (spec `003`) y las piezas de sus presupuestos: es un
  hueco de navegación de UI, no de requisito, y no se convierte en tarea porque no hay RF que lo
  exija.
- **RNF-3 (historial y envíos sin purga) no tiene tarea de test dedicada**, mismo patrón que RNF-7
  de la spec `003` y RNF-8 de la spec `002`: ausencia deliberada de un comando de limpieza sobre
  `piece_histories` y `piece_approval_submissions`.
- **`lang/es/pieces.php` no tiene una tarea propia**: sus claves se reparten entre T6, T10, T11,
  T13, T15, T17, T19, T21, T23, T27, T29, T31, T33, T34, T36 y toda otra tarea de `impl` que
  produzca un mensaje visible, siguiendo el mismo patrón que las specs `001`, `002` y `003`.
