# 002 — Plan técnico: Presupuestos

| | |
|---|---|
| **Spec** | docs/specs/002-presupuestos/spec.md (versión aprobada) |
| **Estado** | Aprobado |
| **Fecha** | 2026-09-10 |
| **Autor** | Claudio |

## 1. Resumen del enfoque

El catálogo y los presupuestos siguen el mismo patrón ya validado en los planes de `001` y `003`:
archivado/descarte con SoftDeletes, historial genérico en una tabla de solo alta con snapshot
JSON, y totales desnormalizados para que el listado no dependa de agregaciones costosas. La única
pieza nueva respecto de los planes anteriores es la generación de PDF, que exige una dependencia
fuera del stack mínimo y que este plan justifica y deja lista para que el PR la declare.

## 2. Chequeo contra la constitución

- **Principio 1 (Stack mínimo)**: una sola dependencia nueva, justificada por escrito: ver D-7
  (generación de PDF). El resto del plan no agrega nada fuera de Laravel/Filament/MySQL.
- **Principio 2 (La spec manda)**: cubre exactamente los RF/RNF de
  `docs/specs/002-presupuestos/spec.md`.
- **Principio 3 (Lógica fuera de la interfaz)**: cálculo de totales, transiciones de estado,
  validación de ítems y armado del PDF viven en Actions y en el modelo `Budget`; `BudgetResource`
  y `ServiceResource` solo orquestan.
- **Principio 4 (TDD estricto)**: ver sección 9.
- **Principio 5 (Persistencia explícita)**: `budgets` usa SoftDeletes para "descartar" (RF-51);
  `budget_histories` y `service_price_histories` son tablas de solo alta, sin purga (RNF-8).
- **Principio 6 (Idioma)**: clases y columnas en inglés (`Service`, `Budget`, `BudgetItem`); toda
  etiqueta y mensaje sale de `lang/es/budgets.php`.

## 3. Decisiones técnicas

### D-1: Identificador correlativo = `id` autoincremental de `budgets`

- **Decisión**: el identificador de RF-13 es el PK autoincremental de la tabla, no una columna
  separada.
- **Motivo**: InnoDB ya garantiza correlativo único y no reutilizado en operación normal; es
  exactamente lo que pide RF-13.
- **Alternativa descartada**: una tabla de secuencia dedicada o una columna `number`
  independiente. Redundante con lo que el propio PK ya resuelve.
- **Consecuencias**: el número que se muestra en listado, ficha y PDF es `budget.id`.

### D-2: Totales desnormalizados y recalculados en cada cambio, no calculados al vuelo

- **Decisión**: `subtotal`, `discount_amount` y `total` son columnas de `budgets`, recalculadas
  por `RecalculateBudgetTotalsAction` cada vez que cambian los ítems o el descuento.
- **Motivo**: RNF-7 exige listar 2.000 presupuestos con su total en menos de 2 segundos; sumar los
  ítems de cada fila en el momento de listar exigiría una agregación por presupuesto en esa
  escala.
- **Alternativa descartada**: accessor que suma los ítems en cada lectura. Descartado por el costo
  de esa agregación repetida sobre el listado completo.
- **Consecuencias**: toda Action que agrega, edita o quita un ítem, o que carga/quita el
  descuento, termina llamando a `RecalculateBudgetTotalsAction` antes de retornar.

### D-3: Unicidad de nombre de servicio con columna generada normalizada

- **Decisión**: columna virtual generada `name_normalized` (`LOWER(TRIM(name))`) con índice
  `UNIQUE`, sobre todos los servicios (no hay soft delete en `services`, así que ya incluye a los
  desactivados).
- **Motivo**: RF-3 exige ignorar mayúsculas y espacios exteriores, y que la comparación incluya
  los desactivados.
- **Alternativa descartada**: `UNIQUE(name)` simple. No ignora mayúsculas ni espacios.
- **Consecuencias**: `CreateServiceAction` consulta contra `name_normalized`, no contra `name`.

### D-4: `budgets` usa SoftDeletes para "descartar"

- **Decisión**: descartar un presupuesto (RF-51) es `delete()` sobre `Budget`, que usa el trait
  `SoftDeletes`.
- **Motivo**: coincide con "Presupuestos… nunca se borran físicamente: SoftDeletes" del principio
  5, y con el mismo patrón que `clients` en el plan de la spec `003`.
- **Alternativa descartada**: columna `discarded_at` propia. Duplica lo que SoftDeletes ya
  resuelve.
- **Consecuencias**: la spec no pide restaurar un presupuesto descartado, así que
  `DiscardBudgetAction` no tiene contraparte `Restore` (ver sección 12).

### D-5: Ítems quitados se eliminan físicamente; el snapshot queda en el historial

- **Decisión**: `RemoveBudgetItemAction` hace `delete()` físico sobre la fila de `budget_items`;
  el dato queda preservado solo como snapshot en `budget_histories`.
- **Motivo**: mismo patrón que `client_contacts` en el plan de la spec `003` (su D-9); el
  principio 5 no nombra a los ítems entre las entidades que nunca se borran físicamente, y el
  historial ya preserva el dato (RF-32).
- **Alternativa descartada**: SoftDeletes también en `budget_items`. Redundante con lo que ya
  cubre `budget_histories`.
- **Consecuencias**: `budget_items` no lleva `deleted_at`.

### D-6: Historial del presupuesto en una única tabla con snapshot

- **Decisión**: `budget_histories` con discriminador `field` (`item_added`, `item_updated`,
  `item_removed`, `discount_changed`, `status_changed`, `header_changed`) y columnas JSON
  `old_value` / `new_value`.
- **Motivo**: mismo patrón ya validado en `client_histories` (spec 003) y `account_histories`
  (spec 001); todas las secciones de esta spec piden la misma forma de registro.
- **Alternativa descartada**: una tabla de historial por sección (ítems, descuento, estado,
  cabecera). Redundancia de esquema.
- **Consecuencias**: toda Action de presupuesto escribe un `BudgetHistory::create()` al final.

### D-7: Generación de PDF con `barryvdh/laravel-dompdf`

- **Decisión**: se agrega la dependencia `barryvdh/laravel-dompdf` (envoltorio de
  `dompdf/dompdf`) para renderizar una vista Blade a PDF.
- **Motivo**: la propia spec anticipa esta dependencia fuera del stack (sección 9) y exige
  justificarla por escrito. `dompdf` es una librería PHP pura, sin binario externo ni runtime
  adicional, se integra directo con las vistas Blade ya usadas en la landing, y renderiza un PDF
  de 100 ítems muy por debajo de los 5 segundos de RNF-6 al ser síncrono y local.
- **Alternativa descartada**: `spatie/laravel-pdf` (requiere Chrome headless vía Browsershot,
  agregando un runtime de Node/Puppeteer más pesado del que esta spec necesita) y generar el PDF
  en el navegador (movería el armado del documento a la interfaz, violando el principio 3).
- **Consecuencias**: **esta dependencia se declara y justifica por escrito en el PR que la
  agregue**, tal como exige el principio 1.

### D-8: Acción de WhatsApp como enlace `wa.me`, sin dependencia

- **Decisión**: `BuildWhatsAppLinkAction` arma una URL `https://wa.me/<teléfono>?text=<mensaje
  codificado>`; el botón de Filament abre esa URL en una pestaña nueva.
- **Motivo**: cumple RF-68 y RF-70 sin necesitar la API de WhatsApp Business, ya descartada en la
  spec (sección 8).
- **Alternativa descartada**: cualquier SDK de integración con WhatsApp. Fuera de alcance de la
  spec y sin necesidad para un enlace simple.
- **Consecuencias**: si el cliente no tiene teléfono de contacto (según `Client::contactPhone()`
  de la spec `003`), la acción no se ofrece (RF-69).

### D-9: `service_price_histories` sin purga

- **Decisión**: ninguna tarea de limpieza sobre `service_price_histories` ni sobre
  `budget_histories`.
- **Motivo**: RNF-8 exige conservarlos sin purgarlos en ningún plazo, a diferencia de
  `access_logs` en la spec `001` (que sí se purga a los 24 meses).
- **Alternativa descartada**: aplicar la misma purga que a `access_logs`. Contradiría RNF-8.
- **Consecuencias**: ninguna, es la ausencia deliberada de un comando de purga.

## 4. Modelo de datos

### work_categories

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| name | string(60) | no | — | único; sembrada (branding, redes, papelería) |

- **Índices**: `UNIQUE(name)`.
- **Soft deletes**: no.

### services

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| name | string(150) | no | — | |
| name_normalized | string(150), generado | no | — | `LOWER(TRIM(name))` (D-3) |
| description | text | sí | null | |
| work_category_id | bigint unsigned | no | — | FK a `work_categories` |
| list_price | decimal(10,2) | no | — | precio de lista vigente |
| is_active | boolean | no | true | |
| created_at / updated_at | timestamp | — | — | |

- **Índices**: `UNIQUE(name_normalized)`; índice sobre `work_category_id`; índice sobre
  `is_active`.
- **Claves foráneas**: `work_category_id` → `work_categories.id`.
- **Soft deletes**: no (RF-9 se cumple con `is_active`, sin borrado ni archivado).

### service_price_histories

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| service_id | bigint unsigned | no | — | FK a `services` |
| old_price | decimal(10,2) | sí | null | null si es el primer precio |
| new_price | decimal(10,2) | no | — | |
| author_id | bigint unsigned | no | — | FK a `users` |
| created_at | timestamp | no | — | sin `updated_at`: solo alta |

- **Índices**: índice sobre `service_id`.
- **Soft deletes**: no aplica.

### budgets

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK; identificador correlativo (D-1) |
| client_id | bigint unsigned | no | — | FK a `clients` (spec `003`) |
| title | string(150) | no | — | |
| modality | string(10) | no | — | enum `BudgetModality`: `single`, `monthly` |
| issue_date | date | no | — | |
| validity_date | date | no | — | debe ser posterior a `issue_date` (RF-19) |
| status | string(10) | no | `draft` | enum `BudgetStatus`: `draft`, `sent`, `accepted`, `rejected` |
| response_date | date | sí | null | fecha de aceptación o rechazo |
| rejection_reason | string(255) | sí | null | |
| discount_type | string(10) | sí | null | enum `BudgetDiscountType`: `percentage`, `fixed` |
| discount_value | decimal(10,2) | sí | null | |
| subtotal | decimal(12,2) | no | 0 | desnormalizado (D-2) |
| discount_amount | decimal(12,2) | no | 0 | desnormalizado (D-2) |
| total | decimal(12,2) | no | 0 | desnormalizado (D-2) |
| created_by | bigint unsigned | no | — | FK a `users` |
| created_at / updated_at | timestamp | — | — | |
| deleted_at | timestamp | sí | null | "descartado" (SoftDeletes, D-4) |

- **Índices**: índice sobre `client_id`; índice sobre `status`; índice sobre `deleted_at`; índice
  sobre `created_at`; índice sobre `validity_date` (para señalar vencidos, RF-55).
- **Claves foráneas**: `client_id` → `clients.id`; `created_by` → `users.id`.
- **Enums**: `BudgetModality`, `BudgetStatus`, `BudgetDiscountType`.
- **Soft deletes**: sí.

### budget_items

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| budget_id | bigint unsigned | no | — | FK a `budgets` |
| service_id | bigint unsigned | no | — | FK a `services`; origen del ítem |
| name | string(150) | no | — | copiado al agregar (RF-22) |
| description | text | sí | null | copiado, editable (RF-27) |
| unit_price | decimal(10,2) | no | — | precio copiado (RF-22, RF-28) |
| quantity | integer unsigned | no | — | > 0 (RF-24) |
| created_at / updated_at | timestamp | — | — | |

- **Índices**: índice sobre `budget_id`; índice sobre `service_id`.
- **Claves foráneas**: `budget_id` → `budgets.id`; `service_id` → `services.id`.
- **Notas**: `amount` (importe, RF-25) es un accessor (`unit_price * quantity`), no una columna;
  a lo sumo 100 filas por presupuesto (RNF-4) hace innecesario desnormalizarlo.
- **Soft deletes**: no (ver D-5).

### budget_histories

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| budget_id | bigint unsigned | no | — | FK a `budgets` |
| field | string(20) | no | — | enum `BudgetHistoryField` |
| old_value | json | sí | null | |
| new_value | json | no | — | |
| author_id | bigint unsigned | no | — | FK a `users` |
| created_at | timestamp | no | — | sin `updated_at`: solo alta |

- **Índices**: índice compuesto `(budget_id, created_at)`.
- **Claves foráneas**: `budget_id` → `budgets.id`; `author_id` → `users.id`.
- **Enums**: `BudgetHistoryField` (`item_added`, `item_updated`, `item_removed`,
  `discount_changed`, `status_changed`, `header_changed`).
- **Soft deletes**: no aplica.

Migraciones a crear, en orden:

1. `create_work_categories_table`
2. `create_services_table`
3. `create_service_price_histories_table`
4. `create_budgets_table` (referencia `clients` de la spec `003` y `users` de la spec `001`)
5. `create_budget_items_table`
6. `create_budget_histories_table`

## 5. Estructura de módulos

- **Models**: `Service` (nuevo), `ServicePriceHistory` (nuevo), `WorkCategory` (nuevo), `Budget`
  (nuevo, `SoftDeletes`), `BudgetItem` (nuevo), `BudgetHistory` (nuevo).
- **Enums**: `BudgetModality`, `BudgetStatus`, `BudgetDiscountType`, `BudgetHistoryField`.
- **Actions**:
  - `CreateServiceAction` / `UpdateServiceAction` — alta y edición del catálogo; la segunda
    registra `ServicePriceHistory` si cambia `list_price`.
  - `ToggleServiceActiveAction` — desactivar/reactivar.
  - `CreateBudgetAction` — alta en borrador, valida cliente y fechas.
  - `UpdateBudgetHeaderAction` — título, modalidad, fecha de validez (borrador/enviado).
  - `AddBudgetItemAction` / `UpdateBudgetItemQuantityAction` /
    `UpdateBudgetItemDescriptionAction` / `RefreshBudgetItemPriceAction` /
    `RemoveBudgetItemAction` — todas recalculan totales y registran historial.
  - `SetBudgetDiscountAction` — carga/edita/quita el descuento; valida total ≥ 0.
  - `RecalculateBudgetTotalsAction` — helper interno, recalcula subtotal/descuento/total.
  - `SendBudgetAction` / `AcceptBudgetAction` / `RejectBudgetAction` /
    `RevertBudgetToSentAction` — transiciones de estado.
  - `DiscardBudgetAction` — soft delete de un presupuesto en borrador.
  - `GenerateBudgetPdfAction` — renderiza y devuelve el PDF; captura fallas de render (RF-67).
  - `BuildWhatsAppLinkAction` — arma la URL `wa.me` o `null` si no hay teléfono.
- **Policies**: `ServicePolicy`, `BudgetPolicy` (ambas: `admin` y `staff` igual, `cliente`
  rechazado siempre, RF-60).
- **Filament Resources / Pages / Widgets**:
  - Panel `staff`: `ServiceResource` (catálogo + relation manager de historial de precios);
    `BudgetResource` (list/create/edit/view, repeater de ítems, acciones de estado, descarga de
    PDF, botón de WhatsApp, relation manager de historial).
- **Rutas**: ninguna manual; todo dentro del panel `staff` ya definido por la spec `001`.
- **Traducciones**: `lang/es/budgets.php` con etiquetas, mensajes de rechazo (RF-3, RF-17, RF-31,
  RF-39, RF-44, RF-60) y textos del PDF.

## 6. Contratos de Actions y servicios

- **CreateServiceAction::handle(array $data): Service** — valida unicidad por `name_normalized`;
  crea el servicio activo; registra el primer `ServicePriceHistory` (`old_price = null`).
- **UpdateServiceAction::handle(Service $service, array $data): Service** — si `list_price`
  cambia, registra `ServicePriceHistory`; nunca toca `unit_price` de ítems existentes (RF-6).
- **ToggleServiceActiveAction::handle(Service $service): Service** — invierte `is_active`.
- **CreateBudgetAction::handle(array $data, User $author): Budget** — exige `client_id` y
  `validity_date > issue_date`; crea en `draft`; totales en cero.
- **AddBudgetItemAction::handle(Budget $budget, Service $service, int $quantity, ?string
  $description = null): BudgetItem** — rechaza si el presupuesto no está en `draft`/`sent`, si el
  servicio no está activo, o si ya tiene 100 ítems (RF-31); copia nombre/descripción/precio;
  llama a `RecalculateBudgetTotalsAction`; registra historial.
- **RemoveBudgetItemAction::handle(BudgetItem $item): void** — snapshot a `budget_histories`,
  `delete()` físico, recalcula totales.
- **SetBudgetDiscountAction::handle(Budget $budget, ?string $type, ?float $value): Budget** —
  rechaza si el total resultante es negativo (RF-39); recalcula; registra historial.
- **RecalculateBudgetTotalsAction::handle(Budget $budget): Budget** — suma `amount` de los ítems
  vigentes, aplica el descuento, redondea a 0,01 (RNF-2), persiste `subtotal`/`discount_amount`/
  `total`.
- **SendBudgetAction::handle(Budget $budget): Budget** — rechaza si no tiene ítems (RF-44).
- **AcceptBudgetAction::handle(Budget $budget): Budget** / **RejectBudgetAction::handle(Budget
  $budget, ?string $reason): Budget** — fijan `response_date`; la segunda acepta motivo vacío.
- **RevertBudgetToSentAction::handle(Budget $budget): Budget** — solo desde `accepted`/`rejected`.
- **DiscardBudgetAction::handle(Budget $budget): void** — solo desde `draft`; `delete()`.
- **GenerateBudgetPdfAction::handle(Budget $budget): string|BinaryFileResponse** — renderiza la
  vista del PDF con dompdf; en caso de excepción de render, la relanza tipada para que el llamador
  muestre el mensaje de error (RF-67) sin devolver ningún archivo.
- **BuildWhatsAppLinkAction::handle(Budget $budget): ?string** — `null` si
  `$budget->client->contactPhone()` es `null`; si no, la URL `wa.me` con el mensaje precargado.

## 7. Reglas de negocio y validaciones

- Nombre de servicio único ignorando mayúsculas y espacios, incluidos los desactivados (RF-3).
- Un cambio de precio de lista nunca altera el precio copiado de un ítem existente (RF-6).
- Un servicio desactivado no se ofrece para nuevos ítems, pero se conserva con su historial
  (RF-8, RF-9).
- Un presupuesto nace en `draft`, con identificador correlativo y autor (RF-12 a RF-14).
- La fecha de validez es obligatoria y posterior a la fecha de emisión (RF-18, RF-19).
- Un presupuesto sigue editable aunque su cliente pase a inactivo o archivado (RF-20).
- Los ítems solo se agregan, editan o quitan mientras el presupuesto está en `draft` o `sent`
  (RF-21, RF-26, RF-27, RF-28, RF-30); un mismo servicio puede repetirse en más de un ítem
  (RF-23); tope de 100 ítems (RF-31, RNF-4).
- El precio de un ítem nunca es distinto del precio de lista de su servicio al agregarlo o
  actualizarlo (RF-29).
- A lo sumo un descuento por presupuesto; si deja el total negativo, se rechaza (RF-37, RF-39).
- Toda alta, edición de ítem, cambio de descuento, cambio de estado y edición de cabecera queda
  registrada con valor anterior, valor nuevo, autor y fecha (RF-33, RF-40, RF-50).
- Transiciones de estado: `draft → sent` (exige al menos un ítem, RF-44), `sent → accepted` /
  `sent → rejected` (fija `response_date`), `accepted`/`rejected → sent` (reversión, RF-49);
  ningún otro salto está permitido.
- Un presupuesto `accepted` o `rejected` no admite editar ítems, descuento ni cabecera hasta
  volver a `sent` (RF-48).
- Solo se puede descartar un presupuesto en `draft` (RF-51); descartado nunca se elimina, solo
  deja de listarse (RF-52, RF-53).
- Un presupuesto `sent` con `validity_date` pasada se señala vencido sin cambiar de estado, y
  igual admite cerrarse como aceptado o rechazado (RF-55, RF-56).
- Ningún usuario con rol `cliente` puede ejecutar ninguna acción sobre presupuestos ni sobre el
  catálogo (RF-60).
- El PDF nunca incluye ítems quitados ni asientos de historial (RF-66); si su generación falla,
  se avisa y no se descarga nada (RF-67).
- La acción de WhatsApp no se ofrece si el cliente no tiene teléfono de contacto, y nunca adjunta
  el PDF (RF-69, RF-70).

## 8. Autorización

- `ServicePolicy` y `BudgetPolicy` autorizan por igual a `admin` y `staff` para todas las
  acciones de esta spec (catálogo y presupuestos); ninguna las distingue entre sí.
- Cualquier acción de un usuario con rol `cliente` sobre presupuestos o catálogo se rechaza en la
  policy correspondiente (RF-60); esta spec no expone ningún componente en el panel `client`.
- Ningún presupuesto ni su PDF quedan accesibles fuera de una sesión autenticada del panel
  `staff` (RF-71): no hay rutas públicas para esta spec.

## 9. Estrategia de tests

- **Unit**: unicidad de nombre de servicio ignorando mayúsculas/espacios; que un cambio de precio
  de lista no altere ítems existentes; cálculo de importe, subtotal, descuento y total, con
  redondeo (RNF-2); rechazo de descuento que deja el total negativo; todas las transiciones de
  estado válidas e inválidas; rechazo al superar 100 ítems; validación de `validity_date` posterior
  a `issue_date`; `BuildWhatsAppLinkAction` con y sin teléfono de contacto.
- **Feature**: alta de servicio y edición de precio con historial visible; armado completo de un
  presupuesto (alta, ítems, descuento, envío, aceptación); descarte de un borrador y su
  desaparición del listado; reversión de `accepted` a `sent` y reedición; descarga de PDF y su
  contenido; fallo simulado de generación de PDF; listado con filtros por cliente y por estado;
  rechazo de cualquier acción intentada por un usuario con rol `cliente`; acceso denegado sin
  sesión a la descarga de PDF.
- **Factories nuevas**: `ServiceFactory`, `BudgetFactory`, `BudgetItemFactory`,
  `ServicePriceHistoryFactory`, `BudgetHistoryFactory`.
- **Seeders**: `WorkCategorySeeder` (branding, redes, papelería — datos de referencia).

## 10. Mapa RF → componente

| RF | Descripción corta | Cubierto por |
|---|---|---|
| RF-1, RF-2, RF-4 | Alta y edición de servicio con categoría | `CreateServiceAction`, `UpdateServiceAction`, migración `create_services_table` |
| RF-3 | Unicidad de nombre, incluidos desactivados | `name_normalized` + índice único (D-3) |
| RF-5, RF-6 | Historial de precio, sin alterar ítems existentes | `service_price_histories`, `UpdateServiceAction` |
| RF-7, RF-8 | Desactivar/reactivar, no ofrecido si desactivado | `ToggleServiceActiveAction` |
| RF-9 | Conserva desactivados sin eliminarlos | Ausencia de borrado físico sobre `services` |
| RF-10 | Consulta de historial de precios | Relation manager en `ServiceResource` |
| RF-11 a RF-15 | Alta de presupuesto: cliente, borrador, correlativo, cabecera, modalidad | `CreateBudgetAction`, migración `create_budgets_table`, PK autoincremental (D-1) |
| RF-16 | Editar cabecera en borrador/enviado | `UpdateBudgetHeaderAction` |
| RF-17 | Rechazo sin cliente | `CreateBudgetAction` |
| RF-18, RF-19 | Fecha de validez obligatoria y posterior a emisión | `CreateBudgetAction`, `UpdateBudgetHeaderAction` |
| RF-20 | Editable aunque el cliente esté inactivo/archivado | Ausencia deliberada de validación cruzada con el estado del cliente |
| RF-21, RF-22 | Agregar ítems, copia de datos | `AddBudgetItemAction` |
| RF-23 | Repetir el mismo servicio | `AddBudgetItemAction` (sin restricción de unicidad) |
| RF-24, RF-25 | Cantidad válida, cálculo de importe | Validación en Action, accessor `BudgetItem::amount()` |
| RF-26 | Editar cantidad | `UpdateBudgetItemQuantityAction` |
| RF-27 | Editar descripción | `UpdateBudgetItemDescriptionAction` |
| RF-28, RF-29 | Actualizar precio copiado al vigente | `RefreshBudgetItemPriceAction` |
| RF-30, RF-31 | Quitar ítems, tope de 100 | `RemoveBudgetItemAction`, `AddBudgetItemAction` |
| RF-32 | Ítems quitados conservados en el historial | `budget_histories` (snapshot), D-5 |
| RF-33 | Registro de cada cambio de ítem | Escritura en `budget_histories` desde las Actions de ítems |
| RF-34 | Historial cronológico visible | Relation manager en `BudgetResource` |
| RF-35 a RF-39 | Subtotal, descuento, total, rechazo si negativo | `RecalculateBudgetTotalsAction`, `SetBudgetDiscountAction` |
| RF-40 | Registro de cambios de descuento | `SetBudgetDiscountAction` → `budget_histories` |
| RF-41 | Total como importe mensual | Vista de ficha y de PDF (accessor de presentación) |
| RF-42, RF-43, RF-44 | Estado único, borrador→enviado, rechazo sin ítems | Enum `BudgetStatus`, `SendBudgetAction` |
| RF-45, RF-46, RF-47 | Enviado→aceptado/rechazado, fecha de respuesta, motivo | `AcceptBudgetAction`, `RejectBudgetAction` |
| RF-48 | Bloqueo de edición en aceptado/rechazado | Validación de estado en Actions de ítems/cabecera/descuento |
| RF-49 | Revertir a enviado | `RevertBudgetToSentAction` |
| RF-50 | Registro de cambio de estado | `budget_histories` (`field = status_changed`) |
| RF-51, RF-52, RF-53 | Descartar, conservar, nunca eliminar | `DiscardBudgetAction`, SoftDeletes (D-4) |
| RF-54, RF-55, RF-56 | Vigencia mostrada, vencido señalado, cierre igual permitido | `BudgetResource` (columna e indicador de vigencia) |
| RF-57, RF-58, RF-59 | Listado con columnas, filtro por cliente y por estado | `BudgetResource` (tabla) |
| RF-60 | Rechazo total al rol cliente | `ServicePolicy`, `BudgetPolicy` |
| RF-61 a RF-66 | Descarga de PDF con su contenido, sin quitados ni historial | `GenerateBudgetPdfAction`, vista Blade del PDF (D-7) |
| RF-67 | Falla de generación de PDF | `GenerateBudgetPdfAction` (captura de excepción) |
| RF-68, RF-69, RF-70 | Acción de WhatsApp, sin teléfono no se ofrece, sin adjunto | `BuildWhatsAppLinkAction` (D-8) |
| RF-71 | Sin acceso público | Ausencia de ruta pública; todo dentro del panel `staff` autenticado |
| RNF-1 | 2 decimales | Columnas `decimal(_,2)` en `budgets`/`budget_items` |
| RNF-2 | Redondeo a 0,01 | `RecalculateBudgetTotalsAction` |
| RNF-3 | Descuento porcentual 0-100 | Validación en `SetBudgetDiscountAction` |
| RNF-4 | Hasta 100 ítems | `AddBudgetItemAction` (junto con RF-31) |
| RNF-5 | Recalcular <1s con 100 ítems | `RecalculateBudgetTotalsAction` (suma en memoria, sin N+1) |
| RNF-6 | PDF <5s con 100 ítems | `GenerateBudgetPdfAction` con dompdf (D-7) |
| RNF-7 | Listado <2s con 2.000 presupuestos | Totales desnormalizados + índices de `budgets` (D-2) |
| RNF-8 | Historiales sin purga | `budget_histories`/`service_price_histories` sin tarea de limpieza (D-9) |

## 11. Riesgos

- **`barryvdh/laravel-dompdf` es la única dependencia nueva de todo el proyecto hasta ahora**:
  mitigación — declararla y justificarla por escrito en el PR que la agregue, tal como pide el
  principio 1 y ya anticipa la spec.
- **Presupuestos que dependen de `clients` (spec 003) y `users` (spec 001)**: si alguno de esos
  dos planes cambia su esquema antes de implementarse, las migraciones de esta spec deben
  ajustarse. Mitigación: implementar en el orden de dependencia declarado (001 y 003 antes que
  002), no en el orden en que se redactaron estos planes.
- **Edición concurrente del mismo presupuesto**: la propia spec lo deja como supuesto (gana el
  último guardado); sin mitigación adicional en esta versión.

## 12. Fuera de alcance del plan

- No implementa restaurar un presupuesto descartado: la spec no lo pide, aunque el uso de
  SoftDeletes lo dejaría técnicamente posible más adelante.
- No implementa ningún componente del panel `client`: la spec rechaza toda acción de ese rol
  sobre presupuestos y catálogo (RF-60).
- No implementa envío automático por email ni por la API de WhatsApp Business, ni enlace público
  al presupuesto: la spec los descarta explícitamente (sección 8).
- No implementa ningún mecanismo de bloqueo optimista para edición concurrente (ver Riesgos).

## 13. Preguntas abiertas

Ninguna: todas las decisiones técnicas quedaron resueltas en la sección 3, con su alternativa
descartada.

## 14. Aprobación

- [x] Aprobado por Claudio el 2026-09-10
