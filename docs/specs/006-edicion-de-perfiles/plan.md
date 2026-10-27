# 006 — Plan técnico: Edición de perfiles

| | |
|---|---|
| **Spec** | docs/specs/006-edicion-de-perfiles/spec.md (versión aprobada) |
| **Estado** | Aprobado |
| **Fecha** | 2026-10-27 |
| **Autor** | Claudio |

## 1. Resumen del enfoque

Dos Actions concentran las reglas: `UpdateAccountProfileAction` (nombre y email, con historial, aviso y
baja de enlaces pendientes) y `ChangeOwnPasswordAction` (contraseña propia). El perfil propio de ambos
portales se monta sobre la página de perfil que trae Filament (`->profile()`), con una clase base común
que solo presenta el formulario y delega en las Actions. La edición de otras cuentas es una acción de
fila más en el listado de cuentas, con el mismo patrón que «Cambiar rol». No hay migraciones: el
historial de cuentas ya admite los hechos nuevos. El corte de las otras sesiones lo hace el middleware
`AuthenticateSession`, que ya está en ambos paneles.

## 2. Chequeo contra la constitución

- **Principio 1 (Stack mínimo)**: sin dependencias nuevas. Los avisos usan el sistema de notificaciones de Laravel y la interfaz es Filament.
- **Principio 2 (La spec manda)**: el plan cubre los RF-1 a RF-24 de la spec 006 (mapa en la sección 10) y no agrega funcionalidad. La spec 001 ya apunta a la 006 desde su sección 8.
- **Principio 3 (Lógica fuera de la interfaz)**: validación, unicidad de email, regla del email propio, verificación de la contraseña actual, historial, aviso y baja de enlaces viven en las dos Actions. La página de perfil y la acción de la tabla solo orquestan y traducen errores.
- **Principio 4 (TDD estricto)**: sección 9; el test se escribe primero y su commit precede al de la implementación.
- **Principio 5 (Persistencia explícita)**: sin cambios de esquema. Las cuentas siguen sin borrarse. Cada cambio queda en `account_histories` con autor y fecha.
- **Principio 6 (Idioma)**: código en inglés; todo texto visible, incluidos los correos de aviso, sale de `lang/es`.

## 3. Decisiones técnicas

### D-1: Perfil propio sobre la página de perfil de Filament

- **Decisión**: ambos paneles registran `->profile(<clase propia>, isSimple: false)`. Cada clase extiende una base común `App\Filament\Auth\PortalEditProfile`, que a su vez extiende `Filament\Auth\Pages\EditProfile`.
- **Motivo**: Filament ya resuelve la ruta (`/staff/profile`, `/portal/profile`), el acceso desde el menú de usuario, el límite de intentos del guardado y el formulario con confirmación de contraseña. Solo se sobrescriben los campos y el guardado.
- **Alternativa descartada**: una `Page` desde cero, como `ClientProfilePage`. Obliga a reconstruir ruta, enlace del menú de usuario y límite de intentos, sin ganar nada.
- **Consecuencias**: dos clases mínimas (una por panel) para que cada panel registre la suya, igual que `Login` en `Staff/Pages/Auth` y `Client/Pages/Auth`.

### D-2: Una Action para nombre y email, otra para la contraseña

- **Decisión**: `UpdateAccountProfileAction` recibe objetivo, datos y autor; `ChangeOwnPasswordAction` recibe la cuenta, la contraseña actual y la nueva. El perfil propio llama a la primera solo con el nombre y, si el campo está completo, a la segunda.
- **Motivo**: son casos de uso distintos con reglas distintas (RF-12 solo afecta al email; RF-7 y RF-10 solo a la contraseña) y asientos distintos. Cada una queda testeable por separado.
- **Alternativa descartada**: una sola Action `UpdateAccountAction` con todo. Mezcla reglas y obliga a ramificar por qué campos vienen.
- **Consecuencias**: el guardado del perfil propio abre una transacción de base de datos (la que ya trae `EditProfile`) que cubre las dos llamadas.

### D-3: La regla del email propio vive en la Action

- **Decisión**: si el autor es la propia cuenta y el email cambia, la Action lanza `CannotChangeOwnEmailException`. La UI además muestra el campo deshabilitado (RF-3) y oculta la acción «Editar» en la propia fila (RF-11), pero eso es presentación.
- **Motivo**: es una regla de negocio (RF-12) y debe valer aunque alguien llame a la Action sin pasar por la UI. Sigue el patrón de `CannotChangeOwnRoleException`.
- **Alternativa descartada**: confiar solo en el campo deshabilitado. Un formulario manipulado cambiaría el email propio.
- **Consecuencias**: excepción nueva en `app/Exceptions`, con mensaje desde `lang/es/auth.php`.

### D-4: Corte de las otras sesiones por el hash de la contraseña

- **Decisión**: `ChangeOwnPasswordAction` guarda la contraseña nueva y, si hay sesión de la solicitud, actualiza la clave `password_hash_web` de la sesión actual. Las demás sesiones quedan inválidas porque `AuthenticateSession` (ya presente en ambos paneles) compara ese hash en cada solicitud.
- **Motivo**: cumple RF-10 sin tabla de sesiones ni código propio de limpieza, y es el mismo mecanismo que usa Filament al guardar la contraseña.
- **Alternativa descartada**: borrar filas de `sessions` por `user_id`. Depende del driver de sesión y hay que excluir la actual a mano.
- **Consecuencias**: la clave de sesión se toma de `Filament::getAuthGuard()`. El test de feature abre una segunda sesión y comprueba que cae.

### D-5: La contraseña actual se verifica en la Action

- **Decisión**: la Action compara la contraseña actual con `Hash::check` y lanza `ValidationException` con el error en `currentPassword`. El formulario no usa la regla `currentPassword` de Filament.
- **Motivo**: RF-7 es una regla de negocio; verificarla en la Action la hace testeable sin interfaz. Los intentos repetidos siguen limitados por el límite de 5 guardados por minuto que trae `EditProfile`.
- **Alternativa descartada**: dejar la regla `currentPassword` en el formulario. Es regla de negocio dentro de una clase de UI.
- **Consecuencias**: la Action devuelve errores con nombres de campo simples y la página los traduce con `FormValidation::prefixed`.

### D-6: Aviso del cambio de email con una notificación de Laravel

- **Decisión**: `AccountEmailChanged`, una notificación por correo enviada por ruta bajo demanda (`Notification::route('mail', ...)`) al email anterior y al nuevo, con texto distinto para cada destinatario. Se envía después de confirmar la transacción.
- **Motivo**: el sistema ya envía correo con notificaciones (`ResetPassword`); evita crear un mailable aparte. Enviar tras el commit evita avisar de un cambio que se revirtió.
- **Alternativa descartada**: colocar el envío dentro de la transacción. Un fallo posterior dejaría un aviso de un cambio inexistente.
- **Consecuencias**: un fallo de envío no deshace el cambio; se asume con el riesgo R-2.

### D-7: Enlaces pendientes con `Password::deleteToken`

- **Decisión**: antes de cambiar el email, la Action llama a `Password::deleteToken($target)`, que borra el token guardado bajo el email anterior.
- **Motivo**: es el mecanismo que ya usa `DeactivateAccountAction` (RF-31 de la spec 001). El token está indexado por email, así que hay que borrarlo antes de actualizar el email.
- **Alternativa descartada**: borrar de `password_reset_tokens` con una consulta propia. Duplica lo que el broker ya sabe hacer.
- **Consecuencias**: el orden dentro de la transacción importa y queda fijado por un test.

## 4. Modelo de datos

Sin cambios de esquema ni migraciones.

- `account_histories.field` es `string(20)` y admite los tres valores nuevos: `name_changed` (12), `email_changed` (13) y `password_changed` (16).
- `old_value` es nulo y `new_value` es obligatorio, ambos JSON:
  - nombre: `{"name": ...}` en anterior y nuevo.
  - email: `{"email": ...}` en anterior y nuevo.
  - contraseña: anterior nulo y nuevo `{"changed": true}`, sin ningún dato de contraseña (RF-22).
- **Enums**: `AccountHistoryField` suma `NameChanged = 'name_changed'`, `EmailChanged = 'email_changed'` y `PasswordChanged = 'password_changed'`.

## 5. Estructura de módulos

- **Models**: sin cambios.
- **Enums**: `AccountHistoryField` (modificado).
- **Actions**: `UpdateAccountProfileAction`, `ChangeOwnPasswordAction`.
- **Excepciones**: `CannotChangeOwnEmailException` (nueva); se reutiliza `DuplicateAccountEmailException`.
- **Notificaciones**: `AccountEmailChanged` (nueva, `app/Notifications`).
- **Policies**: `AccountPolicy::update` ya existe (solo admin); sin cambios.
- **Filament**:
  - `App\Filament\Auth\PortalEditProfile` (base común).
  - `App\Filament\Staff\Pages\Auth\EditProfile` y `App\Filament\Client\Pages\Auth\EditProfile`.
  - `StaffPanelProvider` y `ClientPanelProvider`: `->profile(...)`.
  - `AccountsTable`: acción de fila `edit`.
  - `AccountResource`: se corrige el docblock («never edited»).
- **Rutas**: las dos de perfil que registra Filament, `/staff/profile` y `/portal/profile`.
- **Traducciones**: claves nuevas en `lang/es/accounts.php` (campos, acciones, notificaciones, correo), `lang/es/logs.php` (`history_fields`) y `lang/es/auth.php` (mensaje del email propio).

## 6. Contratos de Actions y servicios

- **UpdateAccountProfileAction::handle(User $target, array $data, User $actor): User**: `$data` trae `name` y, opcionalmente, `email`. Valida, normaliza el email (minúsculas, sin espacios exteriores), aplica los cambios en una transacción, registra un asiento por cada campo que cambió y, si cambió el email, borra los enlaces pendientes y, tras el commit, avisa a ambas direcciones. Devuelve la cuenta actualizada. Lanza `ValidationException`, `DuplicateAccountEmailException` y `CannotChangeOwnEmailException`.
- **ChangeOwnPasswordAction::handle(User $account, string $currentPassword, string $newPassword): void**: verifica la actual, valida la nueva, la guarda, registra el asiento (autor: la propia cuenta) y refresca el hash de la sesión actual. Lanza `ValidationException` (campos `currentPassword` y `password`) y una excepción de dominio si la cuenta no tiene contraseña definida.

## 7. Reglas de negocio y validaciones

- El nombre es obligatorio y tiene hasta 150 caracteres, igual que en el alta.
- El email es obligatorio, con formato de email y hasta 150 caracteres.
- El email se compara y se guarda en minúsculas y sin espacios exteriores.
- Dos cuentas no pueden compartir email, inactivas incluidas; la propia cuenta no cuenta como conflicto.
- Quien edita su propia cuenta no puede cambiar su email.
- Guardar sin cambios no escribe asientos ni envía avisos.
- Cada campo cambiado es un asiento aparte, con valores anterior y nuevo salvo en la contraseña.
- El cambio de contraseña exige la actual correcta y una nueva con las reglas de `SetPasswordAction` (mínimo 8 caracteres).
- Una cuenta sin contraseña definida no puede usar el cambio de contraseña.
- Cambiar el email borra los enlaces de definición de contraseña pendientes del email anterior.
- El aviso sale a los dos emails y solo después de confirmar la transacción.
- Cambiar el email no cierra la sesión abierta: `EnsureAccountIsActive` busca la cuenta por id.

## 8. Autorización

- **Perfil propio**: cualquier cuenta con acceso a su panel; la Action siempre opera sobre `Filament::auth()->user()`, nunca sobre un id recibido.
- **Editar otra cuenta**: `AccountPolicy::update` (solo `admin`). La acción de fila usa `->authorize('update')`, así que un staff recibe el rechazo de permiso insuficiente (RF-13).
- **Historial**: `AccessLogPolicy` ya limita su consulta al admin (RF-24).

## 9. Estrategia de tests

- **Unit** (`tests/Unit/Actions`):
  - `UpdateAccountProfileActionTest`: cambios de nombre y email, normalización, validación, duplicado (con inactivas y sin contar la propia cuenta), email propio, historial, sin cambios, baja de enlaces y avisos.
  - `ChangeOwnPasswordActionTest`: contraseña actual incorrecta, contraseña nueva inválida, cuenta sin contraseña, asiento sin valores.
- **Feature** (`tests/Feature`):
  - `ProfileEditingTest`: perfil propio en staff y cliente; email deshabilitado; sección de contraseña oculta sin contraseña.
  - `AccountEditActionTest`: acción de fila para admin, rechazo a staff, acción oculta en la propia fila.
  - `PasswordChangeSessionTest`: la segunda sesión cae y la actual sigue abierta.
  - `EmailChangeSessionTest`: la sesión abierta sigue abierta y el login con el email nuevo funciona, con contraseña y con Google.
  - El historial muestra los asientos nuevos.
- **Factories**: sin cambios; se usan los estados `admin()`, `staff()` y el de cliente de `UserFactory`.
- **Seeders**: sin cambios.

## 10. Mapa RF → componente

| RF | Descripción corta | Cubierto por |
|---|---|---|
| RF-1 | Perfil propio con nombre y email | `PortalEditProfile`, `->profile()` en ambos paneles (D-1) |
| RF-2 | Guardar nombre nuevo | `UpdateAccountProfileAction` |
| RF-3 | Email visible y no editable | Campo deshabilitado en `PortalEditProfile` y regla de D-3 |
| RF-4 | Validación del nombre | `UpdateAccountProfileAction` (sección 7) |
| RF-5 | Cambio de contraseña si hay una definida | Sección condicional de `PortalEditProfile` |
| RF-6 | Cambiar con actual correcta y nueva válida | `ChangeOwnPasswordAction` |
| RF-7 | Rechazo si la actual no coincide | `ChangeOwnPasswordAction` (D-5) |
| RF-8 | Rechazo si la nueva es inválida | `ChangeOwnPasswordAction` |
| RF-9 | Cuenta sin contraseña: indicar recuperación | `PortalEditProfile` (aviso) y excepción de `ChangeOwnPasswordAction` |
| RF-10 | Cerrar las otras sesiones | `AuthenticateSession` y refresco del hash en `ChangeOwnPasswordAction` (D-4) |
| RF-11 | Admin edita nombre y email de otras cuentas | Acción `edit` en `AccountsTable`, oculta en la propia fila |
| RF-12 | El admin no cambia su propio email | `CannotChangeOwnEmailException` (D-3) |
| RF-13 | Rechazo a quien no es admin | `AccountPolicy::update`, `->authorize('update')` |
| RF-14 | Conflicto de email | `DuplicateAccountEmailException`, reutilizada |
| RF-15 | Email vacío o con formato inválido | `UpdateAccountProfileAction` (sección 7) |
| RF-16 | Identificar por el email nuevo | Actualización del email; el ingreso ya busca por email |
| RF-17 | Aviso a ambos emails | `AccountEmailChanged` (D-6) |
| RF-18 | Invalidar enlaces pendientes | `Password::deleteToken` (D-7) |
| RF-19 | La sesión abierta sigue abierta | `EnsureAccountIsActive` sin cambios |
| RF-20 | Asiento de nombre | `AccountHistoryField::NameChanged` |
| RF-21 | Asiento de email | `AccountHistoryField::EmailChanged` |
| RF-22 | Asiento de contraseña sin valores | `AccountHistoryField::PasswordChanged`, `{"changed": true}` |
| RF-23 | Sin cambios, sin asiento | `UpdateAccountProfileAction` (sección 7) |
| RF-24 | Asientos visibles para el admin | `AccountHistoriesTable` y etiquetas nuevas en `lang/es/logs.php` |

## 11. Riesgos

- **R-1: dos admins cambian a la vez al mismo email de dos cuentas**: la restricción única de `users.email` lo rechaza en la base; la Action captura la excepción de unicidad y la informa como conflicto.
- **R-2: el correo de aviso falla tras el commit**: el cambio ya está hecho y queda en el historial; se registra el error en el log y no se deshace el cambio.
- **R-3: `Password::deleteToken` borra por el email vigente**: se llama antes de actualizar el email; un test fija el orden.
- **R-4: `password_hash_<guard>` depende del nombre del guard de Filament**: se obtiene con `Filament::getAuthGuard()`, no se escribe a mano, y el test de dos sesiones lo cubre.
- **R-5: las clases `EditProfile` por panel se descubren como páginas**: se registran solo con `->profile()` y se ubican en `Pages/Auth`, donde el descubrimiento no las trata como páginas de navegación (igual que `Login`).

## 12. Fuera de alcance del plan

- Confirmación del email nuevo por enlace; la spec la deja fuera.
- Limpieza o rotación de otros datos de sesión al cambiar el email.
- Cambiar los textos de las pantallas de recuperación de contraseña.
- Cualquier cambio sobre la ficha del cliente (spec 003).

## 13. Preguntas abiertas

Ninguna.

## 14. Aprobación

- [x] Aprobado por Claudio el 2026-10-27
