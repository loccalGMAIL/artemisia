# 004 — Plan técnico: Piezas

| | |
|---|---|
| **Spec** | docs/specs/004-piezas/spec.md (versión aprobada) |
| **Estado** | Aprobado |
| **Fecha** | 2026-09-10 |
| **Autor** | Claudio |

## 1. Resumen del enfoque

`Piece` es la unidad de producción, con SoftDeletes para "descartar" (mismo patrón que `clients` y
`budgets`) y un historial genérico igual al de las specs anteriores. Los envíos a aprobación viven
en su propia tabla, porque tienen un ciclo de vida (pendiente → resuelto) distinto del historial
de solo alta: ahí se apoyan tanto la consulta del staff como la del cliente sobre sus propios
envíos. La propuesta de piezas por ítem (RF-1/RF-2) es un paso de formulario, no una entidad
persistida: solo se graba algo cuando el staff confirma.

## 2. Chequeo contra la constitución

- **Principio 1 (Stack mínimo)**: sin dependencias nuevas. El archivo del envío a aprobación se
  guarda con el filesystem nativo de Laravel; la validación de formato usa las reglas de
  validación nativas (`mimes:`).
- **Principio 2 (La spec manda)**: cubre exactamente los RF/RNF de
  `docs/specs/004-piezas/spec.md`.
- **Principio 3 (Lógica fuera de la interfaz)**: transiciones de estado, validación de archivo,
  reglas de delegación y de aprobación viven en Actions y en el modelo `Piece`; `PieceResource` y
  la página del portal de clientes solo orquestan.
- **Principio 4 (TDD estricto)**: ver sección 9.
- **Principio 5 (Persistencia explícita)**: `pieces` usa SoftDeletes (el principio nombra
  explícitamente a "piezas" entre las entidades que nunca se borran físicamente); `piece_histories`
  y `piece_approval_submissions` nunca se purgan ni se eliminan.
- **Principio 6 (Idioma)**: clases y columnas en inglés (`Piece`, `PieceApprovalSubmission`); todo
  mensaje visible sale de `lang/es/pieces.php`.

## 3. Decisiones técnicas

### D-1: `pieces` usa SoftDeletes para "descartar"

- **Decisión**: descartar una pieza (RF-26) es `delete()` sobre `Piece`, que usa `SoftDeletes`.
- **Motivo**: coincide con "piezas… nunca se borran físicamente: SoftDeletes" del principio 5, y
  con el mismo patrón ya usado para `clients` y `budgets`.
- **Alternativa descartada**: columna `discarded_at` propia. Redundante con lo que SoftDeletes ya
  resuelve.
- **Consecuencias**: el listado (RF-41) usa el scope por defecto de Eloquent, que excluye las
  descartadas (RF-27).

### D-2: La propuesta de piezas es un paso de formulario, no una entidad persistida

- **Decisión**: RF-1 y RF-2 se resuelven calculando en memoria una lista de piezas sugeridas (una
  por ítem, con su cantidad) cuando el staff abre la pantalla de generación; dividir o quitar
  líneas de esa lista ocurre sobre ese estado de formulario. Solo al confirmar (RF-4) se crean
  filas reales en `pieces`.
- **Motivo**: la propuesta no tiene valor de negocio propio una vez descartada o confirmada; no
  hay nada que auditar ni consultar después sobre una propuesta no confirmada.
- **Alternativa descartada**: tabla `piece_proposals` transitoria que se persiste y se borra o se
  confirma. Agrega esquema y un ciclo de vida para un estado puramente de UI.
- **Consecuencias**: `ProposePiecesFromBudgetAction` no escribe nada; `CreatePiecesFromProposalAction`
  es la única que persiste piezas a partir de una propuesta ya editada por el staff.

### D-3: Envíos a aprobación en tabla propia, con resolución como update de la misma fila

- **Decisión**: `piece_approval_submissions` tiene su propio ciclo de vida (se crea pendiente, se
  actualiza al resolverse con `resolution`, `resolved_by`, `resolved_at` y, si aplica,
  `rejection_reason`), en vez de modelarse como entradas del historial genérico.
- **Motivo**: un envío tiene campos propios (archivo, resultado, motivo) que no encajan bien en el
  patrón snapshot `old_value`/`new_value` de `piece_histories`; y RF-33, RF-36 y RF-37 necesitan
  consultarlo directo, con su archivo y su resultado, no reconstruirlo desde un historial genérico.
- **Alternativa descartada**: registrar cada envío y su resolución como entradas de
  `piece_histories`. Descartada porque el archivo y el motivo de rechazo no son un simple
  valor-anterior/valor-nuevo.
- **Consecuencias**: `piece_approval_submissions` no es de solo alta (a diferencia de las tablas
  de historial de esta y de las specs anteriores): admite un update para resolverla.

### D-4: Historial genérico de la pieza en tabla propia, mismo patrón que las specs anteriores

- **Decisión**: `piece_histories` con discriminador `field` (`created`, `status_changed`,
  `assignee_changed`, `due_date_changed`) y columnas JSON `old_value`/`new_value`.
- **Motivo**: consistencia con `client_histories` (003), `account_histories` (001) y
  `budget_histories` (002).
- **Alternativa descartada**: una tabla de historial por sección. Redundancia de esquema.
- **Consecuencias**: toda Action que cambia una pieza (salvo los envíos, ver D-3) escribe un
  `PieceHistory::create()` al final.

### D-5: Archivo del envío en el filesystem nativo de Laravel, sin librería de medios

- **Decisión**: el archivo de cada envío a aprobación se guarda con el disco de Laravel
  configurado (`local` u otro), referenciado por su ruta en `piece_approval_submissions.file_path`.
- **Motivo**: un archivo por envío no justifica una librería de gestión de medios.
- **Alternativa descartada**: `spatie/laravel-medialibrary`. Fuera del stack (principio 1) y
  sobredimensionada para este caso.
- **Consecuencias**: la validación de formato y tamaño (RF-29, RF-31, RNF-1) se hace con las
  reglas de validación nativas de Laravel (`mimes:jpg,png,pdf,mp4`, `max:102400`).

### D-6: Un responsable como columna en `pieces`, sin tabla de asignaciones histórica

- **Decisión**: `assignee_id` nullable en `pieces`; reasignar reemplaza el valor.
- **Motivo**: RF-12 fija la cardinalidad en "a lo sumo un responsable"; a quién estuvo asignada
  antes ya queda en `piece_histories` (`field = assignee_changed`).
- **Alternativa descartada**: tabla de asignaciones con fecha de inicio/fin. Redundante con el
  historial genérico.
- **Consecuencias**: el filtro "mis piezas delegadas" (RF-43) es una consulta directa por
  `assignee_id = auth()->id()`.

### D-7: Independencia entre `pieces` y cambios posteriores del presupuesto o sus ítems

- **Decisión**: ninguna Action de `002-presupuestos` (por ejemplo, `RevertBudgetToSentAction` o
  `RemoveBudgetItemAction`) dispara actualización alguna sobre `pieces`; una vez creada, una pieza
  vive de forma independiente de lo que pase después con el presupuesto o el ítem que la originó.
- **Motivo**: cumple RF-8 explícitamente ("no alterará las piezas ya generadas… si vuelve al
  estado enviado o si sus ítems se modifican después").
- **Alternativa descartada**: un listener o evento que sincronice piezas cuando cambia el
  presupuesto o el ítem. Descartada porque la spec pide exactamente lo contrario: que no se
  alteren.
- **Consecuencias**: `budget_item_id` puede quedar apuntando a un ítem que ya no exista si se
  quita del presupuesto (ver Riesgos); no se agrega ninguna clave foránea con borrado en cascada.

## 4. Modelo de datos

### pieces

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| budget_id | bigint unsigned | no | — | FK a `budgets` (spec `002`) |
| budget_item_id | bigint unsigned | sí | null | FK a `budget_items`; null en piezas sueltas (RF-5) |
| name | string(150) | no | — | |
| description | text | sí | null | |
| work_category_id | bigint unsigned | no | — | FK a `work_categories` (spec `002`) |
| status | string(20) | no | `pending` | enum `PieceStatus` |
| assignee_id | bigint unsigned | sí | null | FK a `users`; responsable (D-6) |
| due_date | date | sí | null | fecha de entrega comprometida |
| created_by | bigint unsigned | no | — | FK a `users` |
| created_at / updated_at | timestamp | — | — | |
| deleted_at | timestamp | sí | null | "descartada" (SoftDeletes, D-1) |

- **Índices**: índice sobre `budget_id`; índice sobre `status`; índice sobre `assignee_id`;
  índice sobre `due_date`; índice sobre `deleted_at`.
- **Claves foráneas**: `budget_id` → `budgets.id`; `budget_item_id` → `budget_items.id` (sin
  cascada de borrado, ver D-7); `work_category_id` → `work_categories.id`; `assignee_id` →
  `users.id`; `created_by` → `users.id`.
- **Enums**: `PieceStatus` (`pending`, `in_production`, `in_review`, `client_approval`,
  `approved`, `delivered`).
- **Soft deletes**: sí.

### piece_approval_submissions

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| piece_id | bigint unsigned | no | — | FK a `pieces` |
| file_path | string(255) | no | — | ruta en el disco configurado |
| file_extension | string(10) | no | — | `jpg`, `png`, `pdf` o `mp4` |
| submitted_by | bigint unsigned | no | — | FK a `users`; quien envió |
| submitted_at | timestamp | no | — | |
| resolution | string(10) | sí | null | enum `PieceApprovalResolution`; null mientras está pendiente |
| resolved_by | bigint unsigned | sí | null | FK a `users`; cuenta cliente que resolvió |
| resolved_at | timestamp | sí | null | |
| rejection_reason | string(500) | sí | null | solo si `resolution = rejected` |
| created_at / updated_at | timestamp | — | — | |

- **Índices**: índice sobre `piece_id`; índice sobre `resolved_by`.
- **Claves foráneas**: `piece_id` → `pieces.id`; `submitted_by` → `users.id`; `resolved_by` →
  `users.id`.
- **Enums**: `PieceApprovalResolution` (`approved`, `rejected`).
- **Soft deletes**: no; ningún envío se elimina (RF-30).

### piece_histories

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| piece_id | bigint unsigned | no | — | FK a `pieces` |
| field | string(20) | no | — | enum `PieceHistoryField` |
| old_value | json | sí | null | null en la creación |
| new_value | json | no | — | |
| author_id | bigint unsigned | no | — | FK a `users` |
| created_at | timestamp | no | — | sin `updated_at`: solo alta |

- **Índices**: índice compuesto `(piece_id, created_at)`.
- **Claves foráneas**: `piece_id` → `pieces.id`; `author_id` → `users.id`.
- **Enums**: `PieceHistoryField` (`created`, `status_changed`, `assignee_changed`,
  `due_date_changed`).
- **Soft deletes**: no aplica.

Migraciones a crear, en orden:

1. `create_pieces_table` (referencia `budgets`, `budget_items` y `work_categories` de la spec
   `002`, y `users` de la spec `001`)
2. `create_piece_approval_submissions_table`
3. `create_piece_histories_table`

## 5. Estructura de módulos

- **Models**: `Piece` (nuevo, `SoftDeletes`), `PieceApprovalSubmission` (nuevo), `PieceHistory`
  (nuevo).
- **Enums**: `PieceStatus`, `PieceApprovalResolution`, `PieceHistoryField`.
- **Actions**:
  - `ProposePiecesFromBudgetAction` — calcula la propuesta en memoria (D-2), no persiste nada.
  - `CreatePiecesFromProposalAction` — persiste las piezas a partir de la propuesta confirmada.
  - `CreateLoosePieceAction` — crea una pieza suelta.
  - `AssignPieceOwnerAction` — delega o reasigna el responsable; rechaza si la pieza está
    entregada.
  - `SetPieceDueDateAction` — carga o modifica la fecha de entrega comprometida.
  - `MarkPieceInProductionAction` / `MarkPieceInReviewAction` — transiciones de estado.
  - `SendPieceForClientApprovalAction` — valida y guarda el archivo, crea el
    `PieceApprovalSubmission`, deja la pieza en `client_approval`.
  - `ApprovePieceAction` / `RejectPieceAction` — resuelven el envío vigente; la segunda devuelve
    la pieza a `in_production`.
  - `MarkPieceDeliveredAction` — transición final.
  - `DiscardPieceAction` — soft delete, solo desde `pending`.
- **Policies**: `PiecePolicy` (`admin` y `staff` igual, para todas las acciones de producción y
  consulta); `PieceApprovalPortalPolicy` (rol `cliente`: `view`, `approve`, `reject`, acotado a su
  `client_id`).
- **Filament Resources / Pages / Widgets**:
  - Panel `staff`: `PieceResource` (list/create desde la propuesta/edit/view), relation manager de
    envíos a aprobación, relation manager de historial, acciones de transición de estado.
  - Panel `client`: `PieceApprovalPage` — piezas en `client_approval` para aprobar/rechazar, y las
    ya resueltas por esa misma cuenta (RF-37), incluidas las que volvieron a producción.
- **Rutas**: ninguna manual; ambos paneles ya existen (spec `001`).
- **Traducciones**: `lang/es/pieces.php` con etiquetas, nombres de estado y mensajes de rechazo
  (RF-7, RF-31, RF-47, RF-48).

## 6. Contratos de Actions y servicios

- **ProposePiecesFromBudgetAction::handle(Budget $budget): array** — devuelve, sin persistir, una
  lista de líneas propuestas (una por ítem, con nombre y cantidad); lanza excepción si el
  presupuesto no está aceptado.
- **CreatePiecesFromProposalAction::handle(Budget $budget, array $lines, User $author): Collection**
  — cada línea puede representar una o varias piezas (según cómo el staff dividió la cantidad);
  crea una `Piece` por unidad resultante, asociada a su `budget_item_id`, en estado `pending`;
  registra `PieceHistory` de alta por cada una.
- **CreateLoosePieceAction::handle(Budget $budget, array $data, User $author): Piece** — exige
  `work_category_id`; crea en `pending` sin `budget_item_id`; registra historial.
- **AssignPieceOwnerAction::handle(Piece $piece, User $assignee): Piece** — rechaza si
  `$piece->status` es `delivered`; registra historial.
- **SetPieceDueDateAction::handle(Piece $piece, ?Carbon $dueDate): Piece** — registra historial.
- **MarkPieceInProductionAction::handle(Piece $piece): Piece** / **MarkPieceInReviewAction::handle(Piece $piece): Piece**
  — validan el estado de origen (`pending`→`in_production`, `in_production`→`in_review`); registran
  historial.
- **SendPieceForClientApprovalAction::handle(Piece $piece, UploadedFile $file): PieceApprovalSubmission**
  — exige que la pieza esté `in_review`; valida formato (`jpg`, `png`, `pdf`, `mp4`) y tamaño
  (≤100 MB), rechazando con motivo si no cumple (RF-31); guarda el archivo, crea el envío
  pendiente, deja la pieza en `client_approval`.
- **ApprovePieceAction::handle(PieceApprovalSubmission $submission, User $clientAccount): Piece**
  — exige que la pieza esté `client_approval`; resuelve el envío como `approved`; deja la pieza en
  `approved`.
- **RejectPieceAction::handle(PieceApprovalSubmission $submission, User $clientAccount, ?string $reason): Piece**
  — resuelve el envío como `rejected` (acepta `$reason` vacío); devuelve la pieza a
  `in_production`.
- **MarkPieceDeliveredAction::handle(Piece $piece): Piece** — exige que la pieza esté `approved`.
- **DiscardPieceAction::handle(Piece $piece): void** — exige `pending`; `delete()`.

## 7. Reglas de negocio y validaciones

- Toda pieza pertenece a un presupuesto en estado aceptado al momento de crearse (RF-6, RF-7); una
  vez creada, ningún cambio posterior del presupuesto o de sus ítems la modifica (RF-8, D-7).
- Una pieza generada desde un ítem conserva la referencia a ese ítem (RF-3); una suelta exige que
  se le indique la categoría de trabajo (RF-10).
- A lo sumo un responsable por pieza; no se puede delegar ni reasignar una pieza entregada (RF-11,
  RF-12, RF-14, RF-25).
- La fecha de entrega comprometida es opcional; una pieza sin entregar y con esa fecha vencida se
  considera atrasada (RF-15 a RF-17).
- El estado sigue el camino pendiente → en producción → en revisión → en aprobación del cliente →
  aprobada → entregada, con la única reversión automática de un rechazo del cliente, que vuelve la
  pieza a en producción (RF-18 a RF-24, RF-34).
- Una pieza entregada no admite cambios de estado, de archivo ni de responsable (RF-25).
- Solo se puede descartar una pieza pendiente; el descarte nunca la elimina (RF-26, RF-27, RF-40).
- Cada envío a aprobación admite un único archivo, en formato JPG, PNG, PDF o MP4, de hasta 100
  MB; el que no cumple se rechaza con motivo (RF-28, RF-29, RF-31, RNF-1). Todos los envíos se
  conservan, se resuelvan o no (RF-30).
- Solo la cuenta cliente vinculada al cliente del presupuesto puede aprobar o rechazar, y solo
  mientras la pieza esté en aprobación del cliente; puede consultar sus propios envíos resueltos
  aunque la pieza haya vuelto a producción (RF-32, RF-37, RF-47, RF-48).
- Toda creación y todo cambio de estado, responsable o fecha de entrega queda registrado con valor
  anterior, valor nuevo, autor y fecha (RF-38).

## 8. Autorización

- `PiecePolicy` autoriza por igual a `admin` y `staff` para generar, delegar, cambiar estado,
  descartar y consultar piezas; ninguna acción de esta spec distingue entre ambos roles.
- `PieceApprovalPortalPolicy` autoriza a una cuenta con rol `cliente` únicamente sobre las piezas
  cuyo presupuesto pertenece al cliente al que está vinculada (RF-48), y solo puede aprobar o
  rechazar mientras estén en `client_approval` (RF-47).

## 9. Estrategia de tests

- **Unit**: transición completa de estados, incluida la reversión por rechazo; bloqueo de cambios
  sobre una pieza entregada; validación de formato y tamaño de archivo; que un descarte solo
  proceda desde `pending`; que la pieza no se vea afectada si su presupuesto vuelve a `sent` o si
  se le quita el ítem de origen (RF-8); cálculo de "atrasada".
- **Feature**: generación de piezas desde un presupuesto aceptado (propuesta, división, creación);
  alta de una pieza suelta; delegación y reasignación de responsable; envío a aprobación con
  archivo válido y con archivo inválido; aprobación y rechazo desde el portal de clientes, y que
  el rechazo devuelva la pieza a producción; consulta del cliente sobre sus propios envíos
  resueltos; rechazo de acciones de un cliente sobre piezas de otro cliente; listado con filtros
  de estado, responsable y atraso.
- **Factories nuevas**: `PieceFactory`, `PieceApprovalSubmissionFactory`, `PieceHistoryFactory`.
- **Seeders**: ninguno nuevo (reutiliza `work_categories` de la spec `002`).

## 10. Mapa RF → componente

| RF | Descripción corta | Cubierto por |
|---|---|---|
| RF-1 | Propuesta de una pieza por ítem al aceptar | `ProposePiecesFromBudgetAction` (D-2) |
| RF-2 | Dividir/quitar en la propuesta | Edición en memoria de la propuesta (D-2) |
| RF-3 | Pieza asociada a su ítem de origen | Columna `budget_item_id` |
| RF-4 | Confirmar y crear las piezas | `CreatePiecesFromProposalAction` |
| RF-5 | Piezas sueltas | `CreateLoosePieceAction` |
| RF-6, RF-7 | Exige presupuesto aceptado, rechazo si no | `CreatePiecesFromProposalAction`, `CreateLoosePieceAction` |
| RF-8 | Independencia de cambios posteriores del presupuesto | Ausencia deliberada de acoplamiento (D-7) |
| RF-9 | Nombre y descripción | Columnas `name`/`description` |
| RF-10 | Categoría de trabajo heredada o exigida | Columna `work_category_id` |
| RF-11, RF-14 | Delegar y reasignar responsable | `AssignPieceOwnerAction` |
| RF-12 | A lo sumo un responsable | Columna `assignee_id` (D-6) |
| RF-13 | Visible en piezas delegadas a esa cuenta | `AssignPieceOwnerAction`, filtro de `PieceResource` (RF-43) |
| RF-15, RF-16 | Fecha de entrega opcional | `SetPieceDueDateAction` |
| RF-17 | Señalada como atrasada | Scope `Piece::overdue()` |
| RF-18, RF-19 | Estado único, nace pendiente | Enum `PieceStatus`, Actions de creación |
| RF-20, RF-21 | Pendiente→producción→revisión | `MarkPieceInProductionAction`, `MarkPieceInReviewAction` |
| RF-22, RF-23 | Envío a aprobación cambia el estado | `SendPieceForClientApprovalAction` |
| RF-24 | Aprobada→entregada | `MarkPieceDeliveredAction` |
| RF-25 | Entregada bloquea cambios | Validación de estado en las Actions de asignación/estado |
| RF-26, RF-27 | Descartar, conservar | `DiscardPieceAction`, SoftDeletes (D-1) |
| RF-28 | Subir archivo al enviar | `SendPieceForClientApprovalAction` |
| RF-29 | Formatos admitidos | Validación `mimes:` (D-5, D-7) |
| RF-30 | Conserva cada archivo con su fecha | Tabla `piece_approval_submissions` |
| RF-31 | Rechazo de archivo inválido con motivo | `SendPieceForClientApprovalAction` |
| RF-32 | Cliente aprueba/rechaza en aprobación | `PieceApprovalPortalPolicy`, `ApprovePieceAction`/`RejectPieceAction` |
| RF-33 | Aprobar deja aprobada + fecha | `ApprovePieceAction` |
| RF-34 | Rechazar vuelve a producción + motivo | `RejectPieceAction` |
| RF-35 | Motivo vacío aceptado | `RejectPieceAction` |
| RF-36 | Historial completo de envíos (staff) | Relation manager en `PieceResource` |
| RF-37 | Cliente consulta sus propios envíos resueltos | `PieceApprovalPage` |
| RF-38 | Registro de cada cambio | `piece_histories`, escritura desde cada Action |
| RF-39 | Consulta cronológica | Relation manager de historial en `PieceResource` |
| RF-40 | Nunca elimina pieza ni historial | SoftDeletes + `piece_histories` append-only |
| RF-41 | Listado con columnas | `PieceResource` (tabla) |
| RF-42, RF-43, RF-44 | Filtros estado/responsable/cliente, mis piezas, atrasadas | `PieceResource` (filtros), `Piece::overdue()` |
| RF-45, RF-46 | Portal: solo aprobación/aprobada/entregada | `PieceApprovalPage` |
| RF-47 | Rechazo si no está en aprobación | `PieceApprovalPortalPolicy` |
| RF-48 | Rechazo si es de otro cliente | `PieceApprovalPortalPolicy` |
| RNF-1 | Tope de archivo 100 MB | Validación `max:` en `SendPieceForClientApprovalAction` |
| RNF-2 | Listado <2s con 5.000 piezas | Índices de `pieces` (`status`, `assignee_id`, `due_date`, `budget_id`) |
| RNF-3 | Historial y envíos sin purga | `piece_histories`/`piece_approval_submissions` sin tarea de limpieza |

## 11. Riesgos

- **Dependencia de `budgets`/`budget_items` (spec 002), `clients` y su vínculo con cuentas (spec
  003), y `users` (spec 001)**: implementar en el orden de dependencia (001 → 002 → 003 → 004), no
  en el orden en que se redactaron los planes.
- **`budget_item_id` puede quedar apuntando a un ítem ya inexistente** si ese ítem se quita del
  presupuesto tras una reversión a "enviado" (consecuencia deliberada de D-7): mitigación —
  ninguna clave foránea con borrado en cascada; la relación se vuelve informativa/histórica si el
  ítem deja de existir, sin romper la integridad de la pieza.
- **Archivos de hasta 100 MB pueden impactar el uso de disco** a medida que crece el volumen de
  piezas: mitigación — fuera de este plan; si hace falta, se resuelve cambiando el disco
  configurado (por ejemplo, a uno compatible con S3), sin cambios de código.
- **Edición concurrente de la misma pieza** por dos usuarios de staff: mismo supuesto que en las
  specs anteriores (gana el último guardado), sin mecanismo de bloqueo en esta versión.

## 12. Fuera de alcance del plan

- No implementa ningún canal real de aviso al delegar una pieza (email, push): la spec lo deja
  fuera de alcance.
- No implementa compresión, transcodificación ni generación de miniaturas para los archivos de
  video subidos.
- No implementa la "propuesta" de piezas como una entidad persistida (D-2): es un paso de
  formulario que no deja rastro si se abandona sin confirmar.
- No implementa ningún mecanismo de bloqueo optimista para edición concurrente (ver Riesgos).

## 13. Preguntas abiertas

Ninguna: todas las decisiones técnicas quedaron resueltas en la sección 3, con su alternativa
descartada.

## 14. Aprobación

- [x] Aprobado por Claudio el 2026-09-10
