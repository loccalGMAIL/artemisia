# 002 — Tareas: Presupuestos

| | |
|---|---|
| **Spec** | docs/specs/002-presupuestos/spec.md |
| **Plan** | docs/specs/002-presupuestos/plan.md |
| **Estado** | En curso |
| **Fecha** | 2026-09-10 |

## Convenciones

- Checkbox al inicio de cada tarea. Se marca al terminarla, no antes.
- Orden por dependencias: cada tarea depende solo de las anteriores.
- TDD estricto: el test va antes que la implementación.
- La suite queda verde después de cada tarea de `impl`.
- Cada tarea de `impl` que produce un mensaje visible crea también sus claves en
  `lang/es/budgets.php` (constitución, principio 6). No hay una tarea separada de traducciones.
- **Precondiciones**: requiere `001-acceso-roles-y-paneles` (roles y paneles) y
  `003-clientes` (tabla `clients` con `displayName()` y `contactPhone()`) implementadas.
- **Orden de implementación entre specs**: 003 → 002 → 004. Esta spec va después de la 003 porque
  todo presupuesto se dirige a un cliente existente.
- **T39 es la única tarea de esta spec que no cubre ningún RF propio**: instala y justifica la
  dependencia de generación de PDF (plan D-7), habilitación previa a las tareas de PDF.
- **T45 cubre RF-46 de la spec `003-clientes`**, no un RF de esta spec: es el punto de extensión
  que esa spec dejó pendiente porque el módulo de Presupuestos no existía todavía.
- **Total de 45 tareas supera el techo de ~40 de la skill**: se acepta por el mismo motivo que en
  la spec `003` (ver su `tasks.md`).

## Resumen

- Total: 45 tareas.
- Por tipo: 15 test, 18 impl, 6 migration, 6 ui.
- Cubre: RF-1 a RF-71, RNF-1 a RNF-8, y RF-46 de la spec `003-clientes`.

---

## Fase 1: Esquema

### - [ ] T1: Crear migración y seeder de `work_categories`

- **Tipo**: migration
- **Cubre**: RF-2
- **Depende de**: —
- **Hecho cuando**: `php artisan migrate:fresh --seed` crea `work_categories` con `id` y `name`
  único, y `WorkCategorySeeder` deja cargadas branding, redes y papelería.

### - [ ] T2: Crear migración de `services`

- **Tipo**: migration
- **Cubre**: RF-1, RF-2, RF-3
- **Depende de**: T1
- **Hecho cuando**: la tabla existe con `name`, la columna generada `name_normalized`
  (`LOWER(TRIM(name))`) con índice `UNIQUE`, `description`, `work_category_id`, `list_price`,
  `is_active` (default `true`), según el plan §4 (D-3).

### - [ ] T3: Crear migración de `service_price_histories`

- **Tipo**: migration
- **Cubre**: RF-5
- **Depende de**: T2
- **Hecho cuando**: la tabla existe con `service_id`, `old_price` nullable, `new_price`,
  `author_id`, `created_at`, sin columna `updated_at`.

### - [ ] T4: Crear migración de `budgets`

- **Tipo**: migration
- **Cubre**: RF-12, RF-13, RF-14, RF-15, RF-42
- **Depende de**: T2
- **Hecho cuando**: la tabla existe con `client_id`, `title`, `modality`, `issue_date`,
  `validity_date`, `status` (default `draft`), `response_date`, `rejection_reason`,
  `discount_type`, `discount_value`, `subtotal`/`discount_amount`/`total` desnormalizados
  (default 0), `created_by`, `deleted_at`, según el plan §4 (D-1, D-2, D-4).

### - [ ] T5: Crear migración de `budget_items`

- **Tipo**: migration
- **Cubre**: RF-22, RF-24
- **Depende de**: T4
- **Hecho cuando**: la tabla existe con `budget_id`, `service_id`, `name`, `description`,
  `unit_price`, `quantity` (unsigned), sin `deleted_at` (plan D-5).

### - [ ] T6: Crear migración de `budget_histories`

- **Tipo**: migration
- **Cubre**: RF-33, RF-40, RF-50
- **Depende de**: T4
- **Hecho cuando**: la tabla existe con `budget_id`, `field`, `old_value`, `new_value`,
  `author_id`, `created_at`, índice `(budget_id, created_at)`, sin columna `updated_at`.

### - [ ] T7: Crear enums, modelos, accessors y factories

- **Tipo**: impl
- **Cubre**: RF-15, RF-25, RF-42
- **Depende de**: T3, T5, T6
- **Hecho cuando**: existen `BudgetModality`, `BudgetStatus`, `BudgetDiscountType`,
  `BudgetHistoryField` como backed enums; `Service`, `ServicePriceHistory`, `WorkCategory`,
  `Budget` (`SoftDeletes`), `BudgetItem` (con accessor `amount()` = `unit_price * quantity`) y
  `BudgetHistory` resuelven sus relaciones en un test de humo verde; `ServiceFactory`,
  `BudgetFactory`, `BudgetItemFactory`, `ServicePriceHistoryFactory` y `BudgetHistoryFactory`
  existen y producen registros válidos.

---

## Fase 2: Catálogo de servicios

### - [ ] T8: Escribir test de alta de servicio con conflicto de nombre

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-3
- **Depende de**: T7
- **Hecho cuando**: `pest --filter=CreateService` falla porque `CreateServiceAction` no existe;
  cubre alta con categoría, y rechazo de un nombre repetido (ignorando mayúsculas y espacios,
  incluso contra un servicio desactivado) mostrando el conflicto; commit del test hecho.

### - [ ] T9: Implementar `CreateServiceAction`

- **Tipo**: impl
- **Cubre**: RF-1, RF-2, RF-3
- **Depende de**: T8
- **Hecho cuando**: el test de T8 pasa y la suite completa queda verde.

### - [ ] T10: Escribir test de edición de servicio con historial de precio

- **Tipo**: test
- **Cubre**: RF-4, RF-5, RF-6
- **Depende de**: T9
- **Hecho cuando**: el test falla y comprueba que editar nombre/descripción/categoría/precio
  registra `ServicePriceHistory` solo cuando cambia el precio, y que ese cambio nunca altera el
  `unit_price` de ítems ya copiados en presupuestos existentes; commit del test hecho.

### - [ ] T11: Implementar `UpdateServiceAction`

- **Tipo**: impl
- **Cubre**: RF-4, RF-5, RF-6
- **Depende de**: T10
- **Hecho cuando**: el test de T10 pasa y la suite completa queda verde.

### - [ ] T12: Escribir test de desactivar/reactivar servicio

- **Tipo**: test
- **Cubre**: RF-7, RF-8, RF-9
- **Depende de**: T11
- **Hecho cuando**: el test falla y comprueba que un servicio desactivado no aparece entre los
  activos para agregar a un presupuesto, que reactivar lo devuelve, y que ambas operaciones
  conservan su historial de precios sin eliminarlo; commit del test hecho.

### - [ ] T13: Implementar `ToggleServiceActiveAction`

- **Tipo**: impl
- **Cubre**: RF-7, RF-8, RF-9
- **Depende de**: T12
- **Hecho cuando**: el test de T12 pasa y la suite completa queda verde.

### - [ ] T14: Crear `ServiceResource` con relation manager de historial de precios

- **Tipo**: ui
- **Cubre**: RF-1, RF-4, RF-7, RF-10
- **Depende de**: T13
- **Hecho cuando**: un `admin` o `staff` da de alta, edita y desactiva/reactiva servicios desde el
  panel de staff, y consulta el historial de precios de cada uno en orden cronológico.

---

## Fase 3: Alta del presupuesto

### - [ ] T15: Escribir test de alta de presupuesto con validación de cabecera

- **Tipo**: test
- **Cubre**: RF-11, RF-12, RF-13, RF-14, RF-17, RF-18, RF-19
- **Depende de**: T14
- **Hecho cuando**: el test falla y cubre: alta dirigida a un cliente existente que nace en
  `draft` con identificador correlativo, cabecera completa y autor; rechazo sin cliente
  destinatario; exigencia de fecha de validez; y rechazo cuando la validez no es posterior a la
  emisión; commit del test hecho.

### - [ ] T16: Implementar `CreateBudgetAction`

- **Tipo**: impl
- **Cubre**: RF-11, RF-12, RF-13, RF-14, RF-17, RF-18, RF-19
- **Depende de**: T15
- **Hecho cuando**: el test de T15 pasa y la suite completa queda verde.

### - [ ] T17: Escribir test de edición de cabecera y editabilidad con cliente inactivo/archivado

- **Tipo**: test
- **Cubre**: RF-16, RF-20
- **Depende de**: T16
- **Hecho cuando**: el test falla y comprueba que la cabecera se edita mientras el presupuesto
  está en `draft` o `sent`, y que sigue editable y se puede enviar aunque su cliente destinatario
  haya pasado a inactivo o archivado; commit del test hecho.

### - [ ] T18: Implementar `UpdateBudgetHeaderAction`

- **Tipo**: impl
- **Cubre**: RF-16, RF-20
- **Depende de**: T17
- **Hecho cuando**: el test de T17 pasa y la suite completa queda verde.

---

## Fase 4: Ítems y totales

### - [ ] T19: Escribir test de agregar ítems con copia congelada y tope

- **Tipo**: test
- **Cubre**: RF-21, RF-22, RF-23, RF-24, RF-31, RNF-4
- **Depende de**: T18
- **Hecho cuando**: el test falla y cubre: agregar un ítem copia nombre/descripción/precio
  vigentes; solo se agregan servicios activos; el mismo servicio puede repetirse en ítems
  separados; cantidad exige entero mayor que cero; y el ítem 101 se rechaza con motivo; commit
  del test hecho.

### - [ ] T20: Implementar `AddBudgetItemAction`

- **Tipo**: impl
- **Cubre**: RF-21, RF-22, RF-23, RF-24, RF-31, RNF-4
- **Depende de**: T19
- **Hecho cuando**: el test de T19 pasa y la suite completa queda verde.

### - [ ] T21: Escribir test de recálculo de subtotal con redondeo

- **Tipo**: test
- **Cubre**: RF-25, RF-35, RNF-1, RNF-2, RNF-5
- **Depende de**: T20
- **Hecho cuando**: el test falla y comprueba que el importe de cada ítem es `unit_price *
  quantity`, que el subtotal es la suma de los importes, que todo se expresa con 2 decimales
  redondeados al centavo más cercano, y que el recálculo con 100 ítems corre en menos de 1
  segundo; commit del test hecho.

### - [ ] T22: Implementar `RecalculateBudgetTotalsAction`

- **Tipo**: impl
- **Cubre**: RF-25, RF-35, RNF-1, RNF-2, RNF-5
- **Depende de**: T21
- **Hecho cuando**: el test de T21 pasa y la suite completa queda verde.

### - [ ] T23: Escribir test de edición y quita de ítems con historial

- **Tipo**: test
- **Cubre**: RF-26, RF-27, RF-28, RF-29, RF-30, RF-32, RF-33, RF-34
- **Depende de**: T22
- **Hecho cuando**: el test falla y cubre: modificar cantidad y descripción de un ítem, actualizar
  su precio copiado al precio de lista vigente sin admitir ningún otro valor, quitar un ítem
  preservando su snapshot en el historial, y consulta cronológica del historial; commit del test
  hecho.

### - [ ] T24: Implementar las Actions de edición y quita de ítems

- **Tipo**: impl
- **Cubre**: RF-26, RF-27, RF-28, RF-29, RF-30, RF-32, RF-33, RF-34
- **Depende de**: T23
- **Hecho cuando**: `UpdateBudgetItemQuantityAction`, `UpdateBudgetItemDescriptionAction`,
  `RefreshBudgetItemPriceAction` y `RemoveBudgetItemAction` hacen pasar el test de T23 y la suite
  completa queda verde.

### - [ ] T25: Escribir test de descuento con rechazo de total negativo

- **Tipo**: test
- **Cubre**: RF-36, RF-37, RF-38, RF-39, RF-40, RNF-3
- **Depende de**: T24
- **Hecho cuando**: el test falla y cubre: cargar un descuento porcentual (0-100) o de monto fijo,
  a lo sumo uno por presupuesto, cálculo del total como subtotal menos descuento, rechazo cuando
  el descuento deja el total negativo, y asiento de historial en cada cambio; commit del test
  hecho.

### - [ ] T26: Implementar `SetBudgetDiscountAction`

- **Tipo**: impl
- **Cubre**: RF-36, RF-37, RF-38, RF-39, RF-40, RNF-3
- **Depende de**: T25
- **Hecho cuando**: el test de T25 pasa y la suite completa queda verde.

---

## Fase 5: Estados

### - [ ] T27: Escribir test de envío con exigencia de ítems

- **Tipo**: test
- **Cubre**: RF-43, RF-44, RF-50
- **Depende de**: T26
- **Hecho cuando**: el test falla y comprueba que un presupuesto en `draft` con ítems se puede
  marcar `sent`, que uno sin ítems se rechaza con motivo, y el asiento de historial de estado;
  commit del test hecho.

### - [ ] T28: Implementar `SendBudgetAction`

- **Tipo**: impl
- **Cubre**: RF-43, RF-44, RF-50
- **Depende de**: T27
- **Hecho cuando**: el test de T27 pasa y la suite completa queda verde.

### - [ ] T29: Escribir test de aceptación y rechazo con motivo opcional

- **Tipo**: test
- **Cubre**: RF-45, RF-46, RF-47, RF-48, RF-50
- **Depende de**: T28
- **Hecho cuando**: el test falla y cubre: marcar `sent → accepted` o `sent → rejected` con fecha
  de respuesta, motivo de rechazo opcional (vacío admitido), y que un presupuesto `accepted` o
  `rejected` rechaza cualquier edición de ítems, descuento o cabecera; commit del test hecho.

### - [ ] T30: Implementar `AcceptBudgetAction` y `RejectBudgetAction`

- **Tipo**: impl
- **Cubre**: RF-45, RF-46, RF-47, RF-48, RF-50
- **Depende de**: T29
- **Hecho cuando**: el test de T29 pasa y la suite completa queda verde.

### - [ ] T31: Escribir test de reversión a enviado y de descarte

- **Tipo**: test
- **Cubre**: RF-49, RF-50, RF-51, RF-52, RF-53
- **Depende de**: T30
- **Hecho cuando**: el test falla y cubre: revertir un presupuesto `accepted`/`rejected` a `sent`
  con asiento de historial, descartar un presupuesto en `draft` (queda oculto del listado pero
  conservado con su historial), y que ningún presupuesto ni asiento se elimina físicamente;
  commit del test hecho.

### - [ ] T32: Implementar `RevertBudgetToSentAction` y `DiscardBudgetAction`

- **Tipo**: impl
- **Cubre**: RF-49, RF-50, RF-51, RF-52, RF-53
- **Depende de**: T31
- **Hecho cuando**: el test de T31 pasa y la suite completa queda verde.

---

## Fase 6: Vigencia, listado y autorización

### - [ ] T33: Escribir test de `ServicePolicy` y `BudgetPolicy` con rechazo al rol cliente

- **Tipo**: test
- **Cubre**: RF-60, RF-71
- **Depende de**: T32
- **Hecho cuando**: el test falla y comprueba que `admin` y `staff` tienen los mismos permisos
  sobre catálogo y presupuestos, que cualquier acción de un usuario con rol `cliente` se rechaza
  con mensaje de permiso insuficiente, y que no hay ninguna ruta pública para presupuestos ni PDF;
  commit del test hecho.

### - [ ] T34: Implementar `ServicePolicy` y `BudgetPolicy`

- **Tipo**: impl
- **Cubre**: RF-60, RF-71
- **Depende de**: T33
- **Hecho cuando**: el test de T33 pasa y la suite completa queda verde.

### - [ ] T35: Escribir test de listado con filtros y señal de vencido

- **Tipo**: test
- **Cubre**: RF-54, RF-55, RF-56, RF-57, RF-58, RF-59, RNF-7
- **Depende de**: T34
- **Hecho cuando**: el test falla y cubre: columnas del listado, filtro por cliente y por estado,
  señal de vencido cuando la validez de un `sent` ya pasó (sin cambiar su estado), que igual se
  puede aceptar o rechazar vencido, y que el listado con 2.000 presupuestos responde en menos de
  2 segundos; commit del test hecho.

### - [ ] T36: Implementar la tabla de `BudgetResource` con filtros e indicador de vigencia

- **Tipo**: ui
- **Cubre**: RF-54, RF-55, RF-56, RF-57, RF-58, RF-59, RNF-7
- **Depende de**: T35
- **Hecho cuando**: el test de T35 pasa, la tabla del recurso Filament refleja todos los casos y
  la suite completa queda verde.

### - [ ] T37: Crear la ficha del presupuesto con repeater de ítems, acciones de estado e historial

- **Tipo**: ui
- **Cubre**: RF-16, RF-21, RF-26, RF-27, RF-28, RF-30, RF-34, RF-36, RF-41
- **Depende de**: T36
- **Hecho cuando**: desde la ficha un `admin` o `staff` edita cabecera, agrega/edita/quita ítems,
  carga el descuento, ejecuta las transiciones de estado y consulta el historial cronológico,
  todo delegando en las Actions de las fases 3 a 5.

---

## Fase 7: PDF y envío

### - [ ] T38: Instalar y justificar `barryvdh/laravel-dompdf`

- **Tipo**: impl
- **Cubre**: — (habilitación de RF-61 a RF-67; plan D-7)
- **Depende de**: T37
- **Hecho cuando**: `composer show barryvdh/laravel-dompdf` reporta la dependencia instalada y el
  PR que la agrega documenta por escrito la justificación del plan §3 D-7 (constitución, principio
  1).

### - [ ] T39: Escribir test de generación de PDF con su contenido

- **Tipo**: test
- **Cubre**: RF-61, RF-62, RF-63, RF-64, RF-65, RF-66
- **Depende de**: T38
- **Hecho cuando**: el test falla y comprueba que el PDF de cualquier estado incluye identificador,
  datos del cliente, título, fechas, modalidad, cada ítem con su importe, subtotal/descuento/total,
  el rótulo de importe mensual cuando corresponde, y que nunca incluye ítems quitados ni asientos
  de historial; commit del test hecho.

### - [ ] T40: Implementar la vista Blade del PDF y `GenerateBudgetPdfAction`

- **Tipo**: impl
- **Cubre**: RF-61, RF-62, RF-63, RF-64, RF-65, RF-66
- **Depende de**: T39
- **Hecho cuando**: el test de T39 pasa y la suite completa queda verde.

### - [ ] T41: Escribir test de falla en la generación del PDF

- **Tipo**: test
- **Cubre**: RF-67, RNF-6
- **Depende de**: T40
- **Hecho cuando**: el test falla y comprueba que una falla de render muestra un mensaje de error
  sin descargar ningún archivo, y que la generación de un PDF de 100 ítems corre en menos de 5
  segundos; commit del test hecho.

### - [ ] T42: Implementar el manejo de falla de `GenerateBudgetPdfAction`

- **Tipo**: impl
- **Cubre**: RF-67, RNF-6
- **Depende de**: T41
- **Hecho cuando**: el test de T41 pasa y la suite completa queda verde.

### - [ ] T43: Escribir test del enlace de WhatsApp con y sin teléfono de contacto

- **Tipo**: test
- **Cubre**: RF-68, RF-69, RF-70
- **Depende de**: T42
- **Hecho cuando**: el test falla y comprueba que la acción abre un enlace `wa.me` con mensaje
  precargado referenciando identificador y título, que nunca adjunta el PDF, y que si el cliente
  no tiene teléfono de contacto la acción no se ofrece e indica el motivo; commit del test hecho.

### - [ ] T44: Implementar `BuildWhatsAppLinkAction` y el botón en la ficha

- **Tipo**: ui
- **Cubre**: RF-68, RF-69, RF-70
- **Depende de**: T43
- **Hecho cuando**: el test de T43 pasa, la ficha del presupuesto ofrece descarga de PDF y el
  botón de WhatsApp, y la suite completa queda verde.

---

## Fase 8: Ficha del cliente

### - [ ] T45: Agregar sección de presupuestos a `ClientResource`

- **Tipo**: ui
- **Cubre**: RF-46 (spec `003-clientes`)
- **Depende de**: T44
- **Hecho cuando**: la ficha del cliente en `ClientResource` (spec `003`) muestra sus
  presupuestos con identificador, título, total, estado y fecha de emisión.

---

## Mapa RF → tareas

| RF | Tareas |
|---|---|
| RF-1 | T2, T8, T9, T14 |
| RF-2 | T1, T2, T8, T9 |
| RF-3 | T2, T8, T9 |
| RF-4 | T10, T11, T14 |
| RF-5 | T3, T10, T11 |
| RF-6 | T10, T11 |
| RF-7 | T12, T13, T14 |
| RF-8 | T12, T13 |
| RF-9 | T12, T13 |
| RF-10 | T14 |
| RF-11 | T15, T16 |
| RF-12 | T4, T15, T16 |
| RF-13 | T4, T15, T16 |
| RF-14 | T4, T15, T16 |
| RF-15 | T4, T7 |
| RF-16 | T17, T18, T37 |
| RF-17 | T15, T16 |
| RF-18 | T15, T16 |
| RF-19 | T15, T16 |
| RF-20 | T17, T18 |
| RF-21 | T5, T19, T20, T37 |
| RF-22 | T5, T19, T20 |
| RF-23 | T19, T20 |
| RF-24 | T5, T19, T20 |
| RF-25 | T7, T21, T22 |
| RF-26 | T23, T24, T37 |
| RF-27 | T23, T24, T37 |
| RF-28 | T23, T24, T37 |
| RF-29 | T23, T24 |
| RF-30 | T23, T24, T37 |
| RF-31 | T19, T20 |
| RF-32 | T23, T24 |
| RF-33 | T6, T23, T24 |
| RF-34 | T23, T24, T37 |
| RF-35 | T21, T22 |
| RF-36 | T25, T26, T37 |
| RF-37 | T25, T26 |
| RF-38 | T25, T26 |
| RF-39 | T25, T26 |
| RF-40 | T25, T26 |
| RF-41 | T37 |
| RF-42 | T4, T7 |
| RF-43 | T27, T28 |
| RF-44 | T27, T28 |
| RF-45 | T29, T30 |
| RF-46 | T29, T30 |
| RF-47 | T29, T30 |
| RF-48 | T29, T30 |
| RF-49 | T31, T32 |
| RF-50 | T6, T27, T28, T29, T30, T31, T32 |
| RF-51 | T31, T32 |
| RF-52 | T31, T32 |
| RF-53 | T31, T32 |
| RF-54 | T35, T36 |
| RF-55 | T35, T36 |
| RF-56 | T35, T36 |
| RF-57 | T35, T36 |
| RF-58 | T35, T36 |
| RF-59 | T35, T36 |
| RF-60 | T33, T34 |
| RF-61 | T39, T40 |
| RF-62 | T39, T40 |
| RF-63 | T39, T40 |
| RF-64 | T39, T40 |
| RF-65 | T39, T40 |
| RF-66 | T39, T40 |
| RF-67 | T41, T42 |
| RF-68 | T43, T44 |
| RF-69 | T43, T44 |
| RF-70 | T43, T44 |
| RF-71 | T33, T34 |
| RNF-1 | T21, T22 |
| RNF-2 | T21, T22 |
| RNF-3 | T25, T26 |
| RNF-4 | T19, T20 |
| RNF-5 | T21, T22 |
| RNF-6 | T41, T42 |
| RNF-7 | T35, T36 |
| RNF-8 | *sin test automatizado — ver "Cambios en el plan detectados"* |

## Cambios en el plan detectados al descomponer

- **T38 no cubre ningún RF propio**: instalar `barryvdh/laravel-dompdf` es habilitación pura,
  igual que la T1 de la spec `001`. Se mantiene como tarea propia porque el principio 1 de la
  constitución exige justificar por escrito cada dependencia nueva antes de usarla.
- **T45 cubre un RF de otra spec (RF-46 de `003-clientes`)**, no uno de esta spec. Queda asentado
  en el mapa de ambos `tasks.md` para que el validador de la 003 lo encuentre sin tener que
  buscarlo fuera de su propio archivo.
- **RF-68 y RF-69 dependen de `Client::contactPhone()`**, implementado en la fase 4 del `tasks.md`
  de `003-clientes` (T23/T24). Es una precondición dura: si esa spec cambia el nombre o la firma
  del accessor, T43/T44 se ajustan antes de implementarse.
- **RF-13 pide un identificador "correlativo único que no se reutiliza"**, y el plan lo resuelve
  con el PK autoincremental de `budgets` (D-1). Un `migrate:fresh` en desarrollo reinicia el
  contador; no rompe el requisito en producción, pero el plan no aclara qué pasa en los entornos
  de test que corren `migrate:fresh` entre corridas. Ninguna tarea lo valida porque no es un
  comportamiento observable por un test de Pest.
- **RNF-8 (historial y precios sin purga) no tiene tarea de test dedicada**, igual que RNF-7 de la
  spec `003`: es la ausencia deliberada de un comando de limpieza sobre `budget_histories` y
  `service_price_histories`, sin nada que un test automatizado pueda verificar salvo que esa
  ausencia se mantenga.
- **`lang/es/budgets.php` no tiene una tarea propia**: sus claves se reparten entre T9, T11, T13,
  T16, T18, T20, T22, T24, T26, T28, T30, T32, T34, T36, T40, T42, T44 y toda otra tarea de `impl`
  que produzca un mensaje visible, siguiendo el mismo patrón que las specs `001` y `003`.
