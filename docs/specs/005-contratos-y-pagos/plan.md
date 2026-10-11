# 005 — Plan técnico: Contratos y pagos

| | |
|---|---|
| **Spec** | docs/specs/005-contratos-y-pagos/spec.md (versión aprobada) |
| **Estado** | Aprobado |
| **Fecha** | 2026-10-10 |
| **Autor** | Claudio |

## 1. Resumen del enfoque

`Contract` nace de un presupuesto de abono mensual aceptado y copia el cliente y el importe mensual.
Cada mes se materializa un `ContractPeriod` (la cuota a cobrar), del que cuelgan los conceptos
(extras y descuentos), los pagos y la referencia de factura. El total y lo pagado de cada período se
guardan y se recalculan en cada cambio dentro de una transacción con bloqueo; el saldo y el estado de
pago se derivan. Un comando programado diario genera los períodos que falten y finaliza los contratos
cuyo plazo terminó. Todo cálculo de «hoy» y «mes actual» pasa por un helper de zona horaria de
negocio (Argentina) sin tocar la zona global de la aplicación. El staff opera desde dos recursos de
Filament y la ficha del cliente; el cliente consulta desde una página de solo lectura.

## 2. Chequeo contra la constitución

- **Principio 1 (Stack mínimo)**: sin dependencias nuevas. Los importes usan `App\Support\Money`, la programación usa el `Schedule` de Laravel (como `access-logs:prune`) y la interfaz sigue siendo Filament.
- **Principio 2 (La spec manda)**: el plan cubre exactamente los RF y RNF de `docs/specs/005-contratos-y-pagos/spec.md`; el mapa de la sección 10 los lista todos. Lo que la spec dejó libre se decide en la sección 3.
- **Principio 3 (Lógica fuera de la interfaz)**: generación de períodos, totales, saldos, topes, validación de pagos, transiciones de estado y precarga viven en Actions y en los modelos. Los Resources, Pages y relation managers solo orquestan y presentan.
- **Principio 4 (TDD estricto)**: ver sección 9; el test se escribe primero y su commit precede al de la implementación.
- **Principio 5 (Persistencia explícita)**: todo esquema por migración versionada. `contracts` y `payments` llevan `SoftDeletes` (el principio los nombra) con el borrado físico bloqueado en el modelo; los períodos, conceptos y pagos nunca se borran por la interfaz, salvo el concepto quitado, que queda como asiento del historial (D-5). Cada cambio de estado se registra con autor y fecha; el autor es nulo cuando lo produce el sistema (D-7).
- **Principio 6 (Idioma)**: clases, tablas, columnas y rutas en inglés; toda cadena visible sale de `lang/es/contracts.php`.

## 3. Decisiones técnicas

### D-1: El contrato es una entidad propia con copia de cliente e importe

- **Decisión**: `contracts` guarda `client_id` y `monthly_amount` copiados del presupuesto, y `budget_id` único.
- **Motivo**: el contrato tiene su propio ciclo de vida (estado, plazo, cambios de importe hacia adelante) y no debe moverse si el presupuesto vuelve a «enviado» o se modifica (RF-18).
- **Alternativa descartada**: usar el presupuesto aceptado como contrato, agregándole estado y plazo. Mezcla dos ciclos de vida y haría que un cambio del presupuesto alterara lo que se cobra.
- **Consecuencias**: ningún observer ni listener sincroniza contratos desde presupuestos, igual que en la spec 004 (D-7).

### D-2: Los períodos se materializan, no se calculan al vuelo

- **Decisión**: cada mes del contrato es una fila de `contract_periods`, con `fee` y `due_date` copiados al generarla y único por `(contract_id, month)`.
- **Motivo**: de cada período cuelgan conceptos, pagos y factura, y la cuota se congela aunque el contrato cambie (RF-32, RF-40). La unicidad hace idempotente la generación (RF-38).
- **Alternativa descartada**: derivar los períodos del contrato en cada consulta. No hay dónde colgar los conceptos y los pagos, y el importe vigente se perdería.
- **Consecuencias**: hace falta un generador de períodos faltantes (D-9) y los listados leen filas reales, lo que permite índices (RNF-3).

### D-3: Total y pagado se guardan; saldo y estado de pago se derivan

- **Decisión**: `contract_periods.total` y `paid_amount` son columnas que `RecalculatePeriodTotalsAction` recalcula en cada cambio de conceptos o de pagos. El saldo (`total - paid_amount`) y el estado de pago (pendiente / parcial / pagado) se calculan en el modelo y en scopes sobre esas dos columnas.
- **Motivo**: el listado de 5.000 períodos filtra y ordena por estado y por vencidos (RNF-3); sumar conceptos y pagos fila por fila no escala. El estado derivado cumple RF-70 («calculará») y no genera historial propio.
- **Alternativa descartada**: sumar conceptos y pagos en cada consulta. Es correcto pero lento en el listado. Tampoco se guarda el estado de pago, porque quedaría desfasado de los pagos.
- **Consecuencias**: toda escritura sobre conceptos o pagos termina llamando a `RecalculatePeriodTotalsAction` en la misma transacción.

### D-4: Zona horaria de negocio con un helper, sin cambiar la zona global

- **Decisión**: se agrega `config('app.business_timezone')` (por defecto `America/Argentina/Buenos_Aires`) y `App\Support\BusinessDate` con `today()` y `currentMonth()`. Todo «hoy», «mes actual», vencimiento y «fecha futura» de este módulo sale de ahí. La zona de la aplicación sigue en UTC.
- **Motivo**: el supuesto de la spec exige hora argentina, pero cambiar la zona global reinterpretaría los timestamps ya guardados y las fechas de los módulos 002 y 004, que usan `today()`.
- **Alternativa descartada**: cambiar `APP_TIMEZONE` a la hora argentina. Es una línea, pero desplaza 3 horas todos los timestamps existentes y puede romper fechas de presupuestos y piezas ya acordadas.
- **Consecuencias**: las fechas de negocio se guardan como `date` (sin hora) calculadas con el helper; los timestamps (`created_at`, `voided_at`) siguen en UTC. Los tests viajan en el tiempo con `travelTo`, que el helper respeta.

### D-5: El concepto quitado se borra y queda como asiento del historial

- **Decisión**: `RemovePeriodConceptAction` elimina la fila de `period_concepts` y escribe en `period_histories` un asiento `concept_removed` con la foto del concepto.
- **Motivo**: es el patrón ya aprobado en la spec 002 (D-5) para los ítems quitados; deja el conteo del tope (RF-46) simple y cumple RF-48 («dentro del historial»).
- **Alternativa descartada**: baja lógica con `removed_at`. Obliga a filtrar los quitados en cada suma y en el tope, y duplica lo que el historial ya conserva.
- **Consecuencias**: el id del concepto quitado no se reutiliza; el historial es la única fuente de los quitados.

### D-6: La anulación de un pago son columnas del propio pago

- **Decisión**: `payments` lleva `voided_at`, `voided_by` y `void_justification`. «Vigente» es `voided_at` nulo. `SoftDeletes` se declara por el principio 5, pero la baja funcional es la anulación; `forceDelete` queda bloqueado en el modelo.
- **Motivo**: el pago anulado debe seguir consultable con su justificación, su autor y su fecha (RF-77) y no debe computarse (RF-68).
- **Alternativa descartada**: una tabla `payment_voids` aparte. Agrega una unión sin aportar nada, porque cada pago se anula como mucho una vez.
- **Consecuencias**: un pago anulado no se reactiva (RF-78); el scope `valid()` lo excluye de las sumas.

### D-7: Autor nulo significa «el sistema»

- **Decisión**: `author_id` es nullable en `contract_histories` y `period_histories`; nulo se muestra como «Sistema». Las Actions que puede disparar el sistema reciben `?User $actor`.
- **Motivo**: la generación de períodos y la finalización de contratos no tienen usuario (RF-33, RF-41) y el principio 5 pide autor en cada cambio de estado.
- **Alternativa descartada**: sembrar un usuario «sistema». Es una cuenta falsa que aparece en los listados de cuentas y exige un rol.
- **Consecuencias**: la UI de historial resuelve nulo a la etiqueta `contracts.system`.

### D-8: Concurrencia con bloqueo pesimista sobre el período

- **Decisión**: `RegisterPaymentAction`, `VoidPaymentAction` y las Actions de conceptos abren una transacción, leen la fila del período con `lockForUpdate()` y validan contra los valores recién leídos. `GenerateContractPeriodsAction` bloquea la fila del contrato.
- **Motivo**: RF-65 pide validar el importe contra el saldo vigente al confirmar, y RF-51 y RF-62 contra lo pagado y los topes; el bloqueo lo garantiza aunque dos usuarios operen a la vez.
- **Alternativa descartada**: bloqueo optimista con una columna de versión. Obligaría a pasar la versión por la interfaz y a reintentar, y la spec no lo necesita.
- **Consecuencias**: SQLite (los tests) ignora el bloqueo; la regla se prueba simulando un saldo que cambió entre la apertura y la confirmación.

### D-9: Generación diaria, idempotente y con reposición

- **Decisión**: el comando `contracts:generate-periods` corre todos los días a las 00:10 de la hora de negocio (`Schedule::command(...)->dailyAt('00:10')->timezone(...)`). Por cada contrato vigente llama a `GenerateContractPeriodsAction`, que crea los meses faltantes desde el mes de inicio hasta el mes actual sin pasar del plazo; después `FinishExpiredContractsAction` finaliza los vencidos. Un contrato que falla se registra y no detiene a los demás.
- **Motivo**: RF-36 pide que cada mes aparezca sin intervención y que se reponga lo que no corrió; ser idempotente permite reintentar sin duplicar.
- **Alternativa descartada**: generar al abrir las pantallas. Dependería de que alguien entre y haría escrituras en lecturas.
- **Consecuencias**: el entorno de producción necesita el ciclo de `schedule:run` (supuesto de la spec). Se documenta en `AGENTS.md` §3.

### D-10: Alta del contrato desde la ficha del presupuesto

- **Decisión**: acción de cabecera «Crear contrato» en `ViewBudget`, con un modal precargado por `ProposeContractDefaultsAction`, igual que «Generar piezas» (spec 004).
- **Motivo**: el contrato siempre nace de un presupuesto (RF-1) y así el staff no elige un presupuesto desde una lista.
- **Alternativa descartada**: un formulario de alta en `ContractResource` con un selector de presupuestos. Repite una búsqueda y deja elegir uno no elegible.
- **Consecuencias**: la acción se muestra en cualquier presupuesto no descartado y los rechazos (RF-2 a RF-5) se informan al intentar, como en piezas.

### D-11: Portal del cliente como una página con estado de cuenta

- **Decisión**: `ContractsPage` en el panel `client`, con secciones de estado perezoso (se evalúan al renderizar), alimentadas por `BuildClientStatementAction`, que devuelve contratos, períodos, conceptos y pagos vigentes del cliente de la cuenta.
- **Motivo**: es solo lectura y de varios niveles; el patrón de `PieceApprovalPage` ya resolvió el refresco de secciones.
- **Alternativa descartada**: un `Resource` de Filament por contrato. Pide páginas de detalle, rutas con ids y una política por registro para algo que no tiene acciones.
- **Consecuencias**: el cliente nunca viene de la solicitud sino de la cuenta; la política `portal.contracts.view` se aplica a cada contrato devuelto.

### D-12: Fin de plazo precalculado

- **Decisión**: `contracts.term_end_month` guarda el primer día del último mes del plazo (nulo si es indefinido).
- **Motivo**: el comando de finalización, la reactivación (RF-27) y el chequeo del alta (RF-12) comparan contra el mes actual; con la columna son una condición indexable.
- **Alternativa descartada**: calcularlo en cada consulta a partir de `start_date` y `term_months`. Duplica la aritmética de meses en cada lugar.
- **Consecuencias**: `term_end_month` se fija al crear el contrato y no cambia (el plazo no es modificable).

### D-13: El cliente se copia también al período

- **Decisión**: `contract_periods.client_id` se copia del contrato.
- **Motivo**: los filtros del listado de períodos (RF-88) y el estado de cuenta del portal consultan por cliente; sin la copia cada consulta hace una unión.
- **Alternativa descartada**: unir siempre con `contracts`. Funciona, pero compite con los otros filtros en el listado de 5.000 períodos (RNF-3).
- **Consecuencias**: el cliente de un contrato no se modifica, así que la copia no se desfasa.

## 4. Modelo de datos

### contracts

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| budget_id | bigint unsigned | no | — | FK a `budgets`; único (RF-5) |
| client_id | bigint unsigned | no | — | FK a `clients`; copiado del presupuesto (RF-6) |
| start_date | date | no | — | fecha de inicio, cualquier fecha válida (RF-11) |
| term_months | smallint unsigned | sí | null | null = indefinido (RF-8) |
| term_end_month | date | sí | null | primer día del último mes del plazo; null si indefinido (D-12) |
| due_day | tinyint unsigned | no | — | 1 a 28 (RF-9) |
| monthly_amount | decimal(12,2) | no | — | copiado del total del presupuesto (RF-6) |
| status | string(10) | no | `active` | enum `ContractStatus` |
| created_by | bigint unsigned | no | — | FK a `users` |
| created_at / updated_at | timestamp | — | — | |
| deleted_at | timestamp | sí | null | SoftDeletes (principio 5) |

- **Índices**: único sobre `budget_id`; `client_id`; compuesto `(status, term_end_month)`; `start_date`.
- **Claves foráneas**: `budget_id` → `budgets.id`; `client_id` → `clients.id`; `created_by` → `users.id`.
- **Enums**: `ContractStatus` (`active`, `finished`, `cancelled`).
- **Soft deletes**: sí.

### contract_periods

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| contract_id | bigint unsigned | no | — | FK a `contracts` |
| client_id | bigint unsigned | no | — | copia del contrato (D-13) |
| month | date | no | — | primer día del mes que se cobra |
| due_date | date | no | — | vencimiento: `due_day` del contrato en ese mes (RF-40) |
| fee | decimal(12,2) | no | — | cuota: importe mensual del contrato al generar (RF-40) |
| total | decimal(12,2) | no | — | `fee` + extras − descuentos (RF-49), recalculado |
| paid_amount | decimal(12,2) | no | 0 | suma de pagos vigentes (RF-68), recalculado |
| invoice_number | string(50) | sí | null | referencia de factura (RF-53) |
| invoice_date | date | sí | null | |
| created_at / updated_at | timestamp | — | — | |

- **Índices**: único `(contract_id, month)` (RF-38); `client_id`; `due_date`; `month`.
- **Claves foráneas**: `contract_id` → `contracts.id`; `client_id` → `clients.id`.
- **Enums**: ninguno guardado (el estado de pago es derivado, `PaymentStatus`).
- **Soft deletes**: no; el borrado queda bloqueado en el modelo.

### period_concepts

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| contract_period_id | bigint unsigned | no | — | FK a `contract_periods` |
| type | string(10) | no | — | enum `ConceptType` |
| description | string(255) | no | — | descripción del extra o justificación del descuento |
| amount | decimal(10,2) | no | — | siempre mayor que cero; el signo lo da `type` |
| created_by | bigint unsigned | no | — | FK a `users` |
| created_at / updated_at | timestamp | — | — | |

- **Índices**: `contract_period_id`.
- **Claves foráneas**: `contract_period_id` → `contract_periods.id`; `created_by` → `users.id`.
- **Enums**: `ConceptType` (`extra`, `discount`).
- **Soft deletes**: no; el concepto quitado queda en el historial (D-5).

### payments

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| contract_period_id | bigint unsigned | no | — | FK a `contract_periods` |
| paid_on | date | no | — | fecha del pago, no futura (RF-66) |
| amount | decimal(12,2) | no | — | mayor que cero y no mayor que el saldo (RF-63, RF-64) |
| method | string(10) | no | — | enum `PaymentMethod` |
| reference | string(100) | sí | null | referencia opcional (RF-60) |
| created_by | bigint unsigned | no | — | FK a `users` |
| voided_at | timestamp | sí | null | no nulo = anulado (D-6) |
| voided_by | bigint unsigned | sí | null | FK a `users` |
| void_justification | string(255) | sí | null | obligatoria al anular (RF-76) |
| created_at / updated_at | timestamp | — | — | |
| deleted_at | timestamp | sí | null | SoftDeletes (principio 5); no se usa funcionalmente |

- **Índices**: compuesto `(contract_period_id, voided_at)`; `paid_on`.
- **Claves foráneas**: `contract_period_id` → `contract_periods.id`; `created_by` y `voided_by` → `users.id`.
- **Enums**: `PaymentMethod` (`cash`, `transfer`, `other`).
- **Soft deletes**: sí.

### contract_histories

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| contract_id | bigint unsigned | no | — | FK a `contracts` |
| field | string(20) | no | — | enum `ContractHistoryField` |
| old_value | json | sí | null | null en la creación |
| new_value | json | no | — | |
| author_id | bigint unsigned | sí | null | FK a `users`; null = el sistema (D-7) |
| created_at | timestamp | no | — | sin `updated_at`: solo alta |

- **Índices**: compuesto `(contract_id, created_at)`.
- **Claves foráneas**: `contract_id` → `contracts.id`; `author_id` → `users.id`.
- **Enums**: `ContractHistoryField` (`created`, `status_changed`, `amount_changed`, `due_day_changed`).
- **Soft deletes**: no aplica.

### period_histories

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| contract_period_id | bigint unsigned | no | — | FK a `contract_periods` |
| field | string(20) | no | — | enum `PeriodHistoryField` |
| old_value | json | sí | null | |
| new_value | json | no | — | |
| author_id | bigint unsigned | sí | null | null = el sistema (D-7) |
| created_at | timestamp | no | — | sin `updated_at` |

- **Índices**: compuesto `(contract_period_id, created_at)`.
- **Claves foráneas**: `contract_period_id` → `contract_periods.id`; `author_id` → `users.id`.
- **Enums**: `PeriodHistoryField` (`generated`, `concept_added`, `concept_removed`, `invoice_changed`, `payment_registered`, `payment_voided`).
- **Soft deletes**: no aplica.

Migraciones a crear, en orden:

1. `create_contracts_table`
2. `create_contract_periods_table`
3. `create_period_concepts_table`
4. `create_payments_table`
5. `create_contract_histories_table`
6. `create_period_histories_table`

## 5. Estructura de módulos

- **Config**: `config/app.php` gana `business_timezone`.
- **Support**: `BusinessDate` (nuevo); se reutiliza `Money`.
- **Models**: `Contract`, `ContractPeriod`, `PeriodConcept`, `Payment`, `ContractHistory`, `PeriodHistory` (nuevos); `Budget` gana la relación `contract()`; `Client` gana `contracts()` y `payments()`.
- **Enums**: `ContractStatus`, `ConceptType`, `PaymentMethod`, `PaymentStatus` (derivado), `ContractHistoryField`, `PeriodHistoryField`.
- **Exceptions**: `BudgetNotEligibleForContractException`, `BudgetAlreadyHasContractException`, `InvalidContractTransitionException`, `ContractTermElapsedException`, `PeriodLimitExceededException`, `PeriodTotalException`, `PaymentExceedsBalanceException`.
- **Actions**:
  - `ProposeContractDefaultsAction` — calcula la precarga del alta sin persistir.
  - `CreateContractAction` — valida y crea el contrato, y genera los períodos que le correspondan.
  - `UpdateContractAmountAction`, `UpdateContractDueDayAction` — cambios hacia adelante.
  - `CancelContractAction`, `ReactivateContractAction`, `FinishExpiredContractsAction` — transiciones de estado.
  - `GenerateContractPeriodsAction` — crea los períodos faltantes de un contrato.
  - `AddPeriodExtraAction`, `AddPeriodDiscountAction`, `RemovePeriodConceptAction` — conceptos.
  - `SetPeriodInvoiceAction` — referencia de factura.
  - `RecalculatePeriodTotalsAction` — recalcula `total` y `paid_amount`.
  - `RegisterPaymentAction`, `VoidPaymentAction` — pagos.
  - `BuildClientStatementAction` — estado de cuenta de solo lectura para el portal.
  - Bases abstractas `ChangesContractStatus` (patrón `ChangesBudgetStatus`) y `ChangesPeriod` (abre la transacción, bloquea el período, recalcula y registra el historial).
- **Commands**: `GenerateContractPeriods` (`contracts:generate-periods`).
- **Policies**: `ContractPolicy`, `ContractPeriodPolicy`, `PaymentPolicy` (staff), `ContractPortalPolicy` (cuenta `client`, como gate `portal.contracts.view`).
- **Filament Resources / Pages / Widgets**:
  - Panel `staff`: `ContractResource` (listado con filtros, ficha con acciones, relation managers de períodos e historial); `ContractPeriodResource` (listado con filtros, ficha con relation managers de conceptos, pagos e historial y la acción de factura); `ContractActions` y `PeriodActions` (acciones); acción «Crear contrato» en `ViewBudget`; `ClientContractsSection` y `ClientPaymentsSection` registradas en `ClientCardExtensions`.
  - Panel `client`: `ContractsPage` con contratos, períodos y pagos vigentes.
- **Rutas**: ninguna manual; la programación se declara en `routes/console.php`.
- **Traducciones**: `lang/es/contracts.php` (etiquetas, estados, formas de pago, mensajes de rechazo, historial y «Sistema»).

## 6. Contratos de Actions y servicios

- **ProposeContractDefaultsAction::handle(Client $client): array** — devuelve `due_day`, `term_months`, `indefinite` y `start_date` propuestos desde el contrato de `start_date` más reciente del cliente (desempata por alta más reciente) y vacío si no tiene contratos; `start_date` solo si ese contrato es de plazo fijo. No devuelve el importe.
- **CreateContractAction::handle(Budget $budget, array $data, User $author): Contract** — valida presupuesto aceptado, de abono mensual, con total mayor que cero y sin contrato; valida `start_date`, `due_day` y plazo; rechaza un plazo fijo ya transcurrido; crea el contrato vigente, escribe el historial y genera sus períodos faltantes. Lanza `BudgetNotEligibleForContractException`, `BudgetAlreadyHasContractException`, `ContractTermElapsedException`, `ValidationException`.
- **UpdateContractAmountAction::handle(Contract $contract, mixed $amount, User $actor): Contract** — exige contrato vigente y un importe mayor que cero con 2 decimales; solo cambia `monthly_amount`; escribe el historial. Lanza `InvalidContractTransitionException`, `ValidationException`.
- **UpdateContractDueDayAction::handle(Contract $contract, mixed $day, User $actor): Contract** — mismo patrón para `due_day` (1 a 28).
- **CancelContractAction::handle(Contract $contract, User $actor): Contract** — de `active` a `cancelled`; no toca los períodos.
- **ReactivateContractAction::handle(Contract $contract, User $actor): Contract** — de `cancelled` a `active`; rechaza un contrato finalizado y un cancelado cuyo plazo fijo ya transcurrió; después genera los períodos faltantes. Lanza `InvalidContractTransitionException`, `ContractTermElapsedException`.
- **FinishExpiredContractsAction::handle(): int** — finaliza los contratos vigentes de plazo fijo cuyo `term_end_month` es anterior al mes actual, con el sistema como autor; devuelve la cantidad.
- **GenerateContractPeriodsAction::handle(Contract $contract): Collection** — crea los meses faltantes desde el mes de inicio hasta el mes actual, sin pasar de `term_end_month`; cada período toma `fee` y `due_date` del contrato vigente y escribe su asiento `generated` con el sistema como autor. Solo opera sobre contratos `active`.
- **AddPeriodExtraAction::handle(ContractPeriod $period, array $data, User $actor): PeriodConcept** y **AddPeriodDiscountAction::handle(...)** — validan descripción o justificación e importe mayor que cero; rechazan el concepto 101; el descuento no puede dejar el total bajo cero ni bajo lo pagado. Lanzan `PeriodLimitExceededException`, `PeriodTotalException`, `ValidationException`.
- **RemovePeriodConceptAction::handle(PeriodConcept $concept, User $actor): void** — rechaza si el total quedaría bajo lo pagado; borra la fila y deja la foto en el historial. Lanza `PeriodTotalException`.
- **SetPeriodInvoiceAction::handle(ContractPeriod $period, ?string $number, mixed $date, User $actor): ContractPeriod** — número y fecha juntos o ninguno; acepta el mismo número en otros períodos. Lanza `ValidationException`.
- **RecalculatePeriodTotalsAction::handle(ContractPeriod $period): ContractPeriod** — recalcula `total` y `paid_amount` en centavos.
- **RegisterPaymentAction::handle(ContractPeriod $period, array $data, User $actor): Payment** — bloquea el período; valida importe mayor que cero, no mayor que el saldo vigente (informa el saldo), fecha no futura según `BusinessDate` y el tope de 100 pagos vigentes; escribe el historial. Lanza `PaymentExceedsBalanceException`, `PeriodLimitExceededException`, `ValidationException`.
- **VoidPaymentAction::handle(Payment $payment, string $justification, User $actor): Payment** — exige justificación; marca el pago como anulado; recalcula; escribe el historial. Lanza `ValidationException`.
- **BuildClientStatementAction::handle(User $account): array** — contratos, períodos con conceptos y pagos vigentes del cliente de la cuenta, con carga anticipada; aplica `portal.contracts.view` a cada contrato.

## 7. Reglas de negocio y validaciones

- Un contrato nace solo de un presupuesto aceptado de modalidad abono mensual, con total mayor que cero y sin contrato previo.
- El importe mensual del contrato es siempre el del presupuesto de origen; una precarga nunca lo trae de otro contrato.
- `due_day` entero de 1 a 28; plazo entero mayor que cero o indefinido; `start_date` cualquier fecha válida.
- Un plazo fijo cuyo mes siguiente al último ya comenzó no puede crearse ni reactivarse; un contrato finalizado no se reactiva.
- Un cambio de importe o de día de vencimiento afecta solo a períodos generados después; los existentes, incluido el del mes en curso, no cambian.
- A lo sumo un período por contrato y mes; en plazo fijo, no más períodos que meses del plazo.
- La generación no mira el estado del cliente: un cliente inactivo o archivado sigue generando y admitiendo pagos.
- Un período puede nacer vencido; el vencido es una señal (`due_date` anterior a hoy y saldo mayor que cero), no un estado.
- Total del período = cuota + extras − descuentos; nunca menor que cero ni menor que lo pagado.
- Hasta 100 conceptos por período y hasta 100 pagos vigentes.
- Un pago es mayor que cero, no supera el saldo vigente al confirmar y no tiene fecha futura; cualquier fecha pasada es válida.
- Un pago no se edita; se anula con justificación y se conserva. Lo pagado suma solo pagos vigentes.
- El saldo cero es «pagado», incluso con total cero y sin pagos.
- Todo cambio de estado, de importe, de día de vencimiento, de concepto, de factura y de pago deja un asiento con autor (nulo = sistema) y fecha.
- Fechas y meses de negocio salen de `BusinessDate`; los importes se manejan en centavos con `Money`.

## 8. Autorización

- `ContractPolicy`, `ContractPeriodPolicy` y `PaymentPolicy` autorizan por igual a `admin` y `staff` (ver, crear, cambiar importe y vencimiento, cancelar, reactivar, conceptos, factura, registrar y anular pagos); a la cuenta `client` la rechazan con el mensaje de permiso insuficiente. `delete`, `restore` y `forceDelete` se rechazan para todos.
- `ContractPortalPolicy` (gate `portal.contracts.view`) autoriza a una cuenta `client` vinculada solo sobre contratos de su cliente; ante otro cliente o una cuenta sin vínculo rechaza con permiso insuficiente.
- El panel `client` no ofrece ninguna acción de escritura; las Actions quedan inalcanzables para una cuenta `client` porque las policies la rechazan.

## 9. Estrategia de tests

- **Unit**: validación del alta y sus rechazos (no aceptado, pago único, total cero, ya tiene contrato, plazo transcurrido); precarga y desempate; transiciones de estado, reactivación y finalización; generación de períodos (mes a mes, reposición, plazo, idempotencia, inicio futuro, cliente archivado); cuota y vencimiento copiados; cambios hacia adelante; totales de conceptos y topes; reglas de descuento contra cero y contra lo pagado; pagos (importe, saldo, fecha, tope, saldo que cambió entre la apertura y la confirmación); anulación; estado de pago, vencido y total cero; referencia de factura; autor nulo como sistema; `BusinessDate` en el borde de las 21:00.
- **Feature**: el comando `contracts:generate-periods` (genera, repone, finaliza y tolera un contrato fallido); flujo del staff en las pantallas (alta desde la ficha del presupuesto, filtros, conceptos, pagos, anulación, factura); secciones de la ficha del cliente; el portal del cliente (solo lo suyo, sin pagos anulados, sin acciones); permisos de `admin`, `staff` y `client`.
- **Performance** (grupo `performance`, mejor de tres corridas con `fastestOf`): listado de 5.000 períodos con filtros (RNF-3); generación del mes para 500 contratos (RNF-4); portal con 60 períodos y 200 pagos (RNF-5).
- **Factories nuevas**: `ContractFactory`, `ContractPeriodFactory`, `PeriodConceptFactory`, `PaymentFactory`, `ContractHistoryFactory`, `PeriodHistoryFactory`, con estados (`cancelled`, `finished`, `indefinite`, `voided`, `overdue`).
- **Seeders**: ninguno nuevo.

## 10. Mapa RF → componente

| RF | Descripción corta | Cubierto por |
|---|---|---|
| RF-1 | Crear contrato desde presupuesto aceptado de abono mensual | `CreateContractAction`, acción «Crear contrato» en `ViewBudget`, `ContractPolicy` |
| RF-2, RF-3, RF-4 | Rechazo si no está aceptado, es pago único o total cero | `CreateContractAction`, `BudgetNotEligibleForContractException` |
| RF-5 | Un contrato por presupuesto | índice único `contracts.budget_id`, `BudgetAlreadyHasContractException` |
| RF-6 | Copia cliente e importe del presupuesto | `CreateContractAction`, columnas `client_id` y `monthly_amount` |
| RF-7, RF-8, RF-9 | Inicio, vencimiento y plazo obligatorios y válidos | `CreateContractAction` (validación), columnas `start_date`, `term_months`, `due_day` |
| RF-10 | Importe al modificar mayor que cero, 2 decimales | `UpdateContractAmountAction` |
| RF-11 | Cualquier fecha de inicio válida | `CreateContractAction` (sin otro límite) |
| RF-12 | Plazo ya transcurrido no se crea | `CreateContractAction`, `ContractTermElapsedException`, `term_end_month` |
| RF-13, RF-14, RF-15 | Precarga de vencimiento, plazo y fecha de inicio | `ProposeContractDefaultsAction` |
| RF-16 | Valores propuestos modificables | formulario del modal de «Crear contrato» |
| RF-17 | El importe no se copia de otro contrato | `ProposeContractDefaultsAction` (no devuelve importe) |
| RF-18 | El contrato no cambia con su presupuesto | ausencia deliberada de acoplamiento (D-1) |
| RF-19, RF-20 | Estado único, nace vigente | enum `ContractStatus`, `CreateContractAction` |
| RF-21, RF-22, RF-23 | Cancelar, dejar de generar, conservar períodos | `CancelContractAction`, `GenerateContractPeriodsAction` (solo `active`) |
| RF-24 | Pagos en contratos cancelados o finalizados | `RegisterPaymentAction` (no mira el estado del contrato) |
| RF-25, RF-26, RF-27, RF-28 | Reactivar y sus rechazos | `ReactivateContractAction`, `ContractTermElapsedException`, `InvalidContractTransitionException` |
| RF-29 | Finalizar al terminar el plazo | `FinishExpiredContractsAction`, comando `contracts:generate-periods` |
| RF-30, RF-31, RF-32 | Cambiar importe y vencimiento hacia adelante | `UpdateContractAmountAction`, `UpdateContractDueDayAction`, `fee` y `due_date` copiados (D-2) |
| RF-33 | Historial con autor, nulo = sistema | tabla `contract_histories`, D-7 |
| RF-34 | Nunca se elimina un contrato | `SoftDeletes`, bloqueo de `forceDelete`, `ContractPolicy` |
| RF-35 | Cliente inactivo o archivado sigue | `GenerateContractPeriodsAction` sin filtro de cliente, relación `client()` con eliminados |
| RF-36, RF-37 | Generar los faltantes; inicio futuro no genera | `GenerateContractPeriodsAction`, comando programado (D-9), `CreateContractAction` |
| RF-38 | A lo sumo un período por mes | índice único `(contract_id, month)` |
| RF-39 | No más períodos que meses del plazo | `GenerateContractPeriodsAction`, `term_end_month` |
| RF-40 | Cuota y vencimiento, aunque haya pasado | `GenerateContractPeriodsAction`, columnas `fee` y `due_date` |
| RF-41 | Asiento de generación con el sistema | `period_histories` (`generated`, autor nulo) |
| RF-42, RF-43, RF-44, RF-45 | Agregar extras y descuentos | `AddPeriodExtraAction`, `AddPeriodDiscountAction`, tabla `period_concepts` |
| RF-46 | Tope de 100 conceptos | `AddPeriodExtraAction`, `AddPeriodDiscountAction`, `PeriodLimitExceededException` |
| RF-47, RF-48 | Quitar y conservar en el historial | `RemovePeriodConceptAction`, `period_histories` (`concept_removed`) |
| RF-49 | Total del período | `RecalculatePeriodTotalsAction`, columna `total` |
| RF-50, RF-51 | Total no menor que cero ni que lo pagado | `AddPeriodDiscountAction`, `RemovePeriodConceptAction`, `PeriodTotalException` |
| RF-52 | Asiento de conceptos | `period_histories` (`concept_added`, `concept_removed`) |
| RF-53, RF-54, RF-55, RF-56 | Referencia de factura opcional, número y fecha juntos, repetible | `SetPeriodInvoiceAction`, columnas `invoice_number` e `invoice_date` |
| RF-57 | Asiento de la factura | `period_histories` (`invoice_changed`) |
| RF-58, RF-59, RF-60, RF-61 | Registrar pagos, formas, referencia, varios por período | `RegisterPaymentAction`, enum `PaymentMethod`, tabla `payments` |
| RF-62 | Tope de 100 pagos vigentes | `RegisterPaymentAction`, `PeriodLimitExceededException` |
| RF-63, RF-64 | Importe mayor que cero y no mayor que el saldo | `RegisterPaymentAction`, `PaymentExceedsBalanceException` |
| RF-65 | Validar contra el saldo vigente al confirmar | `RegisterPaymentAction` (transacción con `lockForUpdate`, D-8) |
| RF-66, RF-67 | Fecha no futura, cualquier fecha pasada | `RegisterPaymentAction`, `BusinessDate` |
| RF-68, RF-69 | Pagado y saldo | `RecalculatePeriodTotalsAction`, columna `paid_amount`, accesor `balance` |
| RF-70, RF-71, RF-72, RF-73 | Estado de pago derivado | enum `PaymentStatus`, `ContractPeriod::paymentStatus()` y scopes |
| RF-74 | Señal de vencido | scope `ContractPeriod::overdue()`, `BusinessDate::today()` |
| RF-75, RF-76, RF-77 | Anular con justificación, conservar | `VoidPaymentAction`, columnas `voided_*` (D-6) |
| RF-78 | Un pago no se edita | `Payment` (modelo sin edición salvo anulación), `PaymentPolicy` |
| RF-79 | Asiento de pagos y anulaciones | `period_histories` (`payment_registered`, `payment_voided`) |
| RF-80 | Nunca se elimina un pago | `SoftDeletes`, bloqueo de `forceDelete`, `PaymentPolicy` |
| RF-81, RF-82, RF-83 | Listado de contratos y sus filtros | `ContractResource` (tabla), `ContractsTable` |
| RF-84 | Ficha del contrato con sus períodos | `ViewContract`, `PeriodsRelationManager` |
| RF-85 | Detalle del período | `ViewContractPeriod`, `ConceptsRelationManager`, `PaymentsRelationManager` |
| RF-86 | Historial cronológico | `HistoriesRelationManager` de contrato y de período |
| RF-87, RF-88, RF-89, RF-90 | Listado de períodos y sus filtros | `ContractPeriodResource` (tabla), `ContractPeriodsTable` |
| RF-91, RF-92 | Contratos y pagos en la ficha del cliente | `ClientContractsSection`, `ClientPaymentsSection` en `ClientCardExtensions` |
| RF-93, RF-94, RF-95, RF-96 | Portal del cliente de solo lectura | `ContractsPage`, `BuildClientStatementAction` |
| RF-97 | Otro cliente rechazado con permiso insuficiente | `ContractPortalPolicy` (gate `portal.contracts.view`) |
| RF-98 | El cliente no puede escribir | `ContractPolicy`, `ContractPeriodPolicy`, `PaymentPolicy`, panel `client` sin acciones |
| RNF-1 | Importes con 2 decimales | columnas `decimal(…, 2)`, `Money` |
| RNF-2 | Redondeo al centavo | `Money`, `RecalculatePeriodTotalsAction` en centavos |
| RNF-3 | Listado de 5.000 períodos en menos de 2 s | índices de `contract_periods`, `client_id` copiado (D-13), carga anticipada, test de performance |
| RNF-4 | Generar 500 contratos en menos de 10 s | `GenerateContractPeriodsAction` con inserción por lotes, test de performance |
| RNF-5 | Portal con 60 períodos y 200 pagos en menos de 2 s | `BuildClientStatementAction` con carga anticipada, test de performance |
| RNF-6 | Historial y pagos sin purga | `contract_histories`, `period_histories`, `payments` sin tarea de limpieza |

## 11. Riesgos

- **Hora de negocio contra UTC**: un cálculo que use `today()` en lugar de `BusinessDate` falla entre las 21:00 y las 24:00 de Argentina. Mitigación: un test por cada regla con fecha en el borde, y una revisión en cada tarea.
- **Programación no activa en producción**: sin el ciclo de `schedule:run` no se generan períodos. Mitigación: el comando es idempotente y repone lo faltante en la siguiente corrida; se documenta en `AGENTS.md` §3.
- **Desfase entre `total`/`paid_amount` y sus filas**: si algo escribe sin pasar por las Actions, las columnas guardadas dejan de coincidir. Mitigación: ninguna otra clase escribe conceptos ni pagos, y un test compara las columnas con las sumas reales.
- **El bloqueo no corre en SQLite**: la concurrencia real no se ejercita en los tests. Mitigación: se prueba la regla con el saldo que cambia entre la apertura y la confirmación (RF-65); queda anotado como verificación manual con MySQL.
- **Cambios en la ficha del cliente**: `ClientCardExtensions` tiene tests que asumen qué secciones hay registradas. Mitigación: los tests existentes se revisan al registrar las dos secciones nuevas.
- **Volumen de la spec**: 98 RF producen muchas tareas. Mitigación: el desglose en tareas va por bloques (contratos, períodos, conceptos, pagos, pantallas, portal) para poder validar por tramos.
- **Reposición al reactivar**: reactivar un contrato factura los meses sin generar. Está decidido en la spec (RF-26); se avisa en el mensaje de la acción.

## 12. Fuera de alcance del plan

- No cambia la zona horaria global de la aplicación ni las fechas de las specs 002 y 004 (D-4).
- No agrega usuario ni rol «sistema» (D-7).
- No implementa aviso de vencimientos, recargos por mora, saldo a favor, recibos en PDF, ni integración con plataformas de pago o con ARCA; la spec los deja fuera.
- No incluye bloqueo optimista ni reintentos automáticos de pagos (D-8).
- No hay interfaz para lanzar a mano la generación de períodos; se corre con `php artisan contracts:generate-periods`.

## 13. Preguntas abiertas

Ninguna: las decisiones técnicas quedaron resueltas en la sección 3, cada una con su alternativa descartada.

## 14. Aprobación

- [x] Aprobado por Claudio el 2026-10-10

## 15. Ajustes durante la implementación

Lo que se implementó y difiere de lo descrito arriba, o lo completa. Ninguno cambia un requisito.

- **Historial de una baja de concepto (D-5).** `new_value` de `period_histories` y de `contract_histories` no admite nulo, así que `concept_removed` guarda la foto del concepto en `old_value` y `{"removed": true}` en `new_value`, igual que la baja de un ítem de presupuesto.
- **Asientos.** `status_changed` guarda `{"status": …}` antes y después; `amount_changed` y `due_day_changed`, `{"monthly_amount": …}` y `{"due_day": …}`; `invoice_changed`, `{"number", "date"}` (ambos nulos cuando se quita la referencia); `payment_registered`, la foto del pago; `payment_voided`, la foto del pago en `old_value` y `{"id", "justification"}` en `new_value`.
- **Bases abstractas.** Además de `ChangesContractStatus` y `ChangesPeriod` existen `AddsPeriodConcept` (extras y descuentos) y `ChangesContractTerms` (importe y día de vencimiento). `ChangesPeriod` valida al final que el total no quede bajo cero ni bajo lo pagado, y revierte todo si falla.
- **Excepciones.** `InvalidContractTransitionException` se construye con `transition()` (cancelar, reactivar) o `notActive()` (modificar un contrato que no está vigente); `PeriodLimitExceededException`, con `concepts()` o `payments()`.
- **`ContractPeriod`.** Expone `balance`, `payment_status` e `isOverdue()`, y los scopes `pending`, `partial`, `paid` y `overdue` sobre `total`, `paid_amount` y `due_date`. El estado de pago no se cachea como objeto, porque el recálculo cambia las columnas de las que depende.
- **`Payment`.** El modelo rechaza cualquier modificación que no sea la anulación (una vez) y cualquier baja, también la lógica, no solo el borrado definitivo (RF-78, RF-80).
- **Anulación y concurrencia.** `VoidPaymentAction` relee el pago con el período bloqueado, así que una segunda anulación sobre un pago ya anulado se rechaza.
- **Estado de cuenta.** `BuildClientStatementAction` devuelve arrays (contratos y períodos del más reciente al más antiguo, con los pagos vigentes), no modelos, y arma todo con una cantidad fija de consultas.
- **Comando.** `contracts:generate-periods` termina con código de error si algún contrato falló, además de informarlo; `FinishExpiredContractsAction` se ejecuta después de la generación.
- **Importes de formularios.** Los formularios de Filament entregan los números como `int` o `float`; las Actions los normalizan y rechazan los que no sean enteros o tengan más de 2 decimales, sin redondear los del importe mensual.
