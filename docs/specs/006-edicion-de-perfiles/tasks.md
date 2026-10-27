# 006 — Tareas: Edición de perfiles

| | |
|---|---|
| **Spec** | docs/specs/006-edicion-de-perfiles/spec.md |
| **Plan** | docs/specs/006-edicion-de-perfiles/plan.md |
| **Estado** | En curso |
| **Fecha** | 2026-10-27 |

## Convenciones

- Checkbox al inicio de cada tarea. Se marca al terminarla, no antes.
- Orden por dependencias: cada tarea depende solo de las anteriores.
- TDD estricto: el test va antes que la implementación, y su commit la precede.
- La suite queda verde después de cada tarea de `impl` o `ui`.
- Cuando el test necesita una clase que todavía no existe, la tarea de test crea la clase vacía (sin lógica) para que el test falle por la aserción y no por la falta de clase.
- Cada tarea de `impl` o `ui` que produce mensajes visibles agrega también sus claves en `lang/es`.
- Los tests se nombran `it('RF-n: ...')` con el RF que cubren (AGENTS.md §6).
- Un test marcado como **caracterización** verifica un comportamiento que ya existe o que quedó cubierto por tareas anteriores: pasa desde el inicio y protege contra regresiones.
- Las tareas están agrupadas en **hitos visibles**. Al cerrar un hito se frena, se corre la suite, Pint y `composer analyse`, se hace push de la rama y se prepara la demo antes de seguir.

## Resumen

- Total: 20 tareas.
- Por tipo: 11 test, 6 impl, 3 ui.
- Cubre: RF-1 a RF-24. La spec no tiene RNF.
- Hitos: 1 (T1 a T12) y 2 (T13 a T20).
- Prerrequisitos: la spec 001 implementada (cuentas, roles, historial de cuentas, enlaces de definición de contraseña, ingreso con Google).

---

## Hito 1: Un admin edita cuentas

**Demo posible al cerrarlo**: desde Configuración → Cuentas, un admin corrige el nombre y el email de otra cuenta; la persona recibe el aviso en los dos correos, entra con el email nuevo y los cambios aparecen en el historial de cuentas.

### - [ ] T1: Escribir test de las etiquetas de los hechos nuevos del historial

- **Tipo**: test
- **Cubre**: RF-20, RF-21, RF-22, RF-24
- **Depende de**: —
- **Hecho cuando**: `pest --filter=AccountHistoryField` corre y falla porque los casos `NameChanged`, `EmailChanged` y `PasswordChanged` del enum no tienen etiqueta en español; commit del test hecho.

### - [ ] T2: Agregar los casos del enum y sus etiquetas

- **Tipo**: impl
- **Cubre**: RF-20, RF-21, RF-22, RF-24
- **Depende de**: T1
- **Hecho cuando**: el test de T1 pasa, las tres claves están en `lang/es/logs.php` y la suite completa queda verde.

### - [ ] T3: Escribir test de `UpdateAccountProfileAction` para el nombre

- **Tipo**: test
- **Cubre**: RF-2, RF-4, RF-20, RF-23
- **Depende de**: T2
- **Hecho cuando**: `pest --filter=UpdateAccountProfileAction` corre y falla por la aserción en los casos de nombre nuevo guardado con asiento (anterior y nuevo, autor y fecha), nombre vacío o de más de 150 caracteres rechazado sin cambios, y guardado sin cambios sin asiento; commit del test hecho.

### - [ ] T4: Implementar `UpdateAccountProfileAction` para el nombre

- **Tipo**: impl
- **Cubre**: RF-2, RF-4, RF-20, RF-23
- **Depende de**: T3
- **Hecho cuando**: el test de T3 pasa y la suite completa queda verde.

### - [ ] T5: Escribir test de `UpdateAccountProfileAction` para el email

- **Tipo**: test
- **Cubre**: RF-12, RF-14, RF-15, RF-16, RF-21
- **Depende de**: T4
- **Hecho cuando**: `pest --filter=UpdateAccountProfileAction` falla en los casos de email nuevo guardado en minúsculas y sin espacios exteriores con su asiento, email vacío o con formato inválido rechazado, email de otra cuenta (incluida una inactiva) rechazado con la cuenta existente en el mensaje, el mismo email de la propia cuenta sin conflicto, y el cambio del email propio rechazado con `CannotChangeOwnEmailException`; commit del test hecho.

### - [ ] T6: Implementar el cambio de email en `UpdateAccountProfileAction`

- **Tipo**: impl
- **Cubre**: RF-12, RF-14, RF-15, RF-16, RF-21
- **Depende de**: T5
- **Hecho cuando**: el test de T5 pasa, `CannotChangeOwnEmailException` tiene su mensaje en `lang/es/auth.php` y la suite completa queda verde.

### - [ ] T7: Escribir test del aviso y de la baja de enlaces al cambiar el email

- **Tipo**: test
- **Cubre**: RF-17, RF-18
- **Depende de**: T6
- **Hecho cuando**: `pest --filter=UpdateAccountProfileAction` falla en los casos de aviso enviado al email anterior y al nuevo, ningún aviso cuando el email no cambia, ningún aviso si la operación se revierte, y enlace de definición de contraseña pendiente del email anterior invalidado; commit del test hecho.

### - [ ] T8: Implementar el aviso y la baja de enlaces

- **Tipo**: impl
- **Cubre**: RF-17, RF-18
- **Depende de**: T7
- **Hecho cuando**: el test de T7 pasa, existen la notificación `AccountEmailChanged` y sus textos en `lang/es/accounts.php`, y la suite completa queda verde.

### - [ ] T9: Escribir test de la acción «Editar» del listado de cuentas

- **Tipo**: test
- **Cubre**: RF-11, RF-12, RF-13, RF-14, RF-15
- **Depende de**: T8
- **Hecho cuando**: `pest --filter=AccountEditAction` corre y falla porque la acción `edit` no existe; cubre admin que edita nombre y email de otra cuenta, acción oculta en la fila propia, staff rechazado por falta de permiso, mensaje de conflicto en el campo email y error de formato; commit del test hecho.

### - [ ] T10: Agregar la acción «Editar» al listado de cuentas

- **Tipo**: ui
- **Cubre**: RF-11, RF-12, RF-13, RF-14, RF-15
- **Depende de**: T9
- **Hecho cuando**: el test de T9 pasa, la acción está en `AccountsTable` con `->authorize('update')`, el docblock de `AccountResource` ya no dice que las cuentas no se editan, los textos están en `lang/es/accounts.php` y la suite completa queda verde.

### - [ ] T11: Escribir test de ingreso y sesión tras el cambio de email (caracterización)

- **Tipo**: test
- **Cubre**: RF-16, RF-19
- **Depende de**: T10
- **Hecho cuando**: `pest --filter=EmailChangeSession` corre y pasa: tras el cambio el ingreso con contraseña y con Google funciona con el email nuevo y no con el anterior, y una sesión abierta de esa cuenta sigue abierta; commit del test hecho.

### - [ ] T12: Escribir test del historial de cuentas con los hechos nuevos (caracterización)

- **Tipo**: test
- **Cubre**: RF-24
- **Depende de**: T10
- **Hecho cuando**: `pest --filter=AccountHistoryResource` corre y pasa: un admin ve en el historial los asientos de nombre, email y contraseña con su etiqueta, y un staff no puede consultarlos; commit del test hecho.

---

## Hito 2: Cada persona edita su perfil

**Demo posible al cerrarlo**: un miembro del staff y una cuenta cliente abren su perfil desde el menú de usuario, cambian su nombre y su contraseña, ven el email sin poder editarlo, y las otras sesiones de esa cuenta quedan cerradas.

### - [ ] T13: Escribir test de `ChangeOwnPasswordAction`

- **Tipo**: test
- **Cubre**: RF-6, RF-7, RF-8, RF-9, RF-22
- **Depende de**: T2
- **Hecho cuando**: `pest --filter=ChangeOwnPasswordAction` corre y falla por la aserción en los casos de contraseña cambiada con la actual correcta y una nueva válida, contraseña actual incorrecta rechazada sin cambios, contraseña nueva de menos de 8 caracteres rechazada, cuenta sin contraseña rechazada con `AccountHasNoPasswordException`, y asiento con autor y fecha sin ningún dato de contraseña; commit del test hecho.

### - [ ] T14: Implementar `ChangeOwnPasswordAction`

- **Tipo**: impl
- **Cubre**: RF-6, RF-7, RF-8, RF-9, RF-22
- **Depende de**: T13
- **Hecho cuando**: el test de T13 pasa, el mensaje de la excepción está en `lang/es/auth.php` y la suite completa queda verde.

### - [ ] T15: Escribir test del corte de las otras sesiones

- **Tipo**: test
- **Cubre**: RF-10
- **Depende de**: T14
- **Hecho cuando**: `pest --filter=PasswordChangeSession` corre y falla porque la segunda sesión de la cuenta sigue abierta tras el cambio; cubre también que la sesión desde la que se cambió sigue abierta; commit del test hecho.

### - [ ] T16: Refrescar el hash de la sesión actual al cambiar la contraseña

- **Tipo**: impl
- **Cubre**: RF-10
- **Depende de**: T15
- **Hecho cuando**: el test de T15 pasa, la clave de sesión se toma de `Filament::getAuthGuard()` y la suite completa queda verde.

### - [ ] T17: Escribir test del perfil propio en el portal de staff

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-9, RF-10
- **Depende de**: T16
- **Hecho cuando**: `pest --filter=StaffProfile` corre y falla porque `/staff/profile` no existe o no es la página propia; cubre nombre y email visibles, email deshabilitado, nombre guardado con asiento, nombre vacío rechazado en el formulario, cambio de contraseña con la actual, errores de contraseña en sus campos, y sección de contraseña oculta con aviso de recuperación en una cuenta sin contraseña; commit del test hecho.

### - [ ] T18: Implementar el perfil propio del portal de staff

- **Tipo**: ui
- **Cubre**: RF-1, RF-2, RF-3, RF-4, RF-5, RF-9, RF-10
- **Depende de**: T17
- **Hecho cuando**: el test de T17 pasa, existen `PortalEditProfile` y `Staff\Pages\Auth\EditProfile`, `StaffPanelProvider` registra `->profile()`, los textos están en `lang/es/accounts.php` y la suite completa queda verde.

### - [ ] T19: Escribir test del perfil propio en el portal de clientes

- **Tipo**: test
- **Cubre**: RF-1, RF-2, RF-3, RF-5, RF-9, RF-10
- **Depende de**: T18
- **Hecho cuando**: `pest --filter=ClientProfile` corre y falla porque `/portal/profile` no existe o no es la página propia; cubre una cuenta cliente que cambia su nombre y su contraseña, email deshabilitado, y que la ficha del cliente de `ClientProfilePage` no se modifica; commit del test hecho.

### - [ ] T20: Implementar el perfil propio del portal de clientes

- **Tipo**: ui
- **Cubre**: RF-1, RF-2, RF-3, RF-5, RF-9, RF-10
- **Depende de**: T19
- **Hecho cuando**: el test de T19 pasa, existe `Client\Pages\Auth\EditProfile`, `ClientPanelProvider` registra `->profile()` y la suite completa queda verde.

---

## Mapa RF → tareas

| RF | Tareas |
|---|---|
| RF-1 | T17, T18, T19, T20 |
| RF-2 | T3, T4, T17, T18, T19, T20 |
| RF-3 | T17, T18, T19, T20 |
| RF-4 | T3, T4, T17, T18 |
| RF-5 | T17, T18, T19, T20 |
| RF-6 | T13, T14 |
| RF-7 | T13, T14 |
| RF-8 | T13, T14 |
| RF-9 | T13, T14, T17, T18, T19, T20 |
| RF-10 | T15, T16, T17, T18, T19, T20 |
| RF-11 | T9, T10 |
| RF-12 | T5, T6, T9, T10 |
| RF-13 | T9, T10 |
| RF-14 | T5, T6, T9, T10 |
| RF-15 | T5, T6, T9, T10 |
| RF-16 | T5, T6, T11 |
| RF-17 | T7, T8 |
| RF-18 | T7, T8 |
| RF-19 | T11 |
| RF-20 | T1, T2, T3, T4 |
| RF-21 | T1, T2, T5, T6 |
| RF-22 | T1, T2, T13, T14 |
| RF-23 | T3, T4 |
| RF-24 | T1, T2, T12 |

## Cambios en el plan detectados al descomponer

- El plan §5 y §6 mencionan «una excepción de dominio» para la cuenta sin contraseña pero no la nombran ni la listan en el inventario de excepciones; las tareas la llaman `AccountHasNoPasswordException`.
- El plan §9 no menciona que RF-16 y RF-19 se cubren con tests de caracterización (T11) y no con código nuevo: el ingreso ya busca por email y `EnsureAccountIsActive` ya lee la cuenta por id.
- El plan D-2 dice que el perfil propio llama a `UpdateAccountProfileAction` «solo con el nombre»; el plan no aclara que el campo email no se envía en ese caso, y T17 lo verifica.
