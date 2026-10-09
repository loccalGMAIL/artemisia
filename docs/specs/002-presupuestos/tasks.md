# 002 — Tareas: Presupuestos

| | |
|---|---|
| **Spec** | docs/specs/002-presupuestos/spec.md |
| **Plan** | docs/specs/002-presupuestos/plan.md |
| **Estado** | En curso |
| **Fecha** | 2026-09-21 |

## Convenciones

- Checkbox al inicio de cada tarea. Se marca al terminarla, no antes.
- Orden por dependencias: cada tarea depende solo de las anteriores.
- TDD estricto: el test va antes que la implementación para reglas de negocio y flujos críticos.
- La suite queda verde después de cada tarea de `impl`.
- Cada tarea de `impl` que produce texto visible crea también sus claves en `lang/es/budgets.php`.
- Se asume implementado el orden canónico previo: `001-acceso-roles-y-paneles` y `003-clientes`.
- No hay tarea de bootstrap Laravel: es prerequisito fundacional fuera de esta spec.

## Resumen

- Total: 33 tareas.
- Por tipo: 14 test, 12 impl, 3 migration, 4 ui.
- Cubre: RF-1 a RF-71, RNF-1 a RNF-8.

---

## Fase 1: Esquema y base de dominio

### - [x] T1: Crear migraciones del catálogo de servicios

- **Tipo**: migration
- **Cubre**: RF-1, RF-2, RF-3, RF-5, RF-7, RF-9, RF-10, RNF-1, RNF-8
- **Depende de**: —
- **Hecho cuando**: `php artisan migrate:fresh --seed` crea `work_categories`, `services` y `service_price_histories` con columnas, índices, claves foráneas y datos de referencia del plan §4.

### - [x] T2: Crear migraciones de presupuestos

- **Tipo**: migration
- **Cubre**: RF-11, RF-13, RF-14, RF-15, RF-35, RF-36, RF-37, RF-42, RF-46, RF-47, RF-51, RF-52, RF-53, RNF-1, RNF-8
- **Depende de**: T1
- **Hecho cuando**: `budgets`, `budget_items` y `budget_histories` existen con `SoftDeletes` en `budgets`, totales desnormalizados, índices del plan §4 y referencias a `clients` y `users`.

### - [x] T3: Crear modelos, enums, factories y relaciones

- **Tipo**: impl
- **Cubre**: RF-2, RF-13, RF-15, RF-24, RF-25, RF-35, RF-42, RNF-1
- **Depende de**: T2
- **Hecho cuando**: existen los modelos, enums y factories definidos en el plan, `BudgetItem::amount` calcula cantidad por precio unitario, y un test de humo de relaciones pasa con la suite verde.

---

## Fase 2: Catálogo de servicios

### - [x] T4: Escribir test de alta de servicio con categoría, unicidad e historial inicial

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-3, RF-5, RF-9, RF-10
- **Depende de**: T3
- **Hecho cuando**: el test falla y verifica alta activa, categoría obligatoria, conflicto por nombre normalizado contra servicios activos o desactivados, y primer asiento de `service_price_histories`; commit del test hecho.

### - [x] T5: Implementar alta de servicio

- **Tipo**: impl
- **Cubre**: RF-1, RF-2, RF-3, RF-5, RF-9, RF-10
- **Depende de**: T4
- **Hecho cuando**: `CreateServiceAction` pasa el test de T4, usa mensajes de `lang/es/budgets.php` y la suite completa queda verde.

### - [x] T6: Escribir test de edición de servicio, cambio de precio y activación

- **Tipo**: test
- **Cubre**: RF-4, RF-5, RF-6, RF-7, RF-8, RF-9, RF-10
- **Depende de**: T5
- **Hecho cuando**: el test falla y cubre edición de datos, registro de cambio de precio, preservación de precios copiados en ítems existentes, desactivación/reactivación y exclusión de servicios inactivos para nuevos ítems; commit del test hecho.

### - [x] T7: Implementar edición y activación de servicios

- **Tipo**: impl
- **Cubre**: RF-4, RF-5, RF-6, RF-7, RF-8, RF-9, RF-10
- **Depende de**: T6
- **Hecho cuando**: `UpdateServiceAction` y `ToggleServiceActiveAction` pasan el test de T6 y la suite completa queda verde.

### - [x] T8: Escribir test de acceso al catálogo desde staff y rechazo al cliente

- **Tipo**: test
- **Cubre**: RF-1, RF-4, RF-7, RF-10, RF-60
- **Depende de**: T7
- **Hecho cuando**: el test falla y comprueba que `admin` y `staff` gestionan servicios e historial desde el panel `staff`, y que un usuario `client` recibe permiso insuficiente; commit del test hecho.

### - [x] T9: Crear `ServicePolicy` y `ServiceResource`

- **Tipo**: ui
- **Cubre**: RF-1, RF-4, RF-7, RF-10, RF-60
- **Depende de**: T8
- **Hecho cuando**: el test de T8 pasa, el resource muestra historial de precios, no ofrece servicios inactivos para selección de ítems y la suite completa queda verde.

---

## Fase 3: Alta y cabecera de presupuestos

### - [x] T10: Escribir test de creación de presupuesto

- **Tipo**: test
- **Cubre**: RF-11, RF-12, RF-13, RF-14, RF-15, RF-17, RF-18, RF-19, RF-20
- **Depende de**: T9
- **Hecho cuando**: el test falla y verifica cliente obligatorio, estado `draft`, identificador por `id`, modalidad única, autor, fecha de validez obligatoria y posterior, y creación aunque el cliente esté inactivo o archivado; commit del test hecho.

### - [x] T11: Implementar creación de presupuesto

- **Tipo**: impl
- **Cubre**: RF-11, RF-12, RF-13, RF-14, RF-15, RF-17, RF-18, RF-19, RF-20
- **Depende de**: T10
- **Hecho cuando**: `CreateBudgetAction` pasa el test de T10, usa mensajes traducidos y la suite completa queda verde.

### - [x] T12: Escribir test de edición de cabecera e historial

- **Tipo**: test
- **Cubre**: RF-16, RF-18, RF-19, RF-20, RF-33, RF-48
- **Depende de**: T11
- **Hecho cuando**: el test falla y cubre edición en `draft` y `sent`, rechazo en `accepted` o `rejected`, validación de fechas e historial `header_changed`; commit del test hecho.

### - [x] T13: Implementar edición de cabecera

- **Tipo**: impl
- **Cubre**: RF-16, RF-18, RF-19, RF-20, RF-33, RF-48
- **Depende de**: T12
- **Hecho cuando**: `UpdateBudgetHeaderAction` pasa el test de T12 y la suite completa queda verde.

---

## Fase 4: Ítems, totales y descuentos

### - [x] T14: Escribir test de agregado de ítems

- **Tipo**: test
- **Cubre**: RF-21, RF-22, RF-23, RF-24, RF-25, RF-29, RF-31, RF-33, RNF-4
- **Depende de**: T13
- **Hecho cuando**: el test falla y verifica agregado solo en `draft` o `sent`, copia de nombre/descripción/precio vigente, repetición del mismo servicio, cantidad entera mayor que cero, rechazo de servicios inactivos, tope de 100 e historial `item_added`; commit del test hecho.

### - [x] T15: Implementar agregado de ítems

- **Tipo**: impl
- **Cubre**: RF-21, RF-22, RF-23, RF-24, RF-25, RF-29, RF-31, RF-33, RNF-4
- **Depende de**: T14
- **Hecho cuando**: `AddBudgetItemAction` pasa el test de T14, recalcula totales y la suite completa queda verde.

### - [x] T16: Escribir test de modificación, actualización de precio y remoción de ítems

- **Tipo**: test
- **Cubre**: RF-26, RF-27, RF-28, RF-29, RF-30, RF-32, RF-33, RF-48
- **Depende de**: T15
- **Hecho cuando**: el test falla y cubre cambio de cantidad, cambio de descripción, refresh al precio vigente, remoción con snapshot en historial, rechazo en estados cerrados y ausencia de precio arbitrario; commit del test hecho.

### - [x] T17: Implementar modificación y remoción de ítems

- **Tipo**: impl
- **Cubre**: RF-26, RF-27, RF-28, RF-29, RF-30, RF-32, RF-33, RF-48
- **Depende de**: T16
- **Hecho cuando**: las actions de ítems pasan el test de T16, recalculan totales, registran historial y la suite completa queda verde.

### - [x] T18: Escribir test de totales, descuentos y redondeo

- **Tipo**: test
- **Cubre**: RF-35, RF-36, RF-37, RF-38, RF-39, RF-40, RF-41, RNF-1, RNF-2, RNF-3
- **Depende de**: T17
- **Hecho cuando**: el test falla y verifica subtotal, un solo descuento fijo o porcentual, porcentaje entre 0 y 100, total no negativo, redondeo a 0,01, historial `discount_changed` y etiqueta de importe mensual; commit del test hecho.

### - [x] T19: Implementar recálculo y descuentos

- **Tipo**: impl
- **Cubre**: RF-35, RF-36, RF-37, RF-38, RF-39, RF-40, RF-41, RNF-1, RNF-2, RNF-3
- **Depende de**: T18
- **Hecho cuando**: `RecalculateBudgetTotalsAction` y `SetBudgetDiscountAction` pasan el test de T18 y la suite completa queda verde.

---

## Fase 5: Estados y descarte

### - [x] T20: Escribir test de transiciones de estado

- **Tipo**: test
- **Cubre**: RF-42, RF-43, RF-44, RF-45, RF-46, RF-47, RF-48, RF-49, RF-50, RF-55, RF-56
- **Depende de**: T19
- **Hecho cuando**: el test falla y cubre `draft` → `sent`, rechazo de envío sin ítems, `sent` → `accepted/rejected`, fecha de respuesta, motivo opcional, reversión a `sent`, bloqueo de edición en estados cerrados, vencido sin cambio de estado y cierre de vencidos; commit del test hecho.

### - [x] T21: Implementar transiciones de estado

- **Tipo**: impl
- **Cubre**: RF-42, RF-43, RF-44, RF-45, RF-46, RF-47, RF-48, RF-49, RF-50, RF-55, RF-56
- **Depende de**: T20
- **Hecho cuando**: las actions de estado pasan el test de T20, registran `status_changed` y la suite completa queda verde.

### - [x] T22: Escribir test de descarte de presupuesto

- **Tipo**: test
- **Cubre**: RF-51, RF-52, RF-53, RNF-8
- **Depende de**: T21
- **Hecho cuando**: el test falla y comprueba que solo un presupuesto en `draft` se descarta con `SoftDeletes`, desaparece del listado normal y conserva historial sin borrar físicamente el presupuesto ni sus asientos; commit del test hecho.

### - [x] T23: Implementar descarte de presupuesto

- **Tipo**: impl
- **Cubre**: RF-51, RF-52, RF-53, RNF-8
- **Depende de**: T22
- **Hecho cuando**: `DiscardBudgetAction` pasa el test de T22 y la suite completa queda verde.

---

## Fase 6: Panel de presupuestos

### - [x] T24: Escribir test de listado, filtros, ficha e historial

- **Tipo**: test
- **Cubre**: RF-34, RF-54, RF-55, RF-57, RF-58, RF-59, RF-60
- **Depende de**: T23
- **Hecho cuando**: el test falla y verifica columnas del listado, filtros por cliente y estado, indicador de vencido, fecha de validez en ficha, historial cronológico visible y rechazo al rol `client`; commit del test hecho.

### - [x] T25: Crear `BudgetPolicy` y estructura base de `BudgetResource`

- **Tipo**: ui
- **Cubre**: RF-34, RF-54, RF-55, RF-57, RF-58, RF-59, RF-60
- **Depende de**: T24
- **Hecho cuando**: el test de T24 pasa, el resource lista solo presupuestos no descartados, muestra historial en orden cronológico y la suite completa queda verde.

### - [x] T26: Escribir test de flujo crítico de presupuesto en UI

- **Tipo**: test
- **Cubre**: RF-11, RF-16, RF-21, RF-26, RF-27, RF-28, RF-30, RF-36, RF-43, RF-45, RF-49, RF-51
- **Depende de**: T25
- **Hecho cuando**: el test falla y cubre desde Filament crear presupuesto, agregar ítems, editar cabecera, modificar ítems, cargar descuento, enviar, aceptar, revertir a enviado, reeditar y descartar un borrador; commit del test hecho.

### - [x] T27: Completar formularios y acciones de `BudgetResource`

- **Tipo**: ui
- **Cubre**: RF-11, RF-16, RF-21, RF-26, RF-27, RF-28, RF-30, RF-36, RF-43, RF-45, RF-49, RF-51
- **Depende de**: T26
- **Hecho cuando**: el test de T26 pasa, los formularios delegan en actions de dominio sin calcular totales en Filament y la suite completa queda verde.

---

## Fase 7: PDF y WhatsApp

### - [x] T28: Escribir test de generación y descarga de PDF

- **Tipo**: test
- **Cubre**: RF-54, RF-61, RF-62, RF-63, RF-64, RF-65, RF-66, RF-67, RF-71, RNF-6
- **Depende de**: T27
- **Hecho cuando**: el test falla y verifica descarga autenticada desde staff, contenido del PDF, total mensual cuando corresponde, exclusión de ítems quitados e historial, fallo simulado sin archivo, y rechazo sin sesión; commit del test hecho.

### - [x] T29: Implementar PDF con `barryvdh/laravel-dompdf`

- **Tipo**: impl
- **Cubre**: RF-54, RF-61, RF-62, RF-63, RF-64, RF-65, RF-66, RF-67, RF-71, RNF-6
- **Depende de**: T28
- **Hecho cuando**: la dependencia queda agregada y justificada en el PR, `GenerateBudgetPdfAction` y la vista Blade pasan el test de T28, no hay ruta pública y la suite completa queda verde.

### - [x] T30: Escribir test de enlace de WhatsApp

- **Tipo**: test
- **Cubre**: RF-68, RF-69, RF-70
- **Depende de**: T29
- **Hecho cuando**: el test falla y verifica URL `wa.me` con teléfono y mensaje precargado que referencia identificador y título, ausencia de acción si falta teléfono, y que no adjunta PDF; commit del test hecho.

### - [x] T31: Implementar acción de WhatsApp

- **Tipo**: ui
- **Cubre**: RF-68, RF-69, RF-70
- **Depende de**: T30
- **Hecho cuando**: `BuildWhatsAppLinkAction` y el botón de Filament pasan el test de T30 y la suite completa queda verde.

---

## Fase 8: Performance y cierre de calidad

### - [x] T32: Escribir tests de performance de totales, PDF y listado

- **Tipo**: test
- **Cubre**: RNF-5, RNF-6, RNF-7
- **Depende de**: T31
- **Hecho cuando**: el test falla si recalcular 100 ítems tarda 1 segundo o más, generar PDF de 100 ítems tarda 5 segundos o más, o listar 2.000 presupuestos tarda 2 segundos o más en entorno de test; commit del test hecho.

### - [x] T33: Ajustar consultas, índices y eager loading para performance

- **Tipo**: impl
- **Cubre**: RNF-5, RNF-6, RNF-7
- **Depende de**: T32
- **Hecho cuando**: el test de T32 pasa, el listado usa totales desnormalizados sin agregaciones por fila, no hay N+1 evidente y la suite completa queda verde.

### - [x] T34: Escribir tests de la sección de presupuestos en la ficha del cliente

- **Tipo**: test
- **Cubre**: RF-46 y RF-26, RF-30 de la spec 003
- **Depende de**: T27
- **Hecho cuando**: el test falla porque la ficha no lista presupuestos; el selector de cliente del presupuesto solo ofrece activos y no archivados; commit del test hecho.

### - [x] T35: Registrar la sección de presupuestos en ClientCardExtensions

- **Tipo**: impl
- **Cubre**: RF-46 (spec 003)
- **Depende de**: T34
- **Hecho cuando**: la ficha del cliente muestra identificador, título, total, estado y fecha de emisión de sus presupuestos, o un aviso si no tiene; la suite queda verde.

---

## Mapa RF/RNF → tareas

| RF/RNF | Tareas |
|---|---|
| RF-1 | T4, T5, T8, T9 |
| RF-2 | T1, T3, T4, T5 |
| RF-3 | T1, T4, T5 |
| RF-4 | T6, T7, T8, T9 |
| RF-5 | T1, T4, T5, T6, T7 |
| RF-6 | T6, T7 |
| RF-7 | T1, T6, T7, T8, T9 |
| RF-8 | T6, T7 |
| RF-9 | T1, T4, T5, T6, T7 |
| RF-10 | T1, T4, T5, T6, T7, T8, T9 |
| RF-11 | T2, T10, T11, T26, T27 |
| RF-12 | T10, T11 |
| RF-13 | T2, T3, T10, T11 |
| RF-14 | T2, T10, T11 |
| RF-15 | T2, T3, T10, T11 |
| RF-16 | T12, T13, T26, T27 |
| RF-17 | T10, T11 |
| RF-18 | T10, T11, T12, T13 |
| RF-19 | T10, T11, T12, T13 |
| RF-20 | T10, T11, T12, T13 |
| RF-21 | T14, T15, T26, T27 |
| RF-22 | T14, T15 |
| RF-23 | T14, T15 |
| RF-24 | T3, T14, T15 |
| RF-25 | T3, T14, T15 |
| RF-26 | T16, T17, T26, T27 |
| RF-27 | T16, T17, T26, T27 |
| RF-28 | T16, T17, T26, T27 |
| RF-29 | T14, T15, T16, T17 |
| RF-30 | T16, T17, T26, T27 |
| RF-31 | T14, T15 |
| RF-32 | T16, T17 |
| RF-33 | T12, T13, T14, T15, T16, T17 |
| RF-34 | T24, T25 |
| RF-35 | T2, T3, T18, T19 |
| RF-36 | T2, T18, T19, T26, T27 |
| RF-37 | T2, T18, T19 |
| RF-38 | T18, T19 |
| RF-39 | T18, T19 |
| RF-40 | T18, T19 |
| RF-41 | T18, T19 |
| RF-42 | T2, T3, T20, T21 |
| RF-43 | T20, T21, T26, T27 |
| RF-44 | T20, T21 |
| RF-45 | T20, T21, T26, T27 |
| RF-46 | T2, T20, T21 |
| RF-47 | T2, T20, T21 |
| RF-48 | T12, T13, T16, T17, T20, T21 |
| RF-49 | T20, T21, T26, T27 |
| RF-50 | T20, T21 |
| RF-51 | T2, T22, T23, T26, T27 |
| RF-52 | T2, T22, T23 |
| RF-53 | T2, T22, T23 |
| RF-54 | T24, T25, T28, T29 |
| RF-55 | T20, T21, T24, T25 |
| RF-56 | T20, T21 |
| RF-57 | T24, T25 |
| RF-58 | T24, T25 |
| RF-59 | T24, T25 |
| RF-60 | T8, T9, T24, T25 |
| RF-61 | T28, T29 |
| RF-62 | T28, T29 |
| RF-63 | T28, T29 |
| RF-64 | T28, T29 |
| RF-65 | T28, T29 |
| RF-66 | T28, T29 |
| RF-67 | T28, T29 |
| RF-68 | T30, T31 |
| RF-69 | T30, T31 |
| RF-70 | T30, T31 |
| RF-71 | T28, T29 |
| RNF-1 | T1, T2, T3, T18, T19 |
| RNF-2 | T18, T19 |
| RNF-3 | T18, T19 |
| RNF-4 | T14, T15 |
| RNF-5 | T32, T33 |
| RNF-6 | T28, T29, T32, T33 |
| RNF-7 | T32, T33 |
| RNF-8 | T1, T2, T22, T23 |

## Cambios en el plan detectados al descomponer

- **Contrato de `GenerateBudgetPdfAction` ambiguo**: el plan no define si la Action devuelve bytes, path temporal o response. Conviene fijarlo antes de implementar para que el test de T28 no acople UI con dominio.
- **Excepción de fallo de PDF no tipada**: el plan indica manejo de fallo, pero no nombra la excepción. Conviene definir una excepción propia, por ejemplo `BudgetPdfGenerationException`.
- **Dependencia PDF exige justificación en PR**: T29 lo incluye explícitamente, porque `barryvdh/laravel-dompdf` rompe el stack mínimo salvo justificación escrita.
- **API de teléfono depende de spec 003**: el plan asume `Client::contactPhone()`. Si la spec 003 implementó otro contrato, T30/T31 deben ajustarse sin cambiar el alcance funcional.
- **Performance en entorno de test puede variar**: RNF-5 a RNF-7 se cubren con tests de umbral, pero conviene definir si esos tests quedan marcados como grupo específico de performance para evitar falsos negativos en máquinas lentas.

### Resolución (implementación)

- **Contrato del PDF**: `GenerateBudgetPdfAction::handle(Budget): string` devuelve los bytes y `renderHtml(Budget)` expone el HTML para testear el contenido. El motor queda detrás de `App\Support\Pdf\PdfRenderer` (enlazado a `DompdfRenderer` en `AppServiceProvider`). La UI envuelve los bytes en `streamDownload`.
- **Excepción de fallo**: `BudgetPdfGenerationException`; ante ella no se descarga nada y se notifica el error (RF-67).
- **Dependencia PDF**: `barryvdh/laravel-dompdf ^3.1`, única dependencia nueva, justificada en el PR.
- **Teléfono**: se usó `Client::contactPhone()` de la spec 003, sin ajustes.
- **Performance**: tests en el grupo `performance` con carga masiva y mejor de tres corridas (`fastestOf`); se corren aparte con `--group=performance`.
- **Ítems por relation manager**, no por repeater: cada alta, edición y baja es una Action con su asiento de historial (RF-33).
- **Actions con `$actor`** (`handle(..., User $actor)`), como en las specs 001 y 003; cada Action de estado declara `allowedFrom()`.
- **Reglas decididas al implementar**: el descuento fijo se limita al subtotal al recalcular; revertir a enviado limpia `response_date` y `rejection_reason`; `CreateBudgetAction` acepta clientes inactivos o archivados y el selector de la UI ofrece solo `Client::availableForBudgets()`.
- **WhatsApp**: `https://wa.me/<dígitos>?text=...`, con prefijo `549` si el número es local y mínimo 8 dígitos.
