# 005 — Tareas: Contratos y pagos

| | |
|---|---|
| **Spec** | docs/specs/005-contratos-y-pagos/spec.md |
| **Plan** | docs/specs/005-contratos-y-pagos/plan.md |
| **Estado** | En curso |
| **Fecha** | 2026-10-10 |

## Convenciones

- Checkbox al inicio de cada tarea. Se marca al terminarla, no antes.
- Orden por dependencias: cada tarea depende solo de las anteriores.
- TDD estricto: el test va antes que la implementación en reglas de negocio y flujos críticos.
- La suite queda verde después de cada tarea de `impl` o `ui`.
- Cada tarea de `impl` o `ui` que produce mensajes visibles agrega también sus claves en `lang/es/contracts.php`.
- Todo «hoy», «mes actual» y «fecha futura» sale de `BusinessDate` (plan D-4), nunca de `today()`.
- Se asume el orden canónico ya cumplido: specs `001`, `003`, `002` y `004` implementadas y mergeadas.
- Un cambio de estado hecho por el sistema se asienta con autor nulo (plan D-7).
- Las tareas están agrupadas en **hitos visibles**: cada uno termina con algo que se puede mostrar al cliente en vivo. Al cerrar un hito se frena, se corre la suite, Pint y `composer analyse`, se hace push de la rama y se prepara la demo antes de seguir.

## Resumen

- Total: 55 tareas.
- Por tipo: 25 test, 17 impl, 3 migration, 8 ui, 1 refactor, 1 docs.
- Cubre: RF-1 a RF-98, RNF-1 a RNF-6.
- Hitos: 1 (T1 a T20), 2 (T21 a T32), 3 (T33 a T38) y 4 (T39 a T55).
- Prerrequisitos asumidos: roles y paneles de `001`, presupuestos aceptados de abono mensual de `002`, clientes, vínculo cuenta-cliente y los lugares reservados de la ficha de `003`.

---

## Hito 1: Contratos y períodos

**Demo posible al cerrarlo**: crear un contrato desde un presupuesto aceptado de abono mensual, ver su ficha con los períodos ya generados y el listado de contratos con sus filtros.

### - [x] T1: Crear migraciones de `contracts` y `contract_periods`

- **Tipo**: migration
- **Cubre**: RF-5, RF-7, RF-8, RF-9, RF-19, RF-34, RF-38, RNF-1, RNF-3
- **Depende de**: —
- **Hecho cuando**: `php artisan migrate:fresh` corre sin errores en una base limpia y crea ambas tablas con las columnas, el índice único de `budget_id`, el único `(contract_id, month)`, los índices y las claves foráneas del plan §4.

### - [x] T2: Crear migraciones de `period_concepts` y `payments`

- **Tipo**: migration
- **Cubre**: RF-42, RF-43, RF-58, RF-59, RF-60, RF-77, RF-80, RNF-1
- **Depende de**: T1
- **Hecho cuando**: `php artisan migrate:fresh` crea ambas tablas con sus columnas, `SoftDeletes` solo en `payments`, y los índices y claves foráneas del plan §4.

### - [x] T3: Crear migraciones de `contract_histories` y `period_histories`

- **Tipo**: migration
- **Cubre**: RF-33, RF-41, RF-52, RF-57, RF-79, RNF-6
- **Depende de**: T2
- **Hecho cuando**: `php artisan migrate:fresh` crea ambas tablas con `author_id` nullable, los índices compuestos `(entidad, created_at)` y sin `updated_at`.

### - [x] T4: Escribir test de `BusinessDate`

- **Tipo**: test
- **Cubre**: RF-36, RF-66, RF-74
- **Depende de**: —
- **Hecho cuando**: el test falla porque no existe `BusinessDate`, y comprueba `today()` y `currentMonth()` en hora argentina, incluido el borde de las 21:00 a las 24:00 en que UTC ya está en el día siguiente, y que respeta `travelTo`; commit del test hecho.

### - [x] T5: Implementar `BusinessDate` y la zona horaria de negocio

- **Tipo**: impl
- **Cubre**: RF-36, RF-66, RF-74
- **Depende de**: T4
- **Hecho cuando**: el test de T4 pasa, `config('app.business_timezone')` existe con su valor por defecto, la zona global de la aplicación sigue en UTC y la suite completa queda verde.

### - [x] T6: Crear enums, modelos, factories y relaciones del módulo

- **Tipo**: impl
- **Cubre**: RF-19, RF-34, RF-80, RNF-6
- **Depende de**: T3
- **Hecho cuando**: existen `ContractStatus`, `ConceptType`, `PaymentMethod`, `PaymentStatus`, `ContractHistoryField`, `PeriodHistoryField`, los modelos `Contract`, `ContractPeriod`, `PeriodConcept`, `Payment`, `ContractHistory` y `PeriodHistory` con sus relaciones y casts, `Budget::contract()`, `Client::contracts()` y `Client::payments()`, sus factories con estados, el borrado físico bloqueado y los historiales de solo alta, y un test de humo confirma que las relaciones cargan y que el borrado lanza excepción; la suite queda verde.

### - [x] T7: Escribir test de alta de contrato desde un presupuesto

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-6, RF-18, RF-20, RF-33
- **Depende de**: T6
- **Hecho cuando**: el test falla y comprueba el alta desde un presupuesto aceptado de abono mensual con cliente e importe copiados, estado vigente y asiento de creación, el rechazo con motivo si no está aceptado, es pago único o su total es cero, el rechazo que muestra el contrato existente, y que el contrato no cambia si el presupuesto vuelve a «enviado»; commit del test hecho.

### - [x] T8: Implementar `CreateContractAction`

- **Tipo**: impl
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-6, RF-18, RF-20, RF-33
- **Depende de**: T7
- **Hecho cuando**: el test de T7 pasa, existen `BudgetNotEligibleForContractException` y `BudgetAlreadyHasContractException`, no hay observer ni listener entre presupuestos y contratos, y la suite completa queda verde.

### - [x] T9: Escribir test de validación del alta y del plazo

- **Tipo**: test
- **Cubre**: RF-7, RF-8, RF-9, RF-11, RF-12
- **Depende de**: T8
- **Hecho cuando**: el test falla y cubre los datos obligatorios, el plazo entero mayor que cero o indefinido, el día de vencimiento de 1 a 28, la fecha de inicio pasada, actual o futura, el cálculo del último mes del plazo y el rechazo con motivo de un plazo fijo cuyo mes siguiente al último ya comenzó; commit del test hecho.

### - [x] T10: Implementar la validación del alta y `term_end_month`

- **Tipo**: impl
- **Cubre**: RF-7, RF-8, RF-9, RF-11, RF-12
- **Depende de**: T9
- **Hecho cuando**: el test de T9 pasa, existe `ContractTermElapsedException`, `term_end_month` se calcula al crear el contrato con `BusinessDate` y la suite completa queda verde.

### - [x] T11: Escribir test de precarga desde un contrato anterior

- **Tipo**: test
- **Cubre**: RF-13, RF-14, RF-15, RF-17
- **Depende de**: T10
- **Hecho cuando**: el test falla y comprueba que propone el día de vencimiento y el plazo del contrato de fecha de inicio más reciente de cualquier estado, el desempate por alta más reciente, la fecha de inicio propuesta solo si es de plazo fijo y que nunca devuelve el importe; commit del test hecho.

### - [x] T12: Implementar `ProposeContractDefaultsAction`

- **Tipo**: impl
- **Cubre**: RF-13, RF-14, RF-15, RF-17
- **Depende de**: T11
- **Hecho cuando**: el test de T11 pasa, la action no persiste nada y la suite completa queda verde.

### - [x] T13: Escribir test de generación de períodos

- **Tipo**: test
- **Cubre**: RF-22, RF-35, RF-36, RF-37, RF-38, RF-39, RF-40, RF-41
- **Depende de**: T10
- **Hecho cuando**: el test falla y cubre la reposición de los meses faltantes hasta el mes actual, que no genera con inicio futuro, a lo sumo un período por mes, el tope del plazo, la cuota y el vencimiento copiados aunque la fecha ya haya pasado, el asiento `generated` con autor nulo, que no genera en contratos cancelados y que sigue con un cliente inactivo o archivado; commit del test hecho.

### - [x] T14: Implementar `GenerateContractPeriodsAction` y generar al crear el contrato

- **Tipo**: impl
- **Cubre**: RF-22, RF-35, RF-36, RF-37, RF-38, RF-39, RF-40, RF-41
- **Depende de**: T13
- **Hecho cuando**: el test de T13 pasa, `CreateContractAction` genera los períodos que le correspondan, la inserción es por lotes y la suite completa queda verde.

### - [x] T15: Escribir test de las policies del módulo

- **Tipo**: test
- **Cubre**: RF-34, RF-80, RF-97, RF-98
- **Depende de**: T14
- **Hecho cuando**: el test falla y comprueba que `admin` y `staff` tienen los mismos permisos sobre contratos, períodos y pagos, que la cuenta `client` es rechazada con permiso insuficiente en cualquier acción de escritura, que nadie puede eliminar ni restaurar, y que `portal.contracts.view` permite solo los contratos del cliente vinculado y rechaza a otro cliente o a una cuenta sin vínculo; commit del test hecho.

### - [x] T16: Implementar `ContractPolicy`, `ContractPeriodPolicy`, `PaymentPolicy` y `ContractPortalPolicy`

- **Tipo**: impl
- **Cubre**: RF-34, RF-80, RF-97, RF-98
- **Depende de**: T15
- **Hecho cuando**: el test de T15 pasa, las policies y el gate están registrados en `AppServiceProvider` y la suite completa queda verde.

### - [x] T17: Escribir test del alta desde la ficha del presupuesto

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-13, RF-14, RF-15, RF-16
- **Depende de**: T12, T16
- **Hecho cuando**: el test falla y cubre que `admin` y `staff` abren «Crear contrato» desde un presupuesto, que el formulario viene precargado y es editable, que confirma y crea el contrato, y que sobre un presupuesto no elegible se muestra el motivo; commit del test hecho.

### - [x] T18: Crear la acción «Crear contrato» en la ficha del presupuesto

- **Tipo**: ui
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-13, RF-14, RF-15, RF-16
- **Depende de**: T17
- **Hecho cuando**: el test de T17 pasa, la acción solo orquesta `ProposeContractDefaultsAction` y `CreateContractAction` y la suite completa queda verde.

### - [x] T19: Escribir test del listado y la ficha de contratos

- **Tipo**: test
- **Cubre**: RF-81, RF-82, RF-83, RF-84, RF-86
- **Depende de**: T16
- **Hecho cuando**: el test falla y comprueba las columnas del listado, los filtros por cliente y por estado, la ficha con sus períodos y sus totales, el historial cronológico del contrato y que un contrato eliminado lógicamente no aparece; commit del test hecho.

### - [x] T20: Crear `ContractResource` con listado, ficha y relation managers

- **Tipo**: ui
- **Cubre**: RF-81, RF-82, RF-83, RF-84, RF-86
- **Depende de**: T19
- **Hecho cuando**: el test de T19 pasa, la tabla carga las relaciones de una vez y la suite completa queda verde.

---

## Hito 2: Conceptos, factura y estado del período

**Demo posible al cerrarlo**: listado de períodos con filtros, y en un período agregar extras y descuentos con justificación, ver el total, cargar la referencia de factura y quitar un concepto.

### - [x] T21: Escribir test de extras y descuentos

- **Tipo**: test
- **Cubre**: RF-42, RF-43, RF-44, RF-45, RF-46, RF-49, RF-50, RF-51, RF-52, RNF-2
- **Depende de**: T14
- **Hecho cuando**: el test falla y cubre agregar extras y descuentos, la justificación obligatoria del descuento, varios por período, el tope de 100 conceptos sin contar los quitados, el total como cuota más extras menos descuentos redondeado al centavo, el rechazo si el total quedara bajo cero o bajo lo ya pagado y el asiento de cada alta; commit del test hecho.

### - [x] T22: Implementar `AddPeriodExtraAction`, `AddPeriodDiscountAction` y `RecalculatePeriodTotalsAction`

- **Tipo**: impl
- **Cubre**: RF-42, RF-43, RF-44, RF-45, RF-46, RF-49, RF-50, RF-51, RF-52, RNF-2
- **Depende de**: T21
- **Hecho cuando**: el test de T21 pasa, existen `ChangesPeriod` (transacción con bloqueo, recálculo e historial), `PeriodLimitExceededException` y `PeriodTotalException`, los importes se manejan en centavos con `Money` y la suite completa queda verde.

### - [x] T23: Escribir test del estado de pago y del vencido

- **Tipo**: test
- **Cubre**: RF-69, RF-70, RF-71, RF-72, RF-73, RF-74
- **Depende de**: T22
- **Hecho cuando**: el test falla y cubre el saldo, pendiente sin pagos, parcial con pagos y saldo, pagado con saldo cero incluso con total cero y sin pagos, que anular un pago devuelve el estado anterior y la señal de vencido sin cambiar el estado de pago, con los scopes de los filtros; commit del test hecho.

### - [x] T24: Implementar `PaymentStatus` derivado y los scopes del período

- **Tipo**: impl
- **Cubre**: RF-69, RF-70, RF-71, RF-72, RF-73, RF-74
- **Depende de**: T23
- **Hecho cuando**: el test de T23 pasa, `ContractPeriod` expone el saldo, el estado de pago y los scopes de pendientes, parciales, pagados y vencidos sobre `total` y `paid_amount`, y la suite completa queda verde.

### - [x] T25: Escribir test de quitar un concepto

- **Tipo**: test
- **Cubre**: RF-47, RF-48, RF-51, RF-52
- **Depende de**: T22
- **Hecho cuando**: el test falla y cubre quitar un extra o un descuento, el rechazo si el total quedara bajo lo ya pagado, que la fila se borra y su foto queda en el historial, y el recálculo del total; commit del test hecho.

### - [x] T26: Implementar `RemovePeriodConceptAction`

- **Tipo**: impl
- **Cubre**: RF-47, RF-48, RF-51, RF-52
- **Depende de**: T25
- **Hecho cuando**: el test de T25 pasa y la suite completa queda verde.

### - [x] T27: Escribir test de la referencia de factura

- **Tipo**: test
- **Cubre**: RF-53, RF-54, RF-55, RF-56, RF-57
- **Depende de**: T22
- **Hecho cuando**: el test falla y cubre cargar y modificar número y fecha, el rechazo con motivo si falta uno de los dos, un período sin referencia, el mismo número en más de un período y el asiento con valor anterior y nuevo; commit del test hecho.

### - [x] T28: Implementar `SetPeriodInvoiceAction`

- **Tipo**: impl
- **Cubre**: RF-53, RF-54, RF-55, RF-56, RF-57
- **Depende de**: T27
- **Hecho cuando**: el test de T27 pasa y la suite completa queda verde.

### - [x] T29: Escribir test del listado y la ficha de períodos

- **Tipo**: test
- **Cubre**: RF-74, RF-85, RF-86, RF-87, RF-88, RF-89, RF-90
- **Depende de**: T16, T24
- **Hecho cuando**: el test falla y comprueba las columnas del listado, los filtros por cliente, estado de pago y vencidos, la señal de vencido, el detalle con cuota, extras, descuentos con su justificación, factura y pagos vigentes y anulados, y el historial cronológico del período; commit del test hecho.

### - [x] T30: Crear `ContractPeriodResource` con listado, ficha y relation managers

- **Tipo**: ui
- **Cubre**: RF-74, RF-85, RF-86, RF-87, RF-88, RF-89, RF-90
- **Depende de**: T29
- **Hecho cuando**: el test de T29 pasa, la tabla usa los scopes y los índices del plan y la suite completa queda verde.

### - [x] T31: Escribir test de las acciones de conceptos y factura sobre un período

- **Tipo**: test
- **Cubre**: RF-42, RF-43, RF-47, RF-53
- **Depende de**: T30, T26, T28
- **Hecho cuando**: el test falla y comprueba agregar un extra y un descuento con su justificación, quitar un concepto y cargar la referencia de factura desde la ficha del período, y que un rechazo de la action se muestra con su motivo; commit del test hecho.

### - [x] T32: Crear las acciones de conceptos y factura en la ficha del período

- **Tipo**: ui
- **Cubre**: RF-42, RF-43, RF-47, RF-53
- **Depende de**: T31
- **Hecho cuando**: el test de T31 pasa, las acciones delegan en las Actions de dominio y la suite completa queda verde.

---

## Hito 3: Pagos

**Demo posible al cerrarlo**: registrar un pago parcial, ver el período pasar a parcial y a pagado con su saldo, ver rechazado un pago que supera el saldo y anular un pago con justificación.

### - [x] T33: Escribir test de registro de pagos

- **Tipo**: test
- **Cubre**: RF-24, RF-35, RF-58, RF-59, RF-60, RF-61, RF-62, RF-63, RF-64, RF-65, RF-66, RF-67, RF-68, RF-69, RF-79, RNF-2
- **Depende de**: T22
- **Hecho cuando**: el test falla y cubre registrar un pago con fecha, importe y forma de pago, la referencia opcional, varios pagos por período, el importe mayor que cero y no mayor que el saldo con el saldo en el mensaje, la fecha futura rechazada y cualquier fecha pasada admitida, el tope de 100 pagos vigentes, el saldo que cambió entre la apertura y la confirmación, que se admiten pagos con el contrato cancelado, finalizado o con el cliente archivado y el asiento del pago; commit del test hecho.

### - [x] T34: Implementar `RegisterPaymentAction`

- **Tipo**: impl
- **Cubre**: RF-24, RF-35, RF-58, RF-59, RF-60, RF-61, RF-62, RF-63, RF-64, RF-65, RF-66, RF-67, RF-68, RF-69, RF-79, RNF-2
- **Depende de**: T33
- **Hecho cuando**: el test de T33 pasa, existe `PaymentExceedsBalanceException`, la action abre una transacción y bloquea el período antes de validar, y la suite completa queda verde.

### - [x] T35: Escribir test de anulación de pagos

- **Tipo**: test
- **Cubre**: RF-75, RF-76, RF-77, RF-78, RF-79, RF-80
- **Depende de**: T34
- **Hecho cuando**: el test falla y cubre anular solo un pago vigente con justificación obligatoria, que deja de computarse en lo pagado y se conserva con su justificación, su autor y su fecha, que ningún dato de un pago registrado se modifica, que no se elimina nunca y el asiento de la anulación; commit del test hecho.

### - [x] T36: Implementar `VoidPaymentAction`

- **Tipo**: impl
- **Cubre**: RF-75, RF-76, RF-77, RF-78, RF-79, RF-80
- **Depende de**: T35
- **Hecho cuando**: el test de T35 pasa, el modelo `Payment` rechaza cualquier modificación fuera de la anulación y la suite completa queda verde.

### - [x] T37: Escribir test de las acciones de pagos sobre un período

- **Tipo**: test
- **Cubre**: RF-58, RF-75
- **Depende de**: T32, T36
- **Hecho cuando**: el test falla y comprueba registrar un pago y anularlo con su justificación desde la ficha del período, ver el saldo y el estado de pago cambiar, y que un rechazo de la action se muestra con su motivo; commit del test hecho.

### - [x] T38: Crear las acciones y el relation manager de pagos en la ficha del período

- **Tipo**: ui
- **Cubre**: RF-58, RF-75
- **Depende de**: T37
- **Hecho cuando**: el test de T37 pasa, las acciones delegan en las Actions de dominio y la suite completa queda verde.

---

## Hito 4: Ciclo de vida, ficha del cliente y portal

**Demo posible al cerrarlo**: cancelar y reactivar un contrato, cambiar el importe hacia adelante, las secciones de la ficha del cliente y el estado de cuenta de solo lectura en el portal del cliente.

### - [x] T39: Escribir test del comando programado

- **Tipo**: test
- **Cubre**: RF-29, RF-36
- **Depende de**: T14
- **Hecho cuando**: el test falla y comprueba que `contracts:generate-periods` genera los períodos del mes, repone un mes que no corrió, finaliza los contratos de plazo fijo vencidos con autor nulo, no duplica al correr dos veces, sigue con los demás si un contrato falla y que está programado a diario en la hora de negocio; commit del test hecho.

### - [x] T40: Implementar el comando, `FinishExpiredContractsAction` y la programación

- **Tipo**: impl
- **Cubre**: RF-29, RF-36
- **Depende de**: T39
- **Hecho cuando**: el test de T39 pasa, la entrada está en `routes/console.php` junto a `access-logs:prune` y la suite completa queda verde.

### - [x] T41: Escribir test de cancelar y reactivar

- **Tipo**: test
- **Cubre**: RF-21, RF-22, RF-23, RF-25, RF-26, RF-27, RF-28, RF-33
- **Depende de**: T40
- **Hecho cuando**: el test falla y cubre cancelar solo desde vigente, que los períodos existentes se conservan, reactivar y reponer los períodos faltantes sin pasar del plazo, el rechazo con motivo si el plazo fijo ya transcurrió, el rechazo de un contrato finalizado y el asiento de cada cambio de estado; commit del test hecho.

### - [x] T42: Implementar `CancelContractAction` y `ReactivateContractAction`

- **Tipo**: impl
- **Cubre**: RF-21, RF-22, RF-23, RF-25, RF-26, RF-27, RF-28, RF-33
- **Depende de**: T41
- **Hecho cuando**: el test de T41 pasa, existen `ChangesContractStatus` e `InvalidContractTransitionException` y la suite completa queda verde.

### - [x] T43: Escribir test de cambio de importe y de día de vencimiento

- **Tipo**: test
- **Cubre**: RF-10, RF-30, RF-31, RF-32, RF-33
- **Depende de**: T14
- **Hecho cuando**: el test falla y cubre que solo se modifica un contrato vigente, el importe mayor que cero con a lo sumo 2 decimales, el día de 1 a 28, que el cambio rige desde los períodos que se generen después sin alterar los existentes ni el del mes en curso, y el asiento con valor anterior y nuevo; commit del test hecho.

### - [x] T44: Implementar `UpdateContractAmountAction` y `UpdateContractDueDayAction`

- **Tipo**: impl
- **Cubre**: RF-10, RF-30, RF-31, RF-32, RF-33
- **Depende de**: T43
- **Hecho cuando**: el test de T43 pasa y la suite completa queda verde.

### - [x] T45: Escribir test de las acciones sobre un contrato

- **Tipo**: test
- **Cubre**: RF-21, RF-25, RF-30, RF-31
- **Depende de**: T20, T42, T44
- **Hecho cuando**: el test falla y comprueba cancelar, reactivar, modificar el importe y modificar el día de vencimiento desde la ficha, que cada acción se ofrece solo en el estado que corresponde y que un rechazo de la action se muestra con su motivo; commit del test hecho.

### - [x] T46: Crear las acciones de la ficha del contrato

- **Tipo**: ui
- **Cubre**: RF-21, RF-25, RF-30, RF-31
- **Depende de**: T45
- **Hecho cuando**: el test de T45 pasa, las acciones delegan en las Actions de dominio y la suite completa queda verde.

### - [x] T47: Escribir test de las secciones de la ficha del cliente

- **Tipo**: test
- **Cubre**: RF-91, RF-92
- **Depende de**: T16
- **Hecho cuando**: el test falla y comprueba que la ficha del cliente muestra sus contratos y su historial de pagos con vigentes y anulados, y que no muestra los de otro cliente; se revisan los tests de `ClientCardExtensions` que asumen qué secciones hay registradas; commit del test hecho.

### - [ ] T48: Crear `ClientContractsSection` y `ClientPaymentsSection`

- **Tipo**: ui
- **Cubre**: RF-91, RF-92
- **Depende de**: T47
- **Hecho cuando**: el test de T47 pasa, las dos secciones se registran en `ClientCardExtensions` desde `AppServiceProvider` y la suite completa queda verde.

### - [ ] T49: Escribir test del estado de cuenta del cliente

- **Tipo**: test
- **Cubre**: RF-93, RF-94, RF-95, RF-96, RF-97
- **Depende de**: T16
- **Hecho cuando**: el test falla y comprueba que `BuildClientStatementAction` devuelve solo los contratos del cliente de la cuenta, con períodos, extras, descuentos con su justificación, total, pagado, saldo, estado de pago y vencimiento, solo pagos vigentes, nada de otros clientes y nada para una cuenta sin vínculo; commit del test hecho.

### - [ ] T50: Implementar `BuildClientStatementAction`

- **Tipo**: impl
- **Cubre**: RF-93, RF-94, RF-95, RF-96, RF-97
- **Depende de**: T49
- **Hecho cuando**: el test de T49 pasa, la action aplica `portal.contracts.view` a cada contrato y carga todo de una vez, y la suite completa queda verde.

### - [ ] T51: Escribir test de `ContractsPage`

- **Tipo**: test
- **Cubre**: RF-93, RF-94, RF-95, RF-96, RF-98
- **Depende de**: T50
- **Hecho cuando**: el test falla y comprueba que la cuenta vinculada ve sus contratos, períodos y pagos en el portal, no ve los de otro cliente ni los pagos anulados, que la página no ofrece ninguna acción de escritura y que una cuenta sin vínculo ve el aviso correspondiente; commit del test hecho.

### - [ ] T52: Crear `ContractsPage` del portal de clientes

- **Tipo**: ui
- **Cubre**: RF-93, RF-94, RF-95, RF-96, RF-98
- **Depende de**: T51
- **Hecho cuando**: el test de T51 pasa, la página usa secciones de estado perezoso y la suite completa queda verde.

### - [ ] T53: Escribir tests de rendimiento

- **Tipo**: test
- **Cubre**: RNF-3, RNF-4, RNF-5
- **Depende de**: T30, T48, T52
- **Hecho cuando**: los tests del grupo `performance` verifican con el mejor de tres corridas el listado de 5.000 períodos con filtros en menos de 2 s, la generación del mes para 500 contratos con 12 períodos cada uno en menos de 10 s y el portal de un cliente con 60 períodos y 200 pagos en menos de 2 s, y que no hay consultas por fila; commit del test hecho.

### - [ ] T54: Optimizar consultas y validar textos

- **Tipo**: refactor
- **Cubre**: RF-91, RNF-1, RNF-3, RNF-4, RNF-5
- **Depende de**: T53
- **Hecho cuando**: los tests de T53 pasan, todos los mensajes visibles están en `lang/es/contracts.php`, un test comprueba que cada importe del módulo se expresa con 2 decimales, y `php artisan test` y `./vendor/bin/pint` corren sin errores.

### - [ ] T55: Documentar la programación en `AGENTS.md` y sincronizar plan y tareas

- **Tipo**: docs
- **Cubre**: RNF-6
- **Depende de**: T54
- **Hecho cuando**: `AGENTS.md` §3 explica que producción necesita el ciclo de `schedule:run` para `contracts:generate-periods` y que `business_timezone` es la zona de negocio, `plan.md` y `tasks.md` reflejan lo implementado, y `composer analyse` queda sin errores.

---

## Mapa RF/RNF → tareas

Al terminar, cada RF y RNF de la spec tiene que estar cubierto por al menos una tarea.

| RF/RNF | Tareas |
|---|---|
| RF-1 | T7, T8, T17, T18 |
| RF-2 | T7, T8, T17, T18 |
| RF-3 | T7, T8, T17, T18 |
| RF-4 | T7, T8, T17, T18 |
| RF-5 | T1, T7, T8, T17, T18 |
| RF-6 | T7, T8 |
| RF-7 | T1, T9, T10 |
| RF-8 | T1, T9, T10 |
| RF-9 | T1, T9, T10 |
| RF-10 | T43, T44 |
| RF-11 | T9, T10 |
| RF-12 | T9, T10 |
| RF-13 | T11, T12, T17, T18 |
| RF-14 | T11, T12, T17, T18 |
| RF-15 | T11, T12, T17, T18 |
| RF-16 | T17, T18 |
| RF-17 | T11, T12 |
| RF-18 | T7, T8 |
| RF-19 | T1, T6 |
| RF-20 | T7, T8 |
| RF-21 | T41, T42, T45, T46 |
| RF-22 | T13, T14, T41, T42 |
| RF-23 | T41, T42 |
| RF-24 | T33, T34 |
| RF-25 | T41, T42, T45, T46 |
| RF-26 | T41, T42 |
| RF-27 | T41, T42 |
| RF-28 | T41, T42 |
| RF-29 | T39, T40 |
| RF-30 | T43, T44, T45, T46 |
| RF-31 | T43, T44, T45, T46 |
| RF-32 | T43, T44 |
| RF-33 | T3, T7, T8, T41, T42, T43, T44 |
| RF-34 | T1, T6, T15, T16 |
| RF-35 | T13, T14, T33, T34 |
| RF-36 | T4, T5, T13, T14, T39, T40 |
| RF-37 | T13, T14 |
| RF-38 | T1, T13, T14 |
| RF-39 | T13, T14 |
| RF-40 | T13, T14 |
| RF-41 | T3, T13, T14 |
| RF-42 | T2, T21, T22, T31, T32 |
| RF-43 | T2, T21, T22, T31, T32 |
| RF-44 | T21, T22 |
| RF-45 | T21, T22 |
| RF-46 | T21, T22 |
| RF-47 | T25, T26, T31, T32 |
| RF-48 | T25, T26 |
| RF-49 | T21, T22 |
| RF-50 | T21, T22 |
| RF-51 | T21, T22, T25, T26 |
| RF-52 | T3, T21, T22, T25, T26 |
| RF-53 | T27, T28, T31, T32 |
| RF-54 | T27, T28 |
| RF-55 | T27, T28 |
| RF-56 | T27, T28 |
| RF-57 | T3, T27, T28 |
| RF-58 | T2, T33, T34, T37, T38 |
| RF-59 | T2, T33, T34 |
| RF-60 | T2, T33, T34 |
| RF-61 | T33, T34 |
| RF-62 | T33, T34 |
| RF-63 | T33, T34 |
| RF-64 | T33, T34 |
| RF-65 | T33, T34 |
| RF-66 | T4, T5, T33, T34 |
| RF-67 | T33, T34 |
| RF-68 | T33, T34 |
| RF-69 | T23, T24, T33, T34 |
| RF-70 | T23, T24 |
| RF-71 | T23, T24 |
| RF-72 | T23, T24 |
| RF-73 | T23, T24 |
| RF-74 | T4, T5, T23, T24, T29, T30 |
| RF-75 | T35, T36, T37, T38 |
| RF-76 | T35, T36 |
| RF-77 | T2, T35, T36 |
| RF-78 | T35, T36 |
| RF-79 | T3, T33, T34, T35, T36 |
| RF-80 | T2, T6, T15, T16, T35, T36 |
| RF-81 | T19, T20 |
| RF-82 | T19, T20 |
| RF-83 | T19, T20 |
| RF-84 | T19, T20 |
| RF-85 | T29, T30 |
| RF-86 | T19, T20, T29, T30 |
| RF-87 | T29, T30 |
| RF-88 | T29, T30 |
| RF-89 | T29, T30 |
| RF-90 | T29, T30 |
| RF-91 | T47, T48, T54 |
| RF-92 | T47, T48 |
| RF-93 | T49, T50, T51, T52 |
| RF-94 | T49, T50, T51, T52 |
| RF-95 | T49, T50, T51, T52 |
| RF-96 | T49, T50, T51, T52 |
| RF-97 | T15, T16, T49, T50 |
| RF-98 | T15, T16, T51, T52 |
| RNF-1 | T1, T2, T54 |
| RNF-2 | T21, T22, T33, T34 |
| RNF-3 | T1, T53, T54 |
| RNF-4 | T53, T54 |
| RNF-5 | T53, T54 |
| RNF-6 | T3, T6, T55 |

## Cambios en el plan detectados al descomponer

Cosas que se hicieron visibles al bajar a tareas y que idealmente vuelven al plan antes de implementar (no se resuelven acá).

- **El módulo supera las ~40 tareas de referencia (55).** La spec tiene 98 RF y la regla de la skill pide partir una spec que se va mucho de las 40. Se mantuvo una sola spec porque se eligió Contratos y Pagos juntos; si se prefiere partirla, el corte natural es entre los hitos 2 y 3: contratos, períodos, conceptos y factura (T1 a T32) y una spec posterior de pagos.
- **Reordenado por hitos visibles.** Las policies (T15 y T16) pasaron al hito 1 porque solo necesitan los modelos, y las pantallas se intercalan con la lógica para que cada hito sea mostrable. Las pruebas y pantallas de las acciones sobre un período se partieron en conceptos y factura (T31 y T32) y pagos (T37 y T38).
- **El estado de pago derivado (T23 y T24) va después de los extras (T21 y T22)** porque necesita `RecalculatePeriodTotalsAction`, que nace con ellos.
- **El orden de la generación y las transiciones.** `ReactivateContractAction` y `CreateContractAction` llaman a `GenerateContractPeriodsAction`, así que la generación (T13 y T14) va antes de cancelar y reactivar (T41 y T42); el plan §6 las lista en otro orden y no lo advierte.
- **El plan no fija el nombre de las secciones ni de las claves de `lang/es/contracts.php`.** Se asume la misma estructura que `pieces.php` (`statuses`, `fields`, `actions`, `notifications`, `validation`, `history_fields`).
- **El plan no dice qué muestra la ficha de un contrato eliminado lógicamente.** No hay forma funcional de eliminarlo, así que T19 solo comprueba que no aparece en el listado.
- **La hora de ejecución (00:10) del comando programado es una decisión del plan (D-9)** que la spec no fija; conviene confirmarla antes de implementar T40.
- **Tests existentes de la ficha del cliente.** `ClientCardExtensionsTest` y `ClientBudgetsSectionTest` asumen qué secciones hay registradas; T47 los revisa, pero el plan solo lo menciona como riesgo.
