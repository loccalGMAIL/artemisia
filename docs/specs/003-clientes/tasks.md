# 003 — Tareas: Clientes

| | |
|---|---|
| **Spec** | docs/specs/003-clientes/spec.md |
| **Plan** | docs/specs/003-clientes/plan.md |
| **Estado** | En curso |
| **Fecha** | 2026-09-21 |

## Convenciones

- Checkbox al inicio de cada tarea. Se marca al terminarla, no antes.
- Orden por dependencias: cada tarea depende solo de las anteriores.
- TDD estricto: el test va antes que la implementación.
- La suite queda verde después de cada tarea de `impl`.
- Cada tarea de `impl` o `ui` que produce texto visible crea también sus claves en `lang/es/clients.php`.
- No hay tarea de bootstrap Laravel: se asume `001-acceso-roles-y-paneles` implementada.
- Esta spec respeta el orden canónico: `001` → `003` → `002` → `004`.

## Resumen

- Total: 34 tareas.
- Por tipo: 15 test, 12 impl, 2 migration, 5 ui.
- Cubre: RF-1 a RF-62, RNF-1 a RNF-7.

---

## Fase 1: Esquema, modelos y datos de referencia

### - [x] T1: Crear migraciones de provincias y clientes

- **Tipo**: migration
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-9, RF-10, RF-11, RF-23, RF-24, RF-28, RF-31, RF-34, RNF-1, RNF-2, RNF-3, RNF-5
- **Depende de**: —
- **Hecho cuando**: `php artisan migrate:fresh` crea `provinces` y `clients` con las columnas, FK, índices, `UNIQUE(document)` y `deleted_at` definidos en el plan §4.

### - [x] T2: Crear migraciones de contactos, historial y vínculo de usuario

- **Tipo**: migration
- **Cubre**: RF-13, RF-15, RF-16, RF-17, RF-35, RF-36, RF-37, RF-38, RF-39, RF-40, RF-44, RNF-4, RNF-7
- **Depende de**: T1
- **Hecho cuando**: `client_contacts`, `client_histories` y `users.client_id` existen con sus FK, índices y la restricción que impide más de un contacto principal por cliente.

### - [x] T3: Crear enums, modelos, factories y seeder de provincias

- **Tipo**: impl
- **Cubre**: RF-2, RF-5, RF-21, RF-22, RF-23, RF-37, RNF-7
- **Depende de**: T2
- **Hecho cuando**: existen `ClientType`, `ClientStatus`, `ClientHistoryField`, los modelos y factories del plan, `ProvinceSeeder` carga las 24 provincias argentinas, y un test de humo verifica relaciones, casts y accessors básicos.

---

## Fase 2: Identificación y alta

### - [x] T4: Escribir test de alta e identificación de clientes

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-6, RF-24, RF-35, RNF-1, RNF-2, RNF-3
- **Depende de**: T3
- **Hecho cuando**: el test falla y comprueba alta de persona física, alta de persona jurídica, nombre para mostrar calculado, cliente activo por defecto, historial de alta, normalización de documento y rechazo de DNI, CUIT o documento duplicado incluyendo archivados; commit del test hecho.

### - [x] T5: Implementar `CreateClientAction`

- **Tipo**: impl
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-6, RF-24, RF-35, RNF-1, RNF-2, RNF-3
- **Depende de**: T4
- **Hecho cuando**: el test de T4 pasa, los mensajes visibles salen de `lang/es/clients.php` y la suite completa queda verde.

### - [x] T6: Escribir test de edición de identificación

- **Tipo**: test
- **Cubre**: RF-7, RF-8, RF-36, RNF-1, RNF-2, RNF-3
- **Depende de**: T5
- **Hecho cuando**: el test falla y verifica edición válida de identificación, rechazo al cambiar `person_type`, validación de documento, unicidad contra clientes activos o archivados, y asiento de historial con valor anterior y nuevo; commit del test hecho.

### - [x] T7: Implementar `UpdateClientIdentificationAction`

- **Tipo**: impl
- **Cubre**: RF-7, RF-8, RF-36, RNF-1, RNF-2, RNF-3
- **Depende de**: T6
- **Hecho cuando**: el test de T6 pasa y la suite completa queda verde.

---

## Fase 3: Domicilio y contactos

### - [x] T8: Escribir test de domicilio opcional y editable

- **Tipo**: test
- **Cubre**: RF-9, RF-10, RF-11, RF-12, RF-36
- **Depende de**: T7
- **Hecho cuando**: el test falla y cubre alta sin domicilio, carga de un único domicilio, modificación posterior y asiento de historial con snapshots; commit del test hecho.

### - [ ] T9: Implementar `UpdateClientAddressAction`

- **Tipo**: impl
- **Cubre**: RF-9, RF-10, RF-11, RF-12, RF-36
- **Depende de**: T8
- **Hecho cuando**: el test de T8 pasa y la suite completa queda verde.

### - [ ] T10: Escribir test de reglas de contactos

- **Tipo**: test
- **Cubre**: RF-13, RF-14, RF-15, RF-16, RF-17, RF-18, RF-19, RF-20, RF-21, RF-22, RF-36, RNF-4
- **Depende de**: T9
- **Hecho cuando**: el test falla y cubre alta sin contactos, teléfono-o-email obligatorio, tope de 10 contactos, primer contacto principal, cambio de principal, edición, eliminación, reasignación al quitar el principal y cálculo de teléfono de contacto; commit del test hecho.

### - [ ] T11: Implementar actions de contactos y teléfono de contacto

- **Tipo**: impl
- **Cubre**: RF-13, RF-14, RF-15, RF-16, RF-17, RF-18, RF-19, RF-20, RF-21, RF-22, RF-36, RNF-4
- **Depende de**: T10
- **Hecho cuando**: el test de T10 pasa con `AddClientContactAction`, `UpdateClientContactAction`, `RemoveClientContactAction`, `SetPrimaryContactAction` y el accessor de teléfono, y la suite completa queda verde.

---

## Fase 4: Estado, archivado e historial

### - [ ] T12: Escribir test de estado, archivado y disponibilidad

- **Tipo**: test
- **Cubre**: RF-23, RF-25, RF-26, RF-27, RF-28, RF-29, RF-30, RF-31, RF-32, RF-33, RF-34, RF-36
- **Depende de**: T11
- **Hecho cuando**: el test falla y verifica activar, desactivar, archivar como SoftDelete e inactivo, restaurar como inactivo, ficha e historial consultables, clientes inactivos o archivados fuera de `availableForBudgets()` y ausencia de hard delete; commit del test hecho.

### - [ ] T13: Implementar actions de estado, archivado y restauración

- **Tipo**: impl
- **Cubre**: RF-23, RF-25, RF-26, RF-27, RF-28, RF-29, RF-30, RF-31, RF-32, RF-33, RF-34, RF-36
- **Depende de**: T12
- **Hecho cuando**: el test de T12 pasa con `ActivateClientAction`, `DeactivateClientAction`, `ArchiveClientAction`, `RestoreClientAction` y `Client::availableForBudgets()`, y la suite completa queda verde.

### - [ ] T14: Escribir test de historial cronológico e inmutable

- **Tipo**: test
- **Cubre**: RF-35, RF-36, RF-37, RF-44, RNF-7
- **Depende de**: T13
- **Hecho cuando**: el test falla y comprueba orden cronológico, autor, fecha, campo afectado, valores anterior/nuevo, conservación indefinida y rechazo de edición o borrado de asientos; commit del test hecho.

### - [ ] T15: Implementar historial append-only

- **Tipo**: impl
- **Cubre**: RF-35, RF-36, RF-37, RF-44, RNF-7
- **Depende de**: T14
- **Hecho cuando**: el test de T14 pasa, `ClientHistory` no expone actualización ni borrado operativo y la suite completa queda verde.

---

## Fase 5: Vínculo cuenta-cliente y autorización

### - [ ] T16: Escribir test de vinculación y desvinculación de cuentas

- **Tipo**: test
- **Cubre**: RF-38, RF-39, RF-40, RF-41, RF-42, RF-44
- **Depende de**: T15
- **Hecho cuando**: el test falla y cubre vincular cuenta con rol `client`, varias cuentas al mismo cliente, rechazo de una cuenta ya vinculada a otro cliente, desvinculación, corte de sesión en la siguiente solicitud y asiento de historial; commit del test hecho.

### - [ ] T17: Implementar `LinkAccountToClientAction` y `UnlinkAccountFromClientAction`

- **Tipo**: impl
- **Cubre**: RF-38, RF-39, RF-40, RF-41, RF-42, RF-44
- **Depende de**: T16
- **Hecho cuando**: el test de T16 pasa, la desvinculación se integra con el mecanismo de corte de sesión de la spec `001` y la suite completa queda verde.

### - [ ] T18: Escribir test de policies de staff y portal

- **Tipo**: test
- **Cubre**: RF-43, RF-57, RF-58, RF-59, RF-60, RF-62
- **Depende de**: T17
- **Hecho cuando**: el test falla y verifica permisos completos para `admin` y `staff`, cuenta `client` limitada a su propio cliente, cuenta sin vínculo con mensaje de acceso no habilitado, cliente archivado bloqueado y rechazo al intentar operar sobre otro cliente; commit del test hecho.

### - [ ] T19: Implementar `ClientPolicy` y autorización del portal

- **Tipo**: impl
- **Cubre**: RF-43, RF-57, RF-58, RF-59, RF-60, RF-62
- **Depende de**: T18
- **Hecho cuando**: el test de T18 pasa, los mensajes visibles salen de `lang/es/clients.php` y la suite completa queda verde.

---

## Fase 6: Panel de staff

### - [ ] T20: Escribir test de alta, edición y ficha en staff

- **Tipo**: test
- **Cubre**: RF-1, RF-7, RF-9, RF-12, RF-13, RF-19, RF-25, RF-28, RF-32, RF-37, RF-38, RF-41, RF-45
- **Depende de**: T19
- **Hecho cuando**: el test falla y cubre creación, edición, domicilio, contactos, estado, archivado/restauración, vínculos de cuentas y ficha con identificación, domicilio, contactos, estado e historial; commit del test hecho.

### - [ ] T21: Crear `ClientResource` para gestión y ficha de clientes

- **Tipo**: ui
- **Cubre**: RF-1, RF-7, RF-9, RF-12, RF-13, RF-19, RF-25, RF-28, RF-32, RF-37, RF-38, RF-41, RF-45
- **Depende de**: T20
- **Hecho cuando**: el test de T20 pasa, el recurso delega reglas en Actions/Policies y la suite completa queda verde.

### - [ ] T22: Escribir test del listado de clientes

- **Tipo**: test
- **Cubre**: RF-49, RF-50, RF-51, RF-52, RF-53, RF-54, RF-55
- **Depende de**: T21
- **Hecho cuando**: el test falla y verifica columnas, exclusión de archivados por defecto, filtro explícito de archivados, búsqueda case-insensitive por nombre/documento/teléfono/email, filtros por estado y tipo, orden por nombre y fecha, y paginado; commit del test hecho.

### - [ ] T23: Implementar tabla, scopes y filtros del listado

- **Tipo**: ui
- **Cubre**: RF-49, RF-50, RF-51, RF-52, RF-53, RF-54, RF-55
- **Depende de**: T22
- **Hecho cuando**: el test de T22 pasa, la lógica de búsqueda vive en scopes del modelo o query builders reutilizables y la suite completa queda verde.

### - [ ] T24: Escribir test de exportación del listado

- **Tipo**: test
- **Cubre**: RF-56, RNF-6
- **Depende de**: T23
- **Hecho cuando**: el test falla y comprueba que la exportación CSV respeta búsqueda, filtros y orden aplicados, incluye las columnas esperadas y se genera en streaming; commit del test hecho.

### - [ ] T25: Implementar `ExportClientListAction`

- **Tipo**: impl
- **Cubre**: RF-56, RNF-6
- **Depende de**: T24
- **Hecho cuando**: el test de T24 pasa sin agregar dependencias externas y la suite completa queda verde.

---

## Fase 7: Portal de clientes

### - [ ] T26: Escribir test de acceso a ficha propia en portal

- **Tipo**: test
- **Cubre**: RF-43, RF-57, RF-58, RF-62
- **Depende de**: T25
- **Hecho cuando**: el test falla y cubre cuenta vinculada que ve su ficha, cuenta sin vínculo con mensaje de acceso no habilitado, cliente archivado bloqueado e intento de acceder a otro cliente rechazado; commit del test hecho.

### - [ ] T27: Crear `ClientProfilePage` de solo ficha propia

- **Tipo**: ui
- **Cubre**: RF-43, RF-57, RF-58, RF-62
- **Depende de**: T26
- **Hecho cuando**: el test de T26 pasa, la página no recibe `client_id` arbitrario desde la URL y la suite completa queda verde.

### - [ ] T28: Escribir test de autogestión del cliente

- **Tipo**: test
- **Cubre**: RF-59, RF-60, RF-61
- **Depende de**: T27
- **Hecho cuando**: el test falla y verifica que la cuenta vinculada edita domicilio y contactos propios, no puede editar identificación, estado ni archivado, y cada cambio queda registrado con esa cuenta como autor; commit del test hecho.

### - [ ] T29: Implementar formularios de autogestión

- **Tipo**: ui
- **Cubre**: RF-59, RF-60, RF-61
- **Depende de**: T28
- **Hecho cuando**: el test de T28 pasa, los formularios reutilizan las Actions existentes y la suite completa queda verde.

---

## Fase 8: Extensiones comerciales y rendimiento

### - [ ] T30: Reservar secciones comerciales condicionales en la ficha

- **Tipo**: ui
- **Cubre**: RF-46, RF-47, RF-48
- **Depende de**: T29
- **Hecho cuando**: la ficha del cliente deja puntos de extensión documentados para presupuestos, contratos y pagos, sin consultar tablas todavía inexistentes ni romper la suite actual.

### - [ ] T31: Escribir test de rendimiento del listado con 5.000 clientes

- **Tipo**: test
- **Cubre**: RNF-5
- **Depende de**: T30
- **Hecho cuando**: el test falla si el listado principal con búsqueda, filtros y orden sobre 5.000 clientes supera 2 segundos en entorno de test; commit del test hecho.

### - [ ] T32: Ajustar consultas e índices del listado

- **Tipo**: impl
- **Cubre**: RNF-5
- **Depende de**: T31
- **Hecho cuando**: el test de T31 pasa sin agregar dependencias y la suite completa queda verde.

### - [ ] T33: Escribir test de rendimiento de exportación con 5.000 clientes

- **Tipo**: test
- **Cubre**: RNF-6
- **Depende de**: T32
- **Hecho cuando**: el test falla si la exportación CSV filtrada de 5.000 clientes supera 5 segundos o carga todos los resultados en memoria innecesariamente; commit del test hecho.

### - [ ] T34: Ajustar streaming de exportación

- **Tipo**: impl
- **Cubre**: RNF-6
- **Depende de**: T33
- **Hecho cuando**: el test de T33 pasa, la exportación itera por chunks o cursor y la suite completa queda verde.

---

## Mapa RF/RNF → tareas

| Requisito | Tareas |
|---|---|
| RF-1 | T1, T4, T5, T20, T21 |
| RF-2 | T1, T3, T4, T5 |
| RF-3 | T1, T4, T5 |
| RF-4 | T1, T4, T5 |
| RF-5 | T3, T4, T5 |
| RF-6 | T4, T5 |
| RF-7 | T6, T7, T20, T21 |
| RF-8 | T6, T7 |
| RF-9 | T1, T8, T9, T20, T21 |
| RF-10 | T1, T8, T9 |
| RF-11 | T1, T8, T9 |
| RF-12 | T8, T9, T20, T21 |
| RF-13 | T2, T10, T11, T20, T21 |
| RF-14 | T10, T11 |
| RF-15 | T2, T10, T11 |
| RF-16 | T2, T10, T11 |
| RF-17 | T2, T10, T11 |
| RF-18 | T10, T11 |
| RF-19 | T10, T11, T20, T21 |
| RF-20 | T10, T11 |
| RF-21 | T3, T10, T11 |
| RF-22 | T3, T10, T11 |
| RF-23 | T1, T3, T12, T13 |
| RF-24 | T1, T4, T5 |
| RF-25 | T12, T13, T20, T21 |
| RF-26 | T12, T13 |
| RF-27 | T12, T13 |
| RF-28 | T1, T12, T13, T20, T21 |
| RF-29 | T12, T13 |
| RF-30 | T12, T13 |
| RF-31 | T1, T12, T13 |
| RF-32 | T12, T13, T20, T21 |
| RF-33 | T12, T13 |
| RF-34 | T1, T12, T13 |
| RF-35 | T2, T4, T5, T14, T15 |
| RF-36 | T2, T6, T7, T8, T9, T10, T11, T12, T13, T14, T15 |
| RF-37 | T2, T3, T14, T15, T20, T21 |
| RF-38 | T2, T16, T17, T20, T21 |
| RF-39 | T2, T16, T17 |
| RF-40 | T2, T16, T17 |
| RF-41 | T16, T17, T20, T21 |
| RF-42 | T16, T17 |
| RF-43 | T18, T19, T26, T27 |
| RF-44 | T2, T14, T15, T16, T17 |
| RF-45 | T20, T21 |
| RF-46 | T30 |
| RF-47 | T30 |
| RF-48 | T30 |
| RF-49 | T22, T23 |
| RF-50 | T22, T23 |
| RF-51 | T22, T23 |
| RF-52 | T22, T23 |
| RF-53 | T22, T23 |
| RF-54 | T22, T23 |
| RF-55 | T22, T23 |
| RF-56 | T24, T25 |
| RF-57 | T18, T19, T26, T27 |
| RF-58 | T18, T19, T26, T27 |
| RF-59 | T18, T19, T28, T29 |
| RF-60 | T18, T19, T28, T29 |
| RF-61 | T28, T29 |
| RF-62 | T18, T19, T26, T27 |
| RNF-1 | T1, T4, T5, T6, T7 |
| RNF-2 | T1, T4, T5, T6, T7 |
| RNF-3 | T1, T4, T5, T6, T7 |
| RNF-4 | T2, T10, T11 |
| RNF-5 | T1, T31, T32 |
| RNF-6 | T24, T25, T33, T34 |
| RNF-7 | T2, T3, T14, T15 |

## Cambios en el plan detectados al descomponer

- **El corte de sesión al desvincular cuenta depende de `001`**, pero el plan no nombra el contrato técnico concreto del mecanismo de invalidación. Antes de implementar T17 conviene verificar el middleware o campo real que dejó `001`.
- **RF-46 a RF-48 son condicionales a módulos futuros**, por lo que T30 solo reserva puntos de extensión en la ficha. El contenido real debe quedar para las specs de presupuestos, contratos y pagos.
- **La restricción `primary_marker` es específica de MySQL**, y el plan define la idea pero no la expresión exacta de migración Laravel/MySQL. T2 debe precisar esa sintaxis antes de escribir la migración.
- **Los tests de rendimiento pueden ser sensibles al entorno local**, especialmente en Windows/XAMPP. T31 y T33 deben fijar datos, conexión y medición de forma estable para evitar falsos negativos.
