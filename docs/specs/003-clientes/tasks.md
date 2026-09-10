# 003 — Tareas: Clientes

| | |
|---|---|
| **Spec** | docs/specs/003-clientes/spec.md |
| **Plan** | docs/specs/003-clientes/plan.md |
| **Estado** | En curso |
| **Fecha** | 2026-09-10 |

## Convenciones

- Checkbox al inicio de cada tarea. Se marca al terminarla, no antes.
- Orden por dependencias: cada tarea depende solo de las anteriores.
- TDD estricto: el test va antes que la implementación.
- La suite queda verde después de cada tarea de `impl`.
- Cada tarea de `impl` que produce un mensaje visible crea también sus claves en
  `lang/es/clients.php` (constitución, principio 6). No hay una tarea separada de traducciones.
- **Precondición**: requiere la spec `001-acceso-roles-y-paneles` implementada (tabla `users`,
  roles `admin`/`staff`/`cliente`, ambos paneles Filament, middleware de sesión activa).
- **Orden de implementación entre specs**: 003 → 002 → 004. Esta spec va primero porque
  `002-presupuestos` necesita un `Client` con `displayName()` y `contactPhone()` ya resueltos.
- **Esta spec no cubre sus RF-46, RF-47 ni RF-48** ("DONDE el módulo de Presupuestos/Contratos/
  Pagos esté implementado…"): ninguno de esos módulos existe todavía. RF-46 lo cubre una tarea del
  `tasks.md` de `002-presupuestos`; RF-47 y RF-48 quedan diferidos hasta que existan las specs de
  Contratos y Pagos.
- **Total de 45 tareas supera el techo de ~40 de la skill**: se acepta para mantener pares
  test/impl de 20-30 min sin agrupar RF de secciones distintas (ver "Cambios en el plan
  detectados").

## Resumen

- Total: 45 tareas.
- Por tipo: 16 test, 18 impl, 5 migration, 6 ui.
- Cubre: RF-1 a RF-45, RF-49 a RF-62 (RF-46/47/48 diferidos), RNF-1 a RNF-7.

---

## Fase 1: Esquema y datos de referencia

### - [ ] T1: Crear migración y seeder de `provinces`

- **Tipo**: migration
- **Cubre**: — (soporte de RF-9, plan D-10)
- **Depende de**: —
- **Hecho cuando**: `php artisan migrate:fresh --seed` crea `provinces` con `id` y `name` único, y
  `ProvinceSeeder` deja cargadas las 24 provincias argentinas.

### - [ ] T2: Crear migración de `clients`

- **Tipo**: migration
- **Cubre**: RF-2, RF-23, RF-24
- **Depende de**: T1
- **Hecho cuando**: la tabla existe con `person_type`, `first_name`, `last_name`, `company_name`,
  `document`, `status` (default `active`), columnas de domicilio, `province_id`, `created_by`,
  `deleted_at`, según el plan §4, con `UNIQUE(document)` sin condicionar a `deleted_at` (D-4).

### - [ ] T3: Crear migración de `client_contacts`

- **Tipo**: migration
- **Cubre**: RF-16, RF-17
- **Depende de**: T2
- **Hecho cuando**: la tabla existe con `client_id`, `name`, `role`, `phone`, `email`,
  `is_primary`, y la columna generada `primary_marker` con índice `UNIQUE` (plan D-8).

### - [ ] T4: Crear migración de `client_histories`

- **Tipo**: migration
- **Cubre**: RF-35, RF-36
- **Depende de**: T2
- **Hecho cuando**: la tabla existe con `client_id`, `field`, `old_value`, `new_value`,
  `author_id`, `created_at`, índice `(client_id, created_at)`, sin columna `updated_at`.

### - [ ] T5: Crear migración `add_client_id_to_users`

- **Tipo**: migration
- **Cubre**: RF-38, RF-39
- **Depende de**: T2
- **Hecho cuando**: `users` tiene `client_id` nullable con FK a `clients` (`set null` on delete) e
  índice sobre esa columna.

### - [ ] T6: Crear enums, modelos y factories

- **Tipo**: impl
- **Cubre**: RF-2, RF-23
- **Depende de**: T3, T4, T5
- **Hecho cuando**: existen `ClientType`, `ClientStatus`, `ClientHistoryField` como backed enums;
  `Client` (con `SoftDeletes`, `HasFactory`), `ClientContact`, `ClientHistory` y `User::client()`
  resuelven sus relaciones en un test de humo verde; `ClientFactory`, `ClientContactFactory` y
  `ClientHistoryFactory` existen y producen registros válidos.

---

## Fase 2: Alta e identificación

### - [ ] T7: Escribir test de alta según tipo de persona

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-24, RF-35
- **Depende de**: T6
- **Hecho cuando**: `pest --filter=CreateClient` falla porque `CreateClientAction` no existe;
  cubre alta de persona física (nombre, apellido, DNI) y jurídica (razón social, CUIT), el
  accessor `displayName()` según cada caso, que nace `active`, y el asiento de alta en
  `client_histories`; commit del test hecho.

### - [ ] T8: Implementar `CreateClientAction` con `displayName()`

- **Tipo**: impl
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-24, RF-35
- **Depende de**: T7
- **Hecho cuando**: el test de T7 pasa y la suite completa queda verde.

### - [ ] T9: Escribir test de formato de DNI y CUIT

- **Tipo**: test
- **Cubre**: RNF-2, RNF-3
- **Depende de**: T8
- **Hecho cuando**: el test falla y cubre DNI válido (7 y 8 dígitos) e inválido, y CUIT válido e
  inválido según el dígito verificador módulo 11, con casos conocidos de ambos; commit del test
  hecho.

### - [ ] T10: Implementar la validación de DNI y CUIT

- **Tipo**: impl
- **Cubre**: RNF-2, RNF-3
- **Depende de**: T9
- **Hecho cuando**: el test de T9 pasa y la suite completa queda verde.

### - [ ] T11: Escribir test de unicidad de documento incluyendo archivados

- **Tipo**: test
- **Cubre**: RF-6
- **Depende de**: T10
- **Hecho cuando**: el test falla y comprueba que un documento repetido (con y sin espacios,
  puntos o guiones, y contra un cliente archivado) no crea el cliente y muestra el conflicto
  indicando el cliente existente; commit del test hecho.

### - [ ] T12: Implementar la validación de unicidad de documento con `withTrashed()`

- **Tipo**: impl
- **Cubre**: RF-6
- **Depende de**: T11
- **Hecho cuando**: el test de T11 pasa y la suite completa queda verde.

### - [ ] T13: Escribir test de edición de identificación y bloqueo de tipo de persona

- **Tipo**: test
- **Cubre**: RF-7, RF-8, RF-36, RNF-1
- **Depende de**: T12
- **Hecho cuando**: el test falla y cubre edición válida de nombre/apellido/razón social/documento
  con asiento de historial, el rechazo al intentar cambiar `person_type`, y el rechazo de nombres
  de más de 150 caracteres; commit del test hecho.

### - [ ] T14: Implementar `UpdateClientIdentificationAction`

- **Tipo**: impl
- **Cubre**: RF-7, RF-8, RF-36, RNF-1
- **Depende de**: T13
- **Hecho cuando**: el test de T13 pasa y la suite completa queda verde.

---

## Fase 3: Domicilio

### - [ ] T15: Escribir test de carga y edición de domicilio

- **Tipo**: test
- **Cubre**: RF-9, RF-10, RF-11, RF-12, RF-36
- **Depende de**: T14
- **Hecho cuando**: el test falla y comprueba que el domicilio es opcional al alta, que se puede
  cargar y editar después, que solo admite uno por cliente, y que cada cambio deja asiento de
  historial; commit del test hecho.

### - [ ] T16: Implementar `UpdateClientAddressAction`

- **Tipo**: impl
- **Cubre**: RF-9, RF-10, RF-11, RF-12, RF-36
- **Depende de**: T15
- **Hecho cuando**: el test de T15 pasa y la suite completa queda verde.

---

## Fase 4: Contactos

### - [ ] T17: Escribir test de alta de contacto con principal automático

- **Tipo**: test
- **Cubre**: RF-13, RF-14, RF-15, RF-16, RF-36, RNF-4
- **Depende de**: T16
- **Hecho cuando**: el test falla y cubre: contacto opcional al alta, exigencia de teléfono o
  email, que el primer contacto agregado queda marcado principal, asiento de historial, y rechazo
  al intentar un contacto número 11; commit del test hecho.

### - [ ] T18: Implementar `AddClientContactAction`

- **Tipo**: impl
- **Cubre**: RF-13, RF-14, RF-15, RF-16, RF-36, RNF-4
- **Depende de**: T17
- **Hecho cuando**: el test de T17 pasa y la suite completa queda verde.

### - [ ] T19: Escribir test de edición y quita de contacto con reasignación de principal

- **Tipo**: test
- **Cubre**: RF-17, RF-19, RF-20, RF-36
- **Depende de**: T18
- **Hecho cuando**: el test falla y cubre edición de un contacto existente, que exactamente uno
  queda principal cuando hay más de uno, y que al quitar el contacto principal con otros contactos
  restantes el sistema marca otro como principal automáticamente; commit del test hecho.

### - [ ] T20: Implementar `UpdateClientContactAction` y `RemoveClientContactAction`

- **Tipo**: impl
- **Cubre**: RF-17, RF-19, RF-20, RF-36
- **Depende de**: T19
- **Hecho cuando**: el test de T19 pasa y la suite completa queda verde.

### - [ ] T21: Escribir test de cambio explícito de contacto principal

- **Tipo**: test
- **Cubre**: RF-18, RF-36
- **Depende de**: T20
- **Hecho cuando**: el test falla y comprueba que cambiar cuál contacto es el principal desmarca
  el anterior y marca el nuevo dentro de una transacción, sin dejar dos principales a la vez;
  commit del test hecho.

### - [ ] T22: Implementar `SetPrimaryContactAction`

- **Tipo**: impl
- **Cubre**: RF-18, RF-36
- **Depende de**: T21
- **Hecho cuando**: el test de T21 pasa y la suite completa queda verde.

### - [ ] T23: Escribir test del teléfono de contacto del cliente

- **Tipo**: test
- **Cubre**: RF-21, RF-22
- **Depende de**: T22
- **Hecho cuando**: el test falla y cubre: teléfono del principal cuando lo tiene, teléfono de
  otro contacto cuando el principal no lo tiene, y la indicación explícita de ausencia cuando
  ningún contacto tiene teléfono; commit del test hecho.

### - [ ] T24: Implementar el accessor `Client::contactPhone()`

- **Tipo**: impl
- **Cubre**: RF-21, RF-22
- **Depende de**: T23
- **Hecho cuando**: el test de T23 pasa y la suite completa queda verde.

---

## Fase 5: Estado activo/inactivo y archivado

### - [ ] T25: Escribir test de activar/desactivar y disponibilidad para presupuestos

- **Tipo**: test
- **Cubre**: RF-25, RF-26, RF-27, RF-36
- **Depende de**: T24
- **Hecho cuando**: el test falla y cubre marcar inactivo y reactivar con asiento de historial, que
  un cliente inactivo no aparece en el scope de disponibles para presupuestos nuevos, y que su
  ficha e historial siguen consultables; commit del test hecho.

### - [ ] T26: Implementar `ActivateClientAction`, `DeactivateClientAction` y el scope `availableForBudgets()`

- **Tipo**: impl
- **Cubre**: RF-25, RF-26, RF-27, RF-36
- **Depende de**: T25
- **Hecho cuando**: el test de T25 pasa y la suite completa queda verde.

### - [ ] T27: Escribir test de archivado y restauración

- **Tipo**: test
- **Cubre**: RF-28, RF-29, RF-30, RF-31, RF-32, RF-33, RF-34, RF-36
- **Depende de**: T26
- **Hecho cuando**: el test falla y cubre: archivar deja el cliente inactivo y fuera del listado y
  del scope de disponibles, restaurar lo deja inactivo, ninguna de las dos operaciones lo elimina
  ni borra su historial, y ambas dejan asiento; commit del test hecho.

### - [ ] T28: Implementar `ArchiveClientAction` y `RestoreClientAction`

- **Tipo**: impl
- **Cubre**: RF-28, RF-29, RF-30, RF-31, RF-32, RF-33, RF-34, RF-36
- **Depende de**: T27
- **Hecho cuando**: el test de T27 pasa y la suite completa queda verde.

---

## Fase 6: Vínculo con cuentas del portal

### - [ ] T29: Escribir test de vinculación de cuenta con rechazo de doble vínculo

- **Tipo**: test
- **Cubre**: RF-38, RF-39, RF-40, RF-44
- **Depende de**: T28
- **Hecho cuando**: el test falla y cubre vincular una cuenta a un cliente, que un mismo cliente
  admite varias cuentas, que una cuenta ya vinculada a otro cliente no se puede vincular de nuevo
  (con motivo), y el asiento de historial; commit del test hecho.

### - [ ] T30: Implementar `LinkAccountToClientAction`

- **Tipo**: impl
- **Cubre**: RF-38, RF-39, RF-40, RF-44
- **Depende de**: T29
- **Hecho cuando**: el test de T29 pasa y la suite completa queda verde.

### - [ ] T31: Escribir test de desvinculación con corte de sesión

- **Tipo**: test
- **Cubre**: RF-41, RF-42, RF-44
- **Depende de**: T30
- **Hecho cuando**: el test falla y comprueba que desvincular limpia `client_id`, que una cuenta
  con sesión abierta en el portal de clientes queda fuera en su siguiente solicitud, y el asiento
  de historial; commit del test hecho.

### - [ ] T32: Implementar `UnlinkAccountFromClientAction`

- **Tipo**: impl
- **Cubre**: RF-41, RF-42, RF-44
- **Depende de**: T31
- **Hecho cuando**: el test de T31 pasa y la suite completa queda verde.

---

## Fase 7: Panel de staff

### - [ ] T33: Escribir test de `ClientPolicy`

- **Tipo**: test
- **Cubre**: — (autorización transversal a RF-1, RF-7, RF-25, RF-28, RF-38, RF-41, RF-56)
- **Depende de**: T32
- **Hecho cuando**: el test falla y comprueba que `admin` y `staff` tienen exactamente los mismos
  permisos sobre clientes (alta, edición, archivar/restaurar, vincular/desvincular, exportar);
  commit del test hecho.

### - [ ] T34: Implementar `ClientPolicy`

- **Tipo**: impl
- **Cubre**: — (autorización transversal)
- **Depende de**: T33
- **Hecho cuando**: el test de T33 pasa y la suite completa queda verde.

### - [ ] T35: Escribir test de búsqueda, filtros, orden y paginado del listado

- **Tipo**: test
- **Cubre**: RF-49, RF-50, RF-51, RF-52, RF-53, RF-54, RF-55
- **Depende de**: T34
- **Hecho cuando**: el test falla y cubre: columnas del listado, archivados ocultos por defecto y
  visibles con el filtro explícito, búsqueda por nombre para mostrar/documento/teléfono/email de
  contacto sin distinguir mayúsculas, filtro por estado y por tipo de persona, orden por nombre
  para mostrar y por fecha de alta, y paginado; commit del test hecho.

### - [ ] T36: Implementar el scope `Client::search()` y la tabla de `ClientResource`

- **Tipo**: ui
- **Cubre**: RF-49, RF-50, RF-51, RF-52, RF-53, RF-54, RF-55
- **Depende de**: T35
- **Hecho cuando**: el test de T35 pasa, la tabla del recurso Filament refleja todos los casos y
  la suite completa queda verde.

### - [ ] T37: Crear la ficha del cliente con identificación, domicilio y contactos

- **Tipo**: ui
- **Cubre**: RF-45
- **Depende de**: T36
- **Hecho cuando**: un `admin` o `staff` abre la ficha de un cliente desde `ClientResource` y ve su
  identificación, domicilio, contactos y estado.

### - [ ] T38: Agregar relation manager de contactos a la ficha

- **Tipo**: ui
- **Cubre**: RF-13, RF-17, RF-18, RF-19
- **Depende de**: T37
- **Hecho cuando**: desde la ficha se pueden agregar, editar, quitar contactos y cambiar cuál es
  el principal, todo delegando en las Actions de la fase 4.

### - [ ] T39: Agregar relation manager de historial de solo lectura

- **Tipo**: ui
- **Cubre**: RF-37
- **Depende de**: T38
- **Hecho cuando**: la ficha muestra el historial del cliente en orden cronológico, sin ninguna
  acción de edición ni de borrado disponible.

### - [ ] T40: Agregar sección de vínculo de cuentas y acciones de archivar/restaurar

- **Tipo**: ui
- **Cubre**: RF-1, RF-7, RF-25, RF-28, RF-32, RF-38, RF-41
- **Depende de**: T39
- **Hecho cuando**: desde la ficha un `admin` o `staff` puede vincular/desvincular cuentas y
  archivar/restaurar el cliente, delegando en las Actions correspondientes.

### - [ ] T41: Escribir test y agregar `ExportClientListAction` en CSV

- **Tipo**: test
- **Cubre**: RF-56, RNF-6
- **Depende de**: T40
- **Hecho cuando**: el test falla, comprueba que el CSV exportado respeta la búsqueda, los filtros
  y el orden aplicados al listado, y luego pasa con `ExportClientListAction` implementado con
  `fputcsv` en streaming; suite completa verde; commit del test hecho antes del de implementación.

---

## Fase 8: Portal de clientes

### - [ ] T42: Escribir test de acceso a la ficha propia y bloqueo por archivado o sin vínculo

- **Tipo**: test
- **Cubre**: RF-57, RF-58, RF-62
- **Depende de**: T41
- **Hecho cuando**: el test falla y cubre: una cuenta vinculada ve su propia ficha, un cliente
  archivado bloquea el acceso de sus cuentas vinculadas, y una cuenta con rol `cliente` no puede
  ver la ficha de un cliente distinto al que está vinculada; commit del test hecho.

### - [ ] T43: Implementar `ClientPortalPolicy`

- **Tipo**: impl
- **Cubre**: RF-57, RF-58, RF-62
- **Depende de**: T42
- **Hecho cuando**: el test de T42 pasa y la suite completa queda verde.

### - [ ] T44: Escribir test de autogestión de domicilio y contactos desde el portal

- **Tipo**: test
- **Cubre**: RF-59, RF-60, RF-61
- **Depende de**: T43
- **Hecho cuando**: el test falla y cubre: una cuenta vinculada edita el domicilio y los contactos
  de su cliente, no puede editar identificación/estado/archivado, y cada edición propia queda
  registrada con esa cuenta como autor; commit del test hecho.

### - [ ] T45: Crear `ClientProfilePage` en el panel de clientes

- **Tipo**: ui
- **Cubre**: RF-43, RF-59, RF-60, RF-61
- **Depende de**: T44
- **Hecho cuando**: el test de T44 pasa, la cuenta con rol `cliente` sin vínculo ve el mensaje de
  acceso no habilitado (RF-43), la página edita domicilio y contactos delegando en las Actions de
  las fases 3 y 4, y la suite completa queda verde.

---

## Mapa RF → tareas

| RF | Tareas |
|---|---|
| RF-1 | T7, T8, T40 |
| RF-2 | T2, T6, T7, T8 |
| RF-3 | T7, T8 |
| RF-4 | T7, T8 |
| RF-5 | T7, T8 |
| RF-6 | T11, T12 |
| RF-7 | T13, T14, T40 |
| RF-8 | T13, T14 |
| RF-9 | T15, T16 |
| RF-10 | T15, T16 |
| RF-11 | T15, T16 |
| RF-12 | T15, T16 |
| RF-13 | T17, T18, T38 |
| RF-14 | T17, T18 |
| RF-15 | T17, T18 |
| RF-16 | T17, T18 |
| RF-17 | T19, T20, T38 |
| RF-18 | T21, T22, T38 |
| RF-19 | T19, T20, T38 |
| RF-20 | T19, T20 |
| RF-21 | T23, T24 |
| RF-22 | T23, T24 |
| RF-23 | T2, T6 |
| RF-24 | T2, T7, T8 |
| RF-25 | T25, T26, T40 |
| RF-26 | T25, T26 |
| RF-27 | T25, T26 |
| RF-28 | T27, T28, T40 |
| RF-29 | T27, T28 |
| RF-30 | T27, T28 |
| RF-31 | T27, T28 |
| RF-32 | T27, T28, T40 |
| RF-33 | T27, T28 |
| RF-34 | T27, T28 |
| RF-35 | T4, T7, T8 |
| RF-36 | T13, T14, T15, T16, T17, T18, T19, T20, T21, T22, T25, T26, T27, T28 |
| RF-37 | T39 |
| RF-38 | T5, T29, T30, T40 |
| RF-39 | T29, T30 |
| RF-40 | T29, T30 |
| RF-41 | T31, T32, T40 |
| RF-42 | T31, T32 |
| RF-43 | T45 |
| RF-44 | T29, T30, T31, T32 |
| RF-45 | T37 |
| RF-46 | *diferido — cubierto por T45 de `002-presupuestos/tasks.md`* |
| RF-47 | *diferido — sin spec de Contratos* |
| RF-48 | *diferido — sin spec de Pagos* |
| RF-49 | T35, T36 |
| RF-50 | T35, T36 |
| RF-51 | T35, T36 |
| RF-52 | T35, T36 |
| RF-53 | T35, T36 |
| RF-54 | T35, T36 |
| RF-55 | T35, T36 |
| RF-56 | T41 |
| RF-57 | T42, T43 |
| RF-58 | T42, T43 |
| RF-59 | T44, T45 |
| RF-60 | T44, T45 |
| RF-61 | T44, T45 |
| RF-62 | T42, T43 |
| RNF-1 | T13, T14 |
| RNF-2 | T9, T10 |
| RNF-3 | T9, T10 |
| RNF-4 | T17, T18 |
| RNF-5 | *sin test automatizado — ver "Cambios en el plan detectados"* |
| RNF-6 | T41 |
| RNF-7 | *sin test automatizado — ver "Cambios en el plan detectados"* |

## Cambios en el plan detectados al descomponer

- **RF-46, RF-47 y RF-48 no son cubribles desde esta spec**: piden mostrar secciones de
  Presupuestos, Contratos y Pagos en la ficha del cliente "donde el módulo esté implementado", y
  ninguno existe todavía en este orden de trabajo. RF-46 se resuelve con una tarea del `tasks.md`
  de `002-presupuestos` (que sí puede tocar `ClientResource` una vez existan los presupuestos);
  RF-47 y RF-48 quedan sin tarea hasta que existan las specs de Contratos y de Pagos. El validador
  de la spec 003 los va a marcar como pendientes hasta entonces: es esperado, no un error de esta
  descomposición.
- **RF-42 depende de un mecanismo de corte de sesión que define la spec `001`** (middleware
  `EnsureAccountIsActive`, T30 del `tasks.md` de la 001). El plan de la 003 no aclara si
  `UnlinkAccountFromClientAction` reutiliza ese mismo mecanismo o necesita uno propio; T32 asume
  que lo reutiliza. Si el plan de la 001 cambia esa pieza, T31/T32 se ajustan.
- **RNF-5 (listado en <2s con 5.000 clientes) y RNF-7 (historial sin purga) no tienen una tarea de
  test dedicada**: RNF-5 exigiría un seeder de volumen y una medición de tiempo que el plan no
  define; se deja como verificación manual antes de cerrar la spec, no como test automatizado.
  RNF-7 es la ausencia deliberada de una tarea de purga (a diferencia de `access_logs` en la spec
  `001`): no hay nada que testear salvo que nunca se agregue un comando de limpieza.
- **`lang/es/clients.php` no tiene una tarea propia**: sus claves se reparten entre T8, T14, T16,
  T18, T20, T22, T26, T28, T30, T32, T36, T43, T45 y toda otra tarea de `impl` que produzca un
  mensaje visible, siguiendo el mismo patrón que la spec `001`.
