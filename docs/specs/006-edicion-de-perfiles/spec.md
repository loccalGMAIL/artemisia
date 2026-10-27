# 006 — Edición de perfiles

| | |
|---|---|
| **Estado** | Aprobada |
| **Fecha** | 2026-10-27 |
| **Autor** | Claudio |
| **Rama propuesta** | v00.07.00-edicion-de-perfiles |

## 1. Problema

Hoy los datos de una cuenta no se pueden corregir: un nombre mal escrito o un email que cambió
obligan a dejar la cuenta como está, y cada persona solo puede cambiar su contraseña por el flujo
de recuperación por email, aunque ya haya ingresado. La spec 001 dejó la edición de perfiles fuera
de alcance para una spec posterior.

## 2. Objetivo

Que cada persona mantenga al día sus propios datos de cuenta y que un admin pueda corregir los de
las demás cuentas, sin perder trazabilidad.

Se sabrá que se logró cuando:

- Una cuenta de cualquier rol pueda cambiar su nombre y su contraseña estando dentro de su portal.
- Un admin pueda corregir el nombre y el email de otra cuenta desde el listado de cuentas.
- Cada cambio quede en el historial de cuentas con autor y fecha.

## 3. Actores

- **Admin** — edita nombre y email de las demás cuentas; edita su propio nombre y contraseña.
- **Staff** — edita su propio nombre y contraseña en el portal de staff.
- **Cliente** — edita su propio nombre y contraseña en el portal de clientes.

## 4. Historias de usuario

- **HU-1**: Como miembro del staff o cliente, quiero corregir mi nombre, para que me identifiquen bien en el sistema.
- **HU-2**: Como persona con sesión iniciada, quiero cambiar mi contraseña, para no depender del correo de recuperación.
- **HU-3**: Como admin, quiero corregir el nombre y el email de otra cuenta, para mantener los datos al día cuando una persona cambia de correo o se cargó con un error.
- **HU-4**: Como admin, quiero ver quién cambió qué en una cuenta, para poder investigar un cambio inesperado.

## 5. Glosario

- **Cuenta**, **rol**, **cuenta activa / inactiva**, **portal de staff**, **portal de clientes**, **enlace de definición de contraseña**, **historial de cuentas** — definidos en la spec 001.
- **Perfil propio** — pantalla de cada portal donde la cuenta con sesión iniciada ve y edita sus datos de cuenta.
- **Datos de cuenta** — nombre, email y contraseña. No incluyen la ficha del cliente de la spec 003 (dirección, contactos), que se sigue gestionando donde ya está.

## 6. Alcance

Perfil propio en ambos portales con edición de nombre y cambio de contraseña (el email se muestra
sin poder editarse); edición de nombre y email de otras cuentas por un admin desde el listado de
cuentas, con aviso por correo del cambio de email; y registro de todos los cambios en el historial
de cuentas.

## 7. Requisitos funcionales

### Perfil propio

- **RF-1**: MIENTRAS una cuenta tenga sesión iniciada en su portal, EL SISTEMA le ofrecerá un perfil propio con su nombre y su email.
- **RF-2**: CUANDO una cuenta guarda un nombre nuevo en su perfil propio, EL SISTEMA lo aplicará a su cuenta.
- **RF-3**: EL SISTEMA mostrará el email en el perfil propio sin permitir modificarlo.
- **RF-4**: SI el nombre queda vacío o incumple las reglas de validación del nombre que rigen el alta de cuentas, ENTONCES EL SISTEMA no guardará el cambio y mostrará el motivo en el formulario.

### Cambio de contraseña propia

- **RF-5**: DONDE la cuenta tenga una contraseña definida, EL SISTEMA ofrecerá en su perfil propio el cambio de contraseña.
- **RF-6**: CUANDO una cuenta ingresa su contraseña actual correcta y una contraseña nueva válida, EL SISTEMA reemplazará su contraseña.
- **RF-7**: SI la contraseña actual ingresada no coincide con la de la cuenta, ENTONCES EL SISTEMA no cambiará la contraseña y mostrará el motivo en el formulario.
- **RF-8**: SI la contraseña nueva incumple las reglas de contraseña que rigen su definición por enlace, ENTONCES EL SISTEMA no cambiará la contraseña y mostrará el motivo en el formulario.
- **RF-9**: SI la cuenta no tiene contraseña definida, ENTONCES EL SISTEMA no ofrecerá el cambio de contraseña e indicará que debe definirla con la recuperación por email.
- **RF-10**: CUANDO una cuenta cambia su contraseña, EL SISTEMA cerrará todas sus otras sesiones abiertas y mantendrá la sesión desde la que se hizo el cambio.

### Edición de otras cuentas por un admin

- **RF-11**: MIENTRAS un admin tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá editar el nombre y el email de cualquier cuenta distinta de la propia desde el listado de cuentas.
- **RF-12**: EL SISTEMA no permitirá a un admin modificar su propio email.
- **RF-13**: SI un usuario sin rol `admin` solicita editar los datos de otra cuenta, ENTONCES EL SISTEMA lo rechazará y mostrará un mensaje de permiso insuficiente.
- **RF-14**: SI el email nuevo coincide con el de otra cuenta, incluidas las inactivas (comparación ignorando mayúsculas y espacios exteriores), ENTONCES EL SISTEMA no guardará el cambio y mostrará el mensaje de conflicto en el formulario, indicando la cuenta existente.
- **RF-15**: SI el email nuevo está vacío o no tiene formato de email válido, ENTONCES EL SISTEMA no guardará el cambio y mostrará el motivo en el formulario.
- **RF-16**: CUANDO un admin cambia el email de una cuenta, EL SISTEMA identificará la cuenta por el email nuevo, y no por el anterior, tanto en el ingreso con contraseña como en el ingreso con Google.
- **RF-17**: CUANDO un admin cambia el email de una cuenta, EL SISTEMA enviará un aviso del cambio al email anterior y otro al email nuevo.
- **RF-18**: CUANDO un admin cambia el email de una cuenta, EL SISTEMA invalidará los enlaces de definición de contraseña vigentes sin usar de esa cuenta.
- **RF-19**: CUANDO un admin cambia el email de una cuenta con sesión abierta, EL SISTEMA mantendrá esa sesión abierta.

### Historial

- **RF-20**: CUANDO se cambia el nombre de una cuenta, EL SISTEMA registrará en el historial de cuentas el nombre anterior y el nuevo, con autor y fecha.
- **RF-21**: CUANDO se cambia el email de una cuenta, EL SISTEMA registrará en el historial de cuentas el email anterior y el nuevo, con autor y fecha.
- **RF-22**: CUANDO una cuenta cambia su contraseña, EL SISTEMA registrará en el historial de cuentas el hecho, con autor y fecha, sin guardar ningún valor de contraseña.
- **RF-23**: SI se guarda la edición de una cuenta sin ningún cambio respecto de los datos vigentes, ENTONCES EL SISTEMA no registrará ningún asiento.
- **RF-24**: MIENTRAS un admin tenga sesión iniciada en el portal de staff, EL SISTEMA mostrará en el historial de cuentas, junto a los demás asientos, los cambios de nombre, de email y de contraseña.

## 8. Requisitos no funcionales

Ninguno: esta spec no fija umbrales de volumen ni de tiempo.

## 9. Fuera de alcance

- **Cambio de email desde el perfil propio** — se decidió que lo cambia solo un admin, para evitar bloqueos y emails sin verificar; confirmación por enlace al email nuevo queda para una spec posterior si hace falta.
- **Cambio del email propio por un admin** — lo hace otro admin, coherente con RF-11 de la spec 001.
- **Fijar contraseña desde el perfil en una cuenta sin contraseña** — se usa la recuperación por email de la spec 001.
- **Edición de la ficha del cliente** (dirección, contactos) — pertenece a la spec 003.
- **Cambio de rol y activación o desactivación de cuentas** — ya cubiertos por la spec 001.
- **Foto o avatar y otros datos de perfil** (teléfono, cargo) — no hay caso de uso conocido.
- **Eliminación de cuentas** — las cuentas no se eliminan (RF-34 de la spec 001).
- **Segundo factor de autenticación** — endurecimiento posterior.

## 10. Criterios de finalización

- Todos los RF tienen tests que pasan.
- Una cuenta de cada rol puede cambiar su nombre y su contraseña desde el perfil de su portal.
- Un admin puede cambiar el email de otra cuenta y esa persona ingresa con el email nuevo, no con el anterior.
- Ningún rol distinto de admin puede editar otra cuenta ni el email de ninguna.
- Los cambios de nombre, email y contraseña aparecen en el historial de cuentas sin exponer contraseñas.

## 11. Dependencias y supuestos

- Requiere la spec 001: cuentas, roles, historial de cuentas, enlaces de definición de contraseña, ingreso con Google.
- Requiere un canal de envío de email operativo para los avisos del cambio de email.
- Se asume que el aviso al email anterior es la única barrera contra un cambio indebido de email.

## 12. Preguntas abiertas

Ninguna.

## 13. Aprobación

- [x] Aprobada por Claudio el 2026-10-27
