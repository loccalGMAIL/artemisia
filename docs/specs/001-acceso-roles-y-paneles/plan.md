# 001 — Plan técnico: Acceso, roles y paneles

| | |
|---|---|
| **Spec** | docs/specs/001-acceso-roles-y-paneles/spec.md (versión aprobada) |
| **Estado** | Aprobado |
| **Fecha** | 2026-09-10 |
| **Autor** | Claudio |

## 1. Resumen del enfoque

Cuentas y roles se apoyan en lo que ya trae el stack: `spatie/laravel-permission` para el rol
único de cada cuenta, el broker de contraseña nativo de Laravel para el enlace de definición y
recuperación, y `laravel/socialite` para Google. El corte de acceso cruzado y de cuenta inactiva
se resuelve con un único servicio de autenticación que evalúa las tres condiciones en el orden que
fija RF-19, y el corte de sesión ante desactivación con un middleware que se ejecuta en cada
solicitud. Dos tablas propias (`account_histories`, `access_logs`) cubren los dos historiales que
la spec distingue.

## 2. Chequeo contra la constitución

- **Principio 1 (Stack mínimo)**: sin dependencias nuevas. Roles con `spatie/laravel-permission`,
  Google con `socialite`, contraseña con el broker nativo de Laravel, límite de intentos con el
  `RateLimiter` nativo: todo ya está en el stack de `AGENTS.md`.
- **Principio 2 (La spec manda)**: este plan cubre exactamente los RF/RNF de
  `docs/specs/001-acceso-roles-y-paneles/spec.md`.
- **Principio 3 (Lógica fuera de la interfaz)**: la evaluación de acceso, las reglas de rol único,
  la protección del último admin y el corte de sesión viven en Actions, en un middleware y en un
  servicio de dominio; las Login Pages de Filament solo invocan esas piezas y muestran su
  resultado.
- **Principio 4 (TDD estricto)**: ver sección 9.
- **Principio 5 (Persistencia explícita)**: ninguna cuenta se elimina físicamente (RF-34); el
  historial de cuentas (`account_histories`) es una tabla de solo alta, sin update ni delete.
- **Principio 6 (Idioma)**: clases y columnas en inglés (`User`, `AccountHistory`, `AccessLog`);
  todo mensaje visible sale de `lang/es/auth.php`.

## 3. Decisiones técnicas

### D-1: Rol único con `spatie/laravel-permission`, sin columna `role` propia

- **Decisión**: `User` usa el trait `HasRoles` del paquete; los roles `admin`, `staff` y `cliente`
  se siembran como registros de `Role`.
- **Motivo**: el paquete ya está en el stack exactamente para esto (AGENTS.md, sección 2).
- **Alternativa descartada**: columna `role` enum propia en `users`. Duplicaría lo que el paquete
  resuelve y complicaría integrarlo con las Policies basadas en permisos.
- **Consecuencias**: `canAccessPanel()` de cada panel consulta `hasRole()`.

### D-2: Un solo rol garantizado por reemplazo, no por constraint de base

- **Decisión**: toda asignación de rol usa `syncRoles([$role])` (reemplaza, nunca acumula).
- **Motivo**: el paquete permite múltiples roles por diseño; RF-3 exige exactamente uno.
- **Alternativa descartada**: constraint a nivel de base sobre la tabla `model_has_roles` del
  paquete. Descartada por tocar con migraciones propias el esquema interno de una dependencia.
- **Consecuencias**: `ChangeAccountRoleAction` y `CreateAccountAction` son el único punto de
  entrada para asignar rol; ningún otro código llama `assignRole()` directamente.

### D-3: Enlace de definición/recuperación con el broker de contraseña nativo de Laravel

- **Decisión**: se reutiliza `password_reset_tokens` y el `PasswordBroker` de Laravel tanto para
  el enlace inicial (RF-5) como para la recuperación (RF-29), configurando `expire => 1440`
  (minutos) en `config/auth.php` para cumplir las 24 horas de RNF-3.
- **Motivo**: cumple RF-25 a RF-31 sin tabla ni paquete nuevo.
- **Alternativa descartada**: tabla y generación de token propias. Reinventa lo que el framework
  ya resuelve, incluido el borrado del token al usarlo (RF-27).
- **Consecuencias**: `SetPasswordAction` y `RequestPasswordResetAction` envuelven al broker en
  vez de manejar tokens a mano.

### D-4: Identidad de Google exclusivamente por email, sin `google_id`

- **Decisión**: `users` no lleva ninguna columna de identidad de Google; la cuenta se busca por el
  email que devuelve Socialite.
- **Motivo**: RF-14 pide explícitamente identificar la cuenta "por el email que devuelve Google".
- **Alternativa descartada**: columna `google_id` para vincular la identidad de forma más robusta
  ante un cambio de email en Google. Ningún RF lo pide y agregaría un campo sin sustento.
- **Consecuencias**: si Google cambia el email asociado a una cuenta externa, el sistema ve una
  cuenta "no habilitada" (RF-17); es el comportamiento que la spec pide.

### D-5: Corte de sesión con middleware por solicitud, no invalidando `sessions` en base

- **Decisión**: un middleware `EnsureAccountIsActive`, registrado en ambos paneles, verifica en
  cada solicitud si `auth()->user()->is_active` sigue siendo verdadero; si no, cierra la sesión y
  redirige a la pantalla de acceso.
- **Motivo**: RF-33 pide el corte "en la siguiente solicitud que realice", que un middleware por
  request cumple sin acoplarse al driver de sesión configurado.
- **Alternativa descartada**: borrar las filas de la tabla `sessions` del usuario al desactivar la
  cuenta. Exige que las sesiones se persistan en base de datos, acoplando esta regla a un driver
  de sesión específico.
- **Consecuencias**: el mismo middleware sirve, sin cambios, para el corte de sesión que pide
  RF-42 de la spec `003-clientes` al desvincular una cuenta.

### D-6: Historial de cuentas en tabla propia, mismo patrón que `client_histories`

- **Decisión**: tabla `account_histories` con discriminador `field` (`created`, `activated`,
  `deactivated`, `role_changed`) y columnas JSON `old_value` / `new_value`.
- **Motivo**: consistencia con el patrón ya elegido en el plan de la spec `003-clientes`
  (`client_histories`) para el mismo tipo de necesidad.
- **Alternativa descartada**: un paquete de auditoría (`spatie/activitylog`). No está en la lista
  de dependencias del principio 1.
- **Consecuencias**: `CreateAccountAction`, `ActivateAccountAction`, `DeactivateAccountAction` y
  `ChangeAccountRoleAction` escriben un `AccountHistory::create()` al final de su `handle()`.

### D-7: Registro de acceso en una única tabla con discriminador de resultado

- **Decisión**: tabla `access_logs` con columna `outcome` (`success`, `rejected`), en vez de dos
  tablas separadas.
- **Motivo**: RF-36 y RF-37 piden esencialmente el mismo asiento (portal, fecha, hora) con campos
  opcionales distintos según el resultado.
- **Alternativa descartada**: tablas separadas para éxitos y para rechazos. Duplicaría las
  columnas comunes.
- **Consecuencias**: un único servicio `AccessLogger` centraliza cada escritura.

### D-8: Límite de intentos fallidos con el `RateLimiter` nativo, sin tabla propia

- **Decisión**: RNF-2 se implementa con `RateLimiter::for()` de Laravel, con clave por email
  normalizado, sin persistir los intentos en una tabla.
- **Motivo**: es exactamente el caso de uso del componente, sin dependencia nueva ni esquema
  propio.
- **Alternativa descartada**: tabla `login_attempts` propia. Reinventa el `RateLimiter`.
- **Consecuencias**: el límite depende del driver de caché configurado en el entorno (ver Riesgos).

### D-9: `access_logs` se purga a los 24 meses; `account_histories` nunca

- **Decisión**: un comando Artisan programado (`access-logs:prune`), corriendo mensualmente vía el
  scheduler, elimina físicamente los registros de `access_logs` con más de 24 meses. Ninguna tarea
  equivalente existe para `account_histories`.
- **Motivo**: cierra explícitamente la distinción entre ambos historiales: RNF-5 acota solo los
  registros de acceso; el historial de cuentas queda protegido por el principio 5 sin plazo.
- **Alternativa descartada**: purgar ambas tablas igual, o no purgar ninguna. Lo primero
  contradice el principio 5 aplicado al historial de cuentas; lo segundo no cumple RNF-5.
- **Consecuencias**: es la única purga física de datos en todo este plan, y está acotada a una
  tabla que la propia spec distingue del historial "de cuentas".

### D-10: Login con Google mediante dos pares de rutas, uno por portal

- **Decisión**: `/staff/auth/google/redirect` + `/staff/auth/google/callback` y
  `/portal/auth/google/redirect` + `/portal/auth/google/callback`, en vez de una única ruta
  compartida.
- **Motivo**: el callback necesita saber a qué portal pertenece el intento antes de poder aplicar
  RF-19/RF-21/RF-22 (correspondencia entre rol y portal), y un par de rutas por portal lo fija sin
  depender de datos de sesión entre el redirect y el callback de Google.
- **Alternativa descartada**: una única ruta con el portal de origen guardado en sesión. Más
  frágil si la sesión se pierde entre el redirect a Google y su callback.
- **Consecuencias**: ambos pares de rutas delegan en el mismo `AttemptLoginAction`, parametrizado
  por portal.

## 4. Modelo de datos

### users (referenciada por otras specs; esta spec la crea)

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| name | string(150) | no | — | nombre completo (RF-2) |
| email | string(150) | no | — | único |
| password | string | sí | null | nulo hasta que se usa el enlace de definición |
| is_active | boolean | no | true | |
| created_at / updated_at | timestamp | — | — | |

- **Índices**: `UNIQUE(email)`.
- **Soft deletes**: no. Ninguna cuenta se elimina; `is_active` es la única baja posible (RF-34).
- **Nota de secuencia**: la spec `003-clientes` agrega a esta misma tabla la columna nullable
  `client_id` en su propia migración, que debe ejecutarse después de la migración que crea esta
  tabla (ver Riesgos).

### account_histories

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| user_id | bigint unsigned | no | — | FK a `users` |
| field | string(20) | no | — | enum `AccountHistoryField` |
| old_value | json | sí | null | null en el alta |
| new_value | json | no | — | |
| author_id | bigint unsigned | no | — | FK a `users` |
| created_at | timestamp | no | — | sin `updated_at`: tabla de solo alta |

- **Índices**: índice compuesto `(user_id, created_at)`.
- **Claves foráneas**: `user_id` → `users.id`; `author_id` → `users.id`.
- **Enums**: `AccountHistoryField` (`created`, `activated`, `deactivated`, `role_changed`).
- **Soft deletes**: no aplica (nunca se actualiza ni se borra una fila ya escrita).

### access_logs

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| user_id | bigint unsigned | sí | null | null si el email no corresponde a ninguna cuenta |
| email_used | string(150) | no | — | el email con el que se intentó ingresar |
| portal | string(10) | no | — | enum `AccessPortal`: `staff`, `client` |
| method | string(10) | no | — | enum `AccessMethod`: `password`, `google` |
| outcome | string(10) | no | — | enum `AccessOutcome`: `success`, `rejected` |
| rejection_reason | string(150) | sí | null | solo si `outcome = rejected` |
| created_at | timestamp | no | — | fecha y hora del intento; sin `updated_at` |

- **Índices**: índice sobre `created_at` (para la purga de RNF-5); índice sobre `email_used`.
- **Claves foráneas**: `user_id` → `users.id` (nullable).
- **Enums**: `AccessPortal`, `AccessMethod`, `AccessOutcome`.
- **Soft deletes**: no aplica; se purga físicamente a los 24 meses (D-9), no se archiva.

### Tablas de terceros reutilizadas (no se crean en este plan)

- `roles`, `model_has_roles`, `permissions` — migraciones propias de `spatie/laravel-permission`,
  publicadas al instalar el paquete. `RoleSeeder` siembra `admin`, `staff`, `cliente`.
- `password_reset_tokens` — migración por defecto de Laravel, reutilizada según D-3.

Migraciones a crear, en orden:

1. `create_users_table`
2. `create_account_histories_table`
3. `create_access_logs_table`
4. (del paquete) `create_permission_tables` — publicada, no autoría de este plan.

## 5. Estructura de módulos

- **Models**: `User` (nuevo, `HasRoles`), `AccountHistory` (nuevo), `AccessLog` (nuevo).
- **Enums**: `AccountHistoryField`, `AccessPortal`, `AccessMethod`, `AccessOutcome`.
- **Actions**:
  - `CreateAccountAction` — alta, asigna rol único, envía enlace de definición, registra historial.
  - `ChangeAccountRoleAction` — valida no-autocambio y regla del último admin; registra historial.
  - `ActivateAccountAction` / `DeactivateAccountAction` — valida no-autodesactivación y último
    admin; invalida enlace vigente al desactivar; registra historial.
  - `SetPasswordAction` — valida el token vía el broker, establece contraseña.
  - `RequestPasswordResetAction` — envía enlace si la cuenta está activa; mismo mensaje siempre.
  - `AttemptLoginAction` — orden de evaluación de RF-19 (credenciales/Google → activa →
    rol/portal); llama a `AccessLogger`.
- **Servicios**: `AccessLogger` — centraliza la escritura en `access_logs`.
- **Middleware**: `EnsureAccountIsActive` — corte de sesión por inactividad de cuenta (D-5).
- **Policies**: `AccountPolicy` (todas las acciones de gestión de cuentas y roles, solo `admin`);
  `AccessLogPolicy` (consulta de registros de acceso e historial de cuentas, solo `admin`).
- **Filament Resources / Pages / Widgets**:
  - `StaffPanelProvider` (`/staff`) y `ClientPanelProvider` (`/portal`), cada uno con su propia
    Login Page (formulario email/contraseña + botón Google) y `canAccessPanel()` según rol.
  - `AccountResource` (panel `staff`, oculto para `staff` vía `shouldRegisterNavigation`, RF-8).
  - `AccessLogResource` (panel `staff`, solo `admin`, RF-38/RF-39): registros de acceso e
    historial de cuentas, de solo lectura.
- **Rutas**: `/` (landing, Blade); `/staff/auth/google/redirect` y `/callback`;
  `/portal/auth/google/redirect` y `/callback` (D-10).
- **Comandos Artisan**: `access-logs:prune` (programado mensualmente, D-9); comando o seeder para
  la primera cuenta `admin` (según la spec, paso de instalación).
- **Traducciones**: `lang/es/auth.php` con los mensajes de cada rechazo (credenciales inválidas,
  cuenta deshabilitada, portal incorrecto, enlace vencido, permiso insuficiente).

## 6. Contratos de Actions y servicios

- **CreateAccountAction::handle(array $data): User** — crea la cuenta activa, `syncRoles([$data['role']])`,
  envía el enlace de definición vía el broker, registra `AccountHistory` de alta.
- **ChangeAccountRoleAction::handle(User $target, string $role, User $actor): User** — lanza
  excepción si `$target->is($actor)`; lanza excepción si el cambio deja sin ningún `admin` activo;
  registra historial.
- **ActivateAccountAction::handle(User $target): User** / **DeactivateAccountAction::handle(User
  $target, User $actor): User** — la segunda valida no-autodesactivación y último admin; invalida
  cualquier token de `password_reset_tokens` vigente para esa cuenta; registra historial.
- **SetPasswordAction::handle(string $token, string $email, string $password): void** — delega en
  el `PasswordBroker`; si el token es inválido o vencido, lanza excepción con el motivo (RF-28).
- **RequestPasswordResetAction::handle(string $email): void** — siempre devuelve éxito silencioso;
  solo dispara el envío real si el email corresponde a una cuenta activa.
- **AttemptLoginAction::handle(array $credentials|SocialiteUser $googleUser, string $portal,
  string $method): AuthResult** — evalúa en orden: (1) credenciales o email de Google corresponden
  a una cuenta, (2) la cuenta está activa, (3) su rol corresponde al portal solicitado; se detiene
  en la primera condición que falla y llama a `AccessLogger::log()` con el resultado.
- **AccessLogger::log(?User $user, string $emailUsed, string $portal, string $method, string
  $outcome, ?string $reason): void** — escribe una fila en `access_logs`.

## 7. Reglas de negocio y validaciones

- No existe ningún mecanismo de registro público de cuentas (RF-1).
- Cada cuenta tiene exactamente un rol, asignado por reemplazo (RF-3, D-2).
- El email es único entre todas las cuentas, sin excepción por estado (RF-4).
- Un admin no puede cambiar su propio rol ni desactivar su propia cuenta (RF-11); ninguna
  operación puede dejar el sistema sin ningún admin activo (RF-10).
- El ingreso se evalúa en el orden fijo: credenciales/Google → cuenta activa → rol correspondiente
  al portal (RF-19); cada rama tiene su propio mensaje (RF-16 a RF-18, RF-20 a RF-22).
- El mensaje de credenciales inválidas nunca revela si el email existe (RF-17); el de recuperación
  de contraseña tampoco distingue cuenta inexistente de inactiva (RF-30).
- Un enlace de definición/recuperación es de un solo uso, vence a las 24 horas, y se invalida si
  la cuenta se desactiva antes de usarlo (RF-27, RF-28, RF-31).
- Una cuenta desactivada corta su sesión abierta en la siguiente solicitud (RF-33).
- Ninguna cuenta se elimina físicamente (RF-34); el historial de cuentas nunca se purga (RNF-6);
  los registros de acceso se purgan a los 24 meses (RNF-5).
- Solo `admin` gestiona cuentas y roles, y solo `admin` consulta registros de acceso e historial de
  cuentas (RF-9, RF-39); `staff` no ve esa sección (RF-8).

## 8. Autorización

- `AccountPolicy` autoriza únicamente a `admin` para crear cuentas, cambiar roles, activar y
  desactivar. `staff` y `cliente` no pasan ninguna de sus reglas.
- `AccessLogPolicy` autoriza únicamente a `admin` para consultar `access_logs` y
  `account_histories`.
- `canAccessPanel()` de `StaffPanelProvider` exige rol `admin` o `staff`; el de
  `ClientPanelProvider` exige rol `cliente`. Ninguno depende de permisos finos: la spec deja el
  reparto fino de permisos por módulo a cada spec de dominio (sección 8 de la spec, Fuera de
  alcance).

## 9. Estrategia de tests

- **Unit**: `AttemptLoginAction` cubierto con las cuatro combinaciones de credenciales/actividad/
  portal en el orden de RF-19; regla del último admin activo; bloqueo de autocambio de rol y
  autodesactivación; invalidación del enlace vigente al desactivar una cuenta.
- **Feature**: alta de cuenta y envío del enlace; ingreso exitoso por email/contraseña y por
  Google en cada portal; ingreso rechazado por credenciales inválidas, cuenta inactiva y portal
  cruzado; cancelación del flujo de Google; definición y recuperación de contraseña, incluido
  enlace vencido o ya usado; activación/desactivación con corte de sesión; límite de intentos
  fallidos (RNF-2); acceso a `AccountResource` y `AccessLogResource` restringido a `admin`.
- **Factories nuevas**: `UserFactory` (con estado `admin`/`staff`/`cliente`), `AccessLogFactory`,
  `AccountHistoryFactory`.
- **Seeders**: `RoleSeeder` (datos de referencia: `admin`, `staff`, `cliente`).

## 10. Mapa RF → componente

| RF | Descripción corta | Cubierto por |
|---|---|---|
| RF-1 | Sin registro público | Ausencia deliberada de ruta de registro |
| RF-2, RF-3, RF-5 | Alta de cuenta con rol único | `CreateAccountAction`, migración `create_users_table`, `RoleSeeder` |
| RF-4 | Email único | `UNIQUE(email)`, `CreateAccountAction` |
| RF-6 | Cambiar rol de cualquier cuenta salvo la propia | `ChangeAccountRoleAction` |
| RF-7 | Rol nuevo aplica en la siguiente solicitud | Comportamiento estándar de `spatie/laravel-permission` (sin caché de permisos entre requests) |
| RF-8 | `staff` no ve gestión de cuentas | `AccountResource::shouldRegisterNavigation()` |
| RF-9 | Rechazo a quien no es admin | `AccountPolicy` |
| RF-10 | Nunca sin admin activo | `ChangeAccountRoleAction`, `DeactivateAccountAction` |
| RF-11 | No autocambio de rol ni autodesactivación | `ChangeAccountRoleAction`, `DeactivateAccountAction` |
| RF-12, RF-13 | Pantalla de acceso propia por portal, con Google | `StaffPanelProvider`/`ClientPanelProvider` (Login Pages) |
| RF-14 | Identidad por email de Google | `AttemptLoginAction` (rama Google), D-4 |
| RF-15 | Ingreso lleva a la página inicial del portal | Login Page (redirect post-autenticación) |
| RF-16 | Fallo/cancelación de Google | Rutas `.../auth/google/callback` (manejo de error de Socialite) |
| RF-17 | Email de Google sin cuenta | `AttemptLoginAction` (rama Google) |
| RF-18 | Credenciales sin coincidencia | `AttemptLoginAction` (rama password) |
| RF-19 | Orden de evaluación | `AttemptLoginAction` |
| RF-20 | Cuenta inactiva rechazada | `AttemptLoginAction` |
| RF-21, RF-22 | Rol no corresponde al portal | `AttemptLoginAction` |
| RF-23 | Visitante sin sesión a la pantalla de acceso | Middleware `auth` estándar de cada panel |
| RF-24 | Ruta que no corresponde al rol | `canAccessPanel()` |
| RF-25 | Landing anónima | Ruta `/` sin middleware `auth` |
| RF-26 | Logout a la pantalla de acceso | Comportamiento estándar de logout de Filament |
| RF-27, RF-28 | Enlace vigente / vencido o usado | `SetPasswordAction`, broker nativo (D-3) |
| RF-29, RF-30 | Recuperación por email, mensaje uniforme | `RequestPasswordResetAction` |
| RF-31 | Invalidar enlace al desactivar | `DeactivateAccountAction` |
| RF-32 | Activar/desactivar cualquier cuenta salvo la propia | `ActivateAccountAction`, `DeactivateAccountAction` |
| RF-33 | Corte de sesión al desactivar | Middleware `EnsureAccountIsActive` (D-5) |
| RF-34 | Nunca se elimina una cuenta | Ausencia de acción de borrado físico sobre `User` |
| RF-35 | Historial de alta/activación/desactivación/rol | `account_histories`, escritura desde cada Action |
| RF-36 | Registro de ingreso exitoso | `access_logs` (`outcome = success`), `AccessLogger` |
| RF-37 | Registro de intento rechazado | `access_logs` (`outcome = rejected`), `AccessLogger` |
| RF-38 | Consulta de registros e historial | `AccessLogResource` |
| RF-39 | Rechazo a quien no es admin | `AccessLogPolicy` |
| RNF-1 | Contraseña mínima 8 caracteres | Regla `Password::min(8)` en `SetPasswordAction` |
| RNF-2 | Límite de 5 intentos fallidos por minuto | `RateLimiter` nativo (D-8) |
| RNF-3 | Enlace vigente 24 horas | `config/auth.php` `expire => 1440` (D-3) |
| RNF-4 | Sesión inactiva 120 minutos | `config/session.php` `lifetime => 120` |
| RNF-5 | Registros de acceso 24 meses | Comando `access-logs:prune` (D-9) |
| RNF-6 | Historial de cuentas sin purga | `account_histories` sin tarea de limpieza (D-9) |

## 11. Riesgos

- **Orden de migraciones entre esta spec y la `003-clientes`**: la migración de `003` agrega
  `client_id` a `users` y debe ejecutarse después de que esta spec cree la tabla. Mitigación: el
  orden de implementación sigue el orden de dependencia (001 antes que 003), sin importar en qué
  orden se redactaron los planes.
- **`RateLimiter` depende del driver de caché del entorno**: en un entorno sin caché persistente
  entre procesos (por ejemplo `array` en ciertos setups), el límite de intentos podría no
  sostenerse entre solicitudes. Mitigación: documentar en el entorno local (`AGENTS.md`, sección
  3) qué driver de caché usar; no es una decisión de este plan.
- **Dos pares de rutas de Google por portal duplican código de manejo de callback**: mitigación —
  ambos pares delegan en el mismo `AttemptLoginAction`, la única diferencia es el parámetro
  `portal`.

## 12. Fuera de alcance del plan

- No implementa el modelo `Client` de la spec `003-clientes`, más allá de asumir que esa spec le
  agrega `client_id` a `users`.
- No implementa segundo factor de autenticación ni edición de perfil propio: la spec los deja
  fuera de alcance (sección 8).
- No implementa el contenido de cada portal más allá de sus Login Pages: cada módulo lo define en
  su propia spec y plan.

## 13. Preguntas abiertas

Ninguna: todas las decisiones técnicas quedaron resueltas en la sección 3, con su alternativa
descartada.

## 14. Aprobación

- [x] Aprobado por Claudio el 2026-09-10
