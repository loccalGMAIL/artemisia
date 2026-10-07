# 001 — Tareas: Acceso, roles y paneles

| | |
|---|---|
| **Spec** | docs/specs/001-acceso-roles-y-paneles/spec.md |
| **Plan** | docs/specs/001-acceso-roles-y-paneles/plan.md |
| **Estado** | En curso |
| **Fecha** | 2026-09-10 |

## Convenciones

- Checkbox al inicio de cada tarea. Se marca al terminarla, no antes.
- Orden por dependencias: cada tarea depende solo de las anteriores.
- TDD estricto: el test va antes que la implementación.
- La suite queda verde después de cada tarea de `impl`.
- Cada tarea de `impl` que produce un mensaje visible crea también sus claves en `lang/es/auth.php`
  (constitución, principio 6). No hay una tarea separada de traducciones.
- El bootstrap Laravel inicial es un prerequisito fundacional fuera de esta spec, segun
  `docs/adr/0001-bootstrap-laravel-fuera-de-specs.md`.
- **T40 es la unica tarea funcional que no cubre ningun RF**: es un paso de instalacion previsto
  por la spec, no comportamiento de producto visible. Ver "Cambios en el plan detectados".

## Resumen

- Total: 42 tareas funcionales, mas 1 prerequisito externo.
- Por tipo: 16 test, 18 impl, 4 migration, 4 ui.
- Cubre: RF-1 a RF-39, RNF-1 a RNF-6.

---

## Prerequisito externo: Puesta en marcha del proyecto

### - [x] P0: Instalar el proyecto Laravel con el stack de AGENTS.md

- **Tipo**: prerequisito externo
- **Cubre**: — (fuera de spec; ver `docs/adr/0001-bootstrap-laravel-fuera-de-specs.md`)
- **Depende de**: —
- **Hecho cuando**: `composer install` y `npm install` corren sin errores, y `php artisan --version`
  reporta Laravel 13 con Filament 5, `spatie/laravel-permission`, `laravel/socialite`, Pest y Pint
  instalados.

---

## Fase 0: Configuracion base funcional

### - [x] T2: Configurar entorno y umbrales de sesión y de enlaces

- **Tipo**: impl
- **Cubre**: RNF-3, RNF-4
- **Depende de**: P0
- **Hecho cuando**: `.env.example` declara `DB_DATABASE=artemisia` y las tres variables de Google,
  `CACHE_STORE=database`, `config/session.php` tiene `lifetime => 120`, `config/auth.php` tiene
  `expire => 1440` para el broker de contraseñas, y las migraciones de cache de Laravel quedan
  disponibles para que el `RateLimiter` use persistencia en base.

---

## Fase 1: Esquema y modelos

### - [x] T3: Crear migración de `users`

- **Tipo**: migration
- **Cubre**: RF-4, RF-34
- **Depende de**: T2
- **Hecho cuando**: `php artisan migrate:fresh` corre sin errores y la tabla `users` existe con
  `name`, `email` (único), `password` nullable e `is_active`, sin `deleted_at`, según el plan §4.

### - [x] T4: Publicar migraciones de permisos y sembrar los tres roles

- **Tipo**: migration
- **Cubre**: RF-3
- **Depende de**: T3
- **Hecho cuando**: `php artisan migrate:fresh --seed` crea las tablas del paquete y `RoleSeeder`
  deja exactamente los roles `admin`, `staff` y `client`.

### - [x] T5: Crear migración de `account_histories`

- **Tipo**: migration
- **Cubre**: RF-35
- **Depende de**: T3
- **Hecho cuando**: la tabla existe con `field`, `old_value`, `new_value`, `author_id`, `created_at`
  y el índice `(user_id, created_at)`, sin columna `updated_at`.

### - [x] T6: Crear migración de `access_logs`

- **Tipo**: migration
- **Cubre**: RF-36, RF-37
- **Depende de**: T3
- **Hecho cuando**: la tabla existe con `user_id` nullable, `email_used`, `portal`, `method`,
  `outcome`, `rejection_reason` y los índices sobre `created_at` y `email_used`.

### - [x] T7: Crear enums y modelos con sus relaciones

- **Tipo**: impl
- **Cubre**: RF-3, RF-35, RF-36, RF-37
- **Depende de**: T4, T5, T6
- **Hecho cuando**: existen `AccountHistoryField`, `AccessPortal`, `AccessMethod` y `AccessOutcome`
  como backed enums, `User` usa `HasRoles`, y `AccountHistory` y `AccessLog` resuelven sus
  relaciones en un test de humo verde.

---

## Fase 2: Gestión de cuentas

### - [x] T8: Escribir test de alta de cuenta con rol único

- **Tipo**: test
- **Cubre**: RF-2, RF-3, RF-5, RF-35
- **Depende de**: T7
- **Hecho cuando**: `pest --filter=CreateAccount` falla porque `CreateAccountAction` no existe
  todavía, y el test verifica que la cuenta nace activa, con un solo rol, con enlace enviado y con
  asiento en `account_histories`; commit del test hecho.

### - [x] T9: Implementar `CreateAccountAction`

- **Tipo**: impl
- **Cubre**: RF-2, RF-3, RF-5, RF-35
- **Depende de**: T8
- **Hecho cuando**: el test de T8 pasa y la suite completa queda verde.

### - [x] T10: Escribir test de email duplicado incluyendo cuentas inactivas

- **Tipo**: test
- **Cubre**: RF-4
- **Depende de**: T9
- **Hecho cuando**: el test falla y comprueba que un email repetido (con distinta capitalización y
  con espacios exteriores, y contra una cuenta inactiva) no crea la cuenta **y** que se informa el
  conflicto indicando la cuenta existente; commit del test hecho.

### - [x] T11: Implementar la validación de unicidad de email

- **Tipo**: impl
- **Cubre**: RF-4
- **Depende de**: T10
- **Hecho cuando**: el test de T10 pasa y la suite completa queda verde.

### - [x] T12: Escribir test de cambio de rol, con sus dos bloqueos

- **Tipo**: test
- **Cubre**: RF-6, RF-7, RF-10, RF-11
- **Depende de**: T11
- **Hecho cuando**: el test falla y cubre los tres casos: cambio válido con asiento e impacto en la
  siguiente solicitud, rechazo del cambio sobre la cuenta propia, y rechazo del cambio que dejaría
  al sistema sin ningún `admin` activo; commit del test hecho.

### - [x] T13: Implementar `ChangeAccountRoleAction`

- **Tipo**: impl
- **Cubre**: RF-6, RF-7, RF-10, RF-11
- **Depende de**: T12
- **Hecho cuando**: el test de T12 pasa y la suite completa queda verde.

### - [x] T14: Escribir test de activación y desactivación de cuentas

- **Tipo**: test
- **Cubre**: RF-10, RF-11, RF-31, RF-32, RF-35
- **Depende de**: T13
- **Hecho cuando**: el test falla y cubre activar, desactivar, el rechazo de la autodesactivación,
  el rechazo de la desactivación del último `admin` activo, y que al desactivar se invalide un
  enlace de definición vigente sin usar; commit del test hecho.

### - [x] T15: Implementar `ActivateAccountAction` y `DeactivateAccountAction`

- **Tipo**: impl
- **Cubre**: RF-10, RF-11, RF-31, RF-32, RF-35
- **Depende de**: T14
- **Hecho cuando**: el test de T14 pasa y la suite completa queda verde.

---

## Fase 3: Autenticación

### - [x] T16: Escribir test del orden de evaluación del ingreso

- **Tipo**: test
- **Cubre**: RF-17, RF-18, RF-19, RF-20, RF-21, RF-22
- **Depende de**: T15
- **Hecho cuando**: el test falla y cubre las cuatro ramas en orden: credenciales que no coinciden
  (mensaje genérico, sin revelar si el email existe), cuenta inactiva con credenciales correctas,
  rol que no corresponde al portal, e ingreso válido; incluye el caso de contraseña incorrecta
  contra una cuenta inactiva; commit del test hecho.

### - [x] T17: Implementar `AttemptLoginAction`

- **Tipo**: impl
- **Cubre**: RF-17, RF-18, RF-19, RF-20, RF-21, RF-22
- **Depende de**: T16
- **Hecho cuando**: el test de T16 pasa y la suite completa queda verde.

### - [x] T18: Escribir test del registro de accesos

- **Tipo**: test
- **Cubre**: RF-36, RF-37
- **Depende de**: T17
- **Hecho cuando**: el test falla y comprueba que un ingreso exitoso deja un asiento nuevo con
  fecha, hora, portal y método sin sobrescribir los anteriores, y que un rechazo deja el email
  usado y el motivo; commit del test hecho.

### - [x] T19: Implementar `AccessLogger` e integrarlo en `AttemptLoginAction`

- **Tipo**: impl
- **Cubre**: RF-36, RF-37
- **Depende de**: T18
- **Hecho cuando**: el test de T18 pasa y la suite completa queda verde.

### - [x] T20: Escribir test del límite de intentos fallidos

- **Tipo**: test
- **Cubre**: RNF-2
- **Depende de**: T19
- **Hecho cuando**: el test falla y comprueba que el sexto intento contra el mismo email dentro del
  minuto se rechaza, y que al vencer los 60 segundos vuelve a aceptarse automáticamente; commit del
  test hecho.

### - [x] T21: Implementar el límite con el `RateLimiter` nativo

- **Tipo**: impl
- **Cubre**: RNF-2
- **Depende de**: T20
- **Hecho cuando**: el test de T20 pasa, la suite completa queda verde y el driver de caché usado en
  tests queda declarado en `phpunit.xml` como `database`.

---

## Fase 4: Contraseña

### - [x] T22: Escribir test de definición de contraseña por enlace

- **Tipo**: test
- **Cubre**: RF-27, RF-28, RNF-1, RNF-3
- **Depende de**: T21
- **Hecho cuando**: el test falla y cubre el enlace vigente (establece contraseña y queda
  invalidado), el vencido y el ya usado (rechazo con opción de pedir uno nuevo), y el rechazo de
  contraseñas de menos de 8 caracteres; commit del test hecho.

### - [x] T23: Implementar `SetPasswordAction`

- **Tipo**: impl
- **Cubre**: RF-27, RF-28, RNF-1
- **Depende de**: T22
- **Hecho cuando**: el test de T22 pasa y la suite completa queda verde.

### - [x] T24: Escribir test de recuperación de contraseña

- **Tipo**: test
- **Cubre**: RF-29, RF-30
- **Depende de**: T23
- **Hecho cuando**: el test falla y comprueba que un email de cuenta activa recibe un enlace nuevo,
  y que un email inexistente o de cuenta inactiva devuelve el mismo mensaje de confirmación sin
  enviar ningún correo; commit del test hecho.

### - [x] T25: Implementar `RequestPasswordResetAction`

- **Tipo**: impl
- **Cubre**: RF-29, RF-30
- **Depende de**: T24
- **Hecho cuando**: el test de T24 pasa y la suite completa queda verde.

---

## Fase 5: Paneles y acceso

### - [x] T26: Crear los dos paneles Filament con su control de acceso

- **Tipo**: ui
- **Cubre**: RF-12, RF-24
- **Depende de**: T25
- **Hecho cuando**: `/staff` y `/portal` responden con su propia pantalla de acceso y
  `canAccessPanel()` de cada panel exige el rol correspondiente.

### - [x] T27: Escribir test de acceso cruzado, sin sesión y cierre de sesión

- **Tipo**: test
- **Cubre**: RF-16, RF-21, RF-22, RF-23, RF-24, RF-26
- **Depende de**: T26
- **Hecho cuando**: el test falla y cubre: visitante sin sesión redirigido a la pantalla de acceso
  del portal pedido, cuenta con rol ajeno rechazada en cada portal, ingreso válido que llega a la
  página inicial de su portal, y cierre de sesión que vuelve a la pantalla de acceso; commit del
  test hecho.

### - [x] T28: Implementar las Login Pages propias con email y contraseña

- **Tipo**: impl
- **Cubre**: RF-13, RF-16, RF-26
- **Depende de**: T27
- **Hecho cuando**: el test de T27 pasa y la suite completa queda verde.

### - [x] T29: Escribir test del corte de sesión al desactivar una cuenta

- **Tipo**: test
- **Cubre**: RF-33
- **Depende de**: T28
- **Hecho cuando**: el test falla y comprueba que una cuenta con sesión abierta que se desactiva
  queda fuera en su siguiente solicitud y vuelve a la pantalla de acceso; commit del test hecho.

### - [x] T30: Implementar el middleware `EnsureAccountIsActive`

- **Tipo**: impl
- **Cubre**: RF-33
- **Depende de**: T29
- **Hecho cuando**: el test de T29 pasa, el middleware está registrado en ambos paneles y la suite
  completa queda verde.

### - [x] T31: Crear la landing pública y verificar que no hay registro público

- **Tipo**: ui
- **Cubre**: RF-1, RF-25
- **Depende de**: T30
- **Hecho cuando**: `/` responde de forma anónima con la vista Blade de la landing, y un test
  comprueba que no existe ninguna ruta de registro público en la aplicación.

---

## Fase 6: Ingreso con Google

### - [x] T32: Escribir test del ingreso con Google

- **Tipo**: test
- **Cubre**: RF-14, RF-15, RF-17
- **Depende de**: T31
- **Hecho cuando**: el test falla y cubre los tres casos con Socialite simulado: email que
  corresponde a una cuenta (ingresa), email sin cuenta (no la crea e indica que no está
  habilitada), y flujo fallido o cancelado (vuelve a la pantalla de acceso sin crear nada); commit
  del test hecho.

### - [x] T33: Implementar las rutas de Google por portal

- **Tipo**: impl
- **Cubre**: RF-13, RF-14, RF-15, RF-17
- **Depende de**: T32
- **Hecho cuando**: el test de T32 pasa, los dos pares de rutas (`/staff/...` y `/portal/...`)
  delegan en `AttemptLoginAction` con su portal, y la suite completa queda verde.

---

## Fase 7: Administración y retención

### - [x] T34: Escribir test de las policies de cuentas y de registros

- **Tipo**: test
- **Cubre**: RF-9, RF-39
- **Depende de**: T33
- **Hecho cuando**: el test falla y comprueba que una cuenta `staff` recibe permiso insuficiente al
  intentar una acción de gestión de cuentas y al intentar consultar registros de acceso o historial
  de cuentas; commit del test hecho.

### - [x] T35: Implementar `AccountPolicy` y `AccessLogPolicy`

- **Tipo**: impl
- **Cubre**: RF-9, RF-39
- **Depende de**: T34
- **Hecho cuando**: el test de T34 pasa y la suite completa queda verde.

### - [x] T36: Crear `AccountResource` en el panel de staff

- **Tipo**: ui
- **Cubre**: RF-2, RF-6, RF-8, RF-32
- **Depende de**: T35
- **Hecho cuando**: un `admin` puede crear cuentas, cambiar roles y activar/desactivar desde la
  interfaz, y un test comprueba que la sección no aparece en la navegación de una cuenta `staff`.

### - [x] T37: Crear `AccessLogResource` de solo lectura

- **Tipo**: ui
- **Cubre**: RF-38
- **Depende de**: T36
- **Hecho cuando**: un `admin` consulta desde la interfaz los registros de acceso y el historial de
  cuentas, sin ninguna acción de edición ni de borrado disponible.

### - [x] T38: Escribir test de la purga de registros de acceso

- **Tipo**: test
- **Cubre**: RNF-5, RNF-6
- **Depende de**: T37
- **Hecho cuando**: el test falla y comprueba que el comando borra los `access_logs` de más de 24
  meses, conserva los más nuevos y **no toca ninguna fila de `account_histories`**; commit del test
  hecho.

### - [x] T39: Implementar el comando `access-logs:prune` y programarlo

- **Tipo**: impl
- **Cubre**: RNF-5, RNF-6
- **Depende de**: T38
- **Hecho cuando**: el test de T38 pasa, el comando queda programado mensualmente en el scheduler y
  la suite completa queda verde.

### - [x] T40: Crear el comando de instalación de la primera cuenta `admin`

- **Tipo**: impl
- **Cubre**: — (supuesto de instalación, spec §9)
- **Depende de**: T39
- **Hecho cuando**: el comando crea una cuenta `admin` activa sobre una base vacía y falla con un
  mensaje claro si ya existe alguna cuenta `admin`.

---

## Fase 8: Pantallas de contraseña y umbrales (agregada tras validar la spec)

### - [ ] T41: Escribir test de las pantallas de definición y recuperación de contraseña

- **Tipo**: test
- **Cubre**: RF-5, RF-27, RF-28, RF-29, RF-30, RNF-1
- **Depende de**: T40
- **Hecho cuando**: el test falla y cubre, en cada portal: el enlace de recuperación visible en la
  pantalla de acceso; pedir recuperación con una cuenta activa (envía el enlace) y con una
  inexistente o inactiva (mismo mensaje, sin correo); el enlace del mail de alta apuntando al
  portal según el rol de la cuenta y abriendo la pantalla de definición; definir la contraseña con
  un enlace vigente (queda invalidado); un enlace vencido o ya usado, que ofrece pedir uno nuevo; y
  el rechazo de menos de 8 caracteres; commit del test hecho.

### - [ ] T42: Implementar las pantallas de contraseña y la URL del enlace

- **Tipo**: impl
- **Cubre**: RF-5, RF-27, RF-28, RF-29, RF-30, RNF-1
- **Depende de**: T41
- **Hecho cuando**: el test de T41 pasa y la suite completa queda verde; ambos paneles tienen
  `passwordReset()` con `PortalRequestPasswordReset` y `PortalResetPassword`, que solo delegan en
  `RequestPasswordResetAction` y `SetPasswordAction`; el mail de alta y el de recuperación apuntan a
  la pantalla del portal que corresponde al rol (plan D-11).

### - [ ] T43: Escribir test de los umbrales de sesión y de enlace

- **Tipo**: test
- **Cubre**: RNF-3, RNF-4
- **Depende de**: T42
- **Hecho cuando**: un test comprueba que `session.lifetime` es 120 minutos y que el vencimiento del
  broker de contraseñas es 1440 minutos. Es un test de verificación sobre configuración ya hecha en
  T2: pasa sin implementación nueva.

---

## Mapa RF → tareas

| RF | Tareas |
|---|---|
| RF-1 | T31 |
| RF-2 | T8, T9, T36 |
| RF-3 | T4, T7, T8, T9 |
| RF-4 | T3, T10, T11 |
| RF-5 | T8, T9, T41, T42 |
| RF-6 | T12, T13, T36 |
| RF-7 | T12, T13 |
| RF-8 | T36 |
| RF-9 | T34, T35 |
| RF-10 | T12, T13, T14, T15 |
| RF-11 | T12, T13, T14, T15 |
| RF-12 | T26 |
| RF-13 | T28, T33 |
| RF-14 | T32, T33 |
| RF-15 | T32, T33 |
| RF-16 | T27, T28 |
| RF-17 | T16, T17, T32, T33 |
| RF-18 | T16, T17 |
| RF-19 | T16, T17 |
| RF-20 | T16, T17 |
| RF-21 | T16, T17, T27 |
| RF-22 | T16, T17, T27 |
| RF-23 | T27 |
| RF-24 | T26, T27 |
| RF-25 | T31 |
| RF-26 | T27, T28 |
| RF-27 | T22, T23, T41, T42 |
| RF-28 | T22, T23, T41, T42 |
| RF-29 | T24, T25, T41, T42 |
| RF-30 | T24, T25, T41, T42 |
| RF-31 | T14, T15 |
| RF-32 | T14, T15, T36 |
| RF-33 | T29, T30 |
| RF-34 | T3 |
| RF-35 | T5, T7, T8, T9, T14, T15 |
| RF-36 | T6, T7, T18, T19 |
| RF-37 | T6, T7, T18, T19 |
| RF-38 | T37 |
| RF-39 | T34, T35 |
| RNF-1 | T22, T23, T41, T42 |
| RNF-2 | T20, T21 |
| RNF-3 | T2, T22, T43 |
| RNF-4 | T2, T43 |
| RNF-5 | T38, T39 |
| RNF-6 | T38, T39 |

## Cambios en el plan detectados al descomponer

- **El plan asume que el proyecto Laravel ya existe, y no existe.** Este repo es solo
  documentación: no hay `composer.json`, ni `app/`, ni `tests/`. Se resolvio con
  `docs/adr/0001-bootstrap-laravel-fuera-de-specs.md`: la puesta en marcha queda como prerequisito
  externo `P0`, no como tarea funcional de esta spec.
- **El plan no definia qué driver de caché usa el `RateLimiter`**, y lo dejaba anotado como riesgo
  ("depende del driver de caché del entorno"). Queda resuelto usando cache en base de datos:
  `CACHE_STORE=database` en entorno local y `phpunit.xml` configurado con cache `database` para
  tests.
- **Las traducciones de `lang/es/auth.php` figuran en el plan §5 como un ítem suelto**, pero no
  cuelgan de ningún RF puntual: acá se repartieron entre cada tarea de `impl` que produce el
  mensaje. Si se prefiere una tarea única de traducciones, hay que decidirlo en el plan.
- **El plan no incluía la interfaz de definición y recuperación de contraseña (RF-27 a RF-30), y el mail de alta (RF-5) no tenía URL a la que apuntar.** Lo detectó el `spec-validator` al cierre de la 001: `Route [password.reset] not defined` al enviar el enlace fuera de los tests. Se resolvió con el plan D-11 y las tareas T41 a T43.
- **RNF-2 decía "60 segundos siguientes al último intento" pero el `RateLimiter` nativo (plan D-8) usa una ventana fija desde el primer fallo.** La spec se enmendó con aprobación de Claudio (2026-10-07).
- **RNF-4 no tenía test.** T43 verifica el umbral de sesión y el de vencimiento del enlace.
