# 001 — Acceso, roles y paneles

| | |
|---|---|
| **Estado** | Aprobada |
| **Fecha** | 2026-09-10 |
| **Autor** | Claudio |
| **Rama propuesta** | v00.01.00-acceso-roles-y-paneles |

## 1. Problema

Artemisia expone tres superficies en una misma aplicación —landing pública, portal de staff y
portal de clientes— pero hoy no hay ninguna definición de quién puede entrar a cada una ni cómo
obtiene su acceso. Sin eso, cualquier módulo posterior (presupuestos, piezas, pagos) no tiene
sobre qué apoyar sus permisos y no se puede probar de punta a punta.

## 2. Objetivo

Que cada persona entre únicamente al portal que le corresponde, con una cuenta que la agencia
controla de forma explícita.

Se sabrá que se logró cuando:

- Un admin pueda dar de alta una cuenta y esa persona ingrese sin intervención adicional.
- Un intento de ingreso a un portal ajeno sea rechazado y quede registrado.
- Desactivar una cuenta corte el acceso, incluida la sesión abierta.

## 3. Actores

- **Admin** — da de alta cuentas, les asigna rol, las activa y desactiva; trabaja en el portal de staff.
- **Staff** — miembro del equipo de la agencia; trabaja en el portal de staff y no gestiona cuentas.
- **Cliente** — persona de una empresa cliente; accede al portal de clientes.
- **Visitante** — cualquiera sin sesión iniciada; solo ve la landing pública.

## 4. Glosario

- **Cuenta** — identidad con la que una persona ingresa al sistema, identificada por su email.
- **Rol** — categoría única de una cuenta: `admin`, `staff` o `cliente`.
- **Cuenta activa / inactiva** — cuenta habilitada o deshabilitada para ingresar; la inactiva se conserva con todo su historial.
- **Portal de staff** — superficie interna de la agencia, en `/staff`.
- **Portal de clientes** — superficie del cliente, en `/portal`.
- **Landing pública** — superficie anónima, en `/`.
- **Enlace de definición de contraseña** — enlace de un solo uso y vigencia limitada que permite establecer la contraseña de una cuenta; se envía al crear la cuenta y también cada vez que se solicita recuperar la contraseña.
- **Historial de cuentas** — asientos de cada alta, activación, desactivación o cambio de rol sobre una cuenta, con autor y fecha.
- **Registro de acceso** — asiento de un ingreso o de un intento de ingreso rechazado.

## 5. Alcance

Alta y gestión de cuentas por parte de un admin; ingreso con Google o con email y contraseña desde
la pantalla de acceso propia de cada portal; rechazo de accesos cruzados, de cuentas inactivas y de
emails no habilitados; definición y recuperación de contraseña por email; activación y
desactivación de cuentas con corte de la sesión abierta; y registro de cambios sobre las cuentas,
ingresos exitosos e intentos fallidos.

## 6. Requisitos funcionales

### Alta y gestión de cuentas

- **RF-1**: EL SISTEMA no ofrecerá ningún mecanismo de registro público de cuentas.
- **RF-2**: MIENTRAS un admin tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá crear cuentas indicando nombre completo, email y un rol entre `admin`, `staff` y `cliente`.
- **RF-3**: EL SISTEMA asignará a cada cuenta exactamente un rol.
- **RF-4**: SI el email de una cuenta nueva coincide con el de una cuenta existente, incluidas las inactivas (comparación ignorando mayúsculas y espacios exteriores), ENTONCES EL SISTEMA no la creará y mostrará el mensaje de conflicto en el formulario, indicando la cuenta existente.
- **RF-5**: CUANDO se crea una cuenta, EL SISTEMA la dejará activa y enviará a su email un enlace de definición de contraseña.
- **RF-6**: MIENTRAS un admin tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá cambiar el rol de cualquier cuenta distinta de la propia.
- **RF-7**: CUANDO se cambia el rol de una cuenta con sesión abierta, EL SISTEMA aplicará el nuevo rol en la siguiente solicitud de esa cuenta.
- **RF-8**: MIENTRAS un usuario con rol `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA no mostrará la sección de gestión de cuentas y roles.
- **RF-9**: SI un usuario sin rol `admin` solicita una acción de gestión de cuentas o roles, ENTONCES EL SISTEMA la rechazará y mostrará un mensaje de permiso insuficiente.
- **RF-10**: SI una operación dejaría al sistema sin ninguna cuenta `admin` activa, ENTONCES EL SISTEMA la impedirá y mostrará el motivo.
- **RF-11**: SI un admin intenta desactivar su propia cuenta o cambiar su propio rol, ENTONCES EL SISTEMA lo impedirá y mostrará el motivo.

### Ingreso

- **RF-12**: EL SISTEMA ofrecerá una pantalla de acceso propia en el portal de staff y otra en el portal de clientes.
- **RF-13**: EL SISTEMA ofrecerá en cada pantalla de acceso el ingreso con email y contraseña y el ingreso con Google.
- **RF-14**: CUANDO alguien ingresa con Google, EL SISTEMA identificará la cuenta por el email que devuelve Google, sin restringir el ingreso a ningún dominio de correo en particular.
- **RF-15**: SI el proceso de ingreso con Google falla o es cancelado antes de completarse, ENTONCES EL SISTEMA devolverá a la pantalla de acceso sin crear ninguna cuenta.
- **RF-16**: CUANDO una cuenta activa ingresa correctamente por la pantalla de acceso de su portal, EL SISTEMA la llevará a la página inicial de ese portal.
- **RF-17**: SI el email devuelto por Google no corresponde a ninguna cuenta, ENTONCES EL SISTEMA no creará ninguna cuenta y devolverá a la pantalla de acceso indicando que esa cuenta no está habilitada y que debe contactar a la agencia.
- **RF-18**: SI el email y la contraseña no coinciden con ninguna cuenta, ENTONCES EL SISTEMA devolverá a la pantalla de acceso con un mensaje genérico de credenciales inválidas, sin revelar si el email existe.
- **RF-19**: EL SISTEMA evaluará, en este orden, si las credenciales o el ingreso con Google corresponden a una cuenta, si esa cuenta está activa, y si su rol corresponde al portal solicitado, deteniéndose en la primera condición que no se cumpla.
- **RF-20**: SI una cuenta cuyas credenciales o cuyo ingreso con Google son correctos está inactiva, ENTONCES EL SISTEMA rechazará su ingreso por cualquiera de los dos métodos e indicará que la cuenta está deshabilitada.
- **RF-21**: SI una cuenta con rol `cliente` intenta ingresar por la pantalla de acceso del portal de staff, ENTONCES EL SISTEMA rechazará el ingreso e indicará que esa cuenta no accede a ese portal.
- **RF-22**: SI una cuenta con rol `admin` o `staff` intenta ingresar por la pantalla de acceso del portal de clientes, ENTONCES EL SISTEMA rechazará el ingreso e indicará que esa cuenta no accede a ese portal.
- **RF-23**: SI un visitante sin sesión solicita cualquier ruta de un portal, ENTONCES EL SISTEMA lo llevará a la pantalla de acceso de ese portal.
- **RF-24**: SI una cuenta con sesión iniciada solicita una ruta del portal que no corresponde a su rol, ENTONCES EL SISTEMA le negará el acceso y mostrará un mensaje de acceso no permitido.
- **RF-25**: EL SISTEMA permitirá el acceso anónimo a la landing pública.
- **RF-26**: CUANDO un usuario cierra sesión, EL SISTEMA lo devolverá a la pantalla de acceso de su portal.

### Contraseña

- **RF-27**: CUANDO alguien usa un enlace de definición de contraseña vigente, EL SISTEMA le permitirá establecer su contraseña e invalidará el enlace.
- **RF-28**: SI el enlace de definición de contraseña está vencido o ya fue usado, ENTONCES EL SISTEMA rechazará la operación y ofrecerá solicitar uno nuevo.
- **RF-29**: EL SISTEMA ofrecerá en cada pantalla de acceso la recuperación de contraseña por email, enviando un nuevo enlace de definición de contraseña.
- **RF-30**: SI se solicita recuperación para un email que no corresponde a una cuenta activa, ENTONCES EL SISTEMA mostrará el mismo mensaje de confirmación que en el caso exitoso y no enviará ningún correo.
- **RF-31**: SI se desactiva una cuenta que tiene un enlace de definición de contraseña vigente sin usar, ENTONCES EL SISTEMA invalidará ese enlace.

### Desactivación

- **RF-32**: MIENTRAS un admin tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá activar y desactivar cualquier cuenta distinta de la propia.
- **RF-33**: CUANDO se desactiva una cuenta con sesión abierta, EL SISTEMA cerrará esa sesión en la siguiente solicitud que realice y la devolverá a la pantalla de acceso.
- **RF-34**: EL SISTEMA conservará las cuentas desactivadas junto con su historial, sin eliminarlas.

### Registro

- **RF-35**: CUANDO se crea una cuenta, se activa, se desactiva o se cambia su rol, EL SISTEMA registrará el hecho con autor y fecha.
- **RF-36**: CUANDO una cuenta ingresa correctamente, EL SISTEMA registrará un asiento nuevo con la fecha, la hora, el portal y el método de ingreso, sin sobrescribir los anteriores.
- **RF-37**: CUANDO EL SISTEMA rechaza un intento de ingreso, registrará el email usado, el motivo del rechazo, el portal, la fecha y la hora.
- **RF-38**: MIENTRAS un admin tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá consultar los registros de acceso y el historial de cuentas.
- **RF-39**: SI un usuario sin rol `admin` solicita consultar los registros de acceso o el historial de cuentas, ENTONCES EL SISTEMA la rechazará y mostrará un mensaje de permiso insuficiente.

## 7. Requisitos no funcionales

- **RNF-1**: EL SISTEMA exigirá contraseñas de al menos 8 caracteres.
- **RNF-2**: SI se registran 5 intentos de ingreso fallidos con email y contraseña contra el mismo email dentro de 1 minuto, ENTONCES EL SISTEMA rechazará todo intento adicional contra ese email durante los 60 segundos siguientes al último intento, y volverá a aceptarlos automáticamente al vencer ese plazo.
- **RNF-3**: EL SISTEMA mantendrá vigente un enlace de definición de contraseña durante 24 horas desde su envío.
- **RNF-4**: EL SISTEMA cerrará por inactividad toda sesión, en cualquiera de los dos portales, que no tenga actividad durante 120 minutos.
- **RNF-5**: EL SISTEMA conservará los registros de acceso (ingresos exitosos e intentos fallidos) durante 24 meses desde su creación.
- **RNF-6**: EL SISTEMA conservará el historial de cuentas sin purgarlo en ningún plazo.

## 8. Fuera de alcance

- **Vinculación entre una cuenta con rol `cliente` y la entidad cliente de la agencia** — se define en la spec `003-clientes`; acá solo se establece el rol y el acceso al portal.
- **Contenido y funcionalidad interna de cada portal** — cada módulo trae su propia spec.
- **Contenido de la landing pública** — solo se define que es anónima.
- **Reparto fino de permisos por módulo** — cada spec define qué puede hacer cada rol dentro de su módulo.
- **Más de un rol por cuenta** — no hay caso de uso conocido; si aparece, spec aparte.
- **Segundo factor de autenticación** — endurecimiento posterior.
- **Invitaciones por email con aceptación previa** — se descartó: la cuenta nace activa.
- **Edición del perfil propio** (cambiar nombre, email o contraseña estando dentro) — spec posterior; hoy la contraseña se cambia por el flujo de recuperación.
- **Proveedores de identidad distintos de Google** — no previstos.

## 9. Dependencias y supuestos

- Requiere credenciales de Google OAuth configuradas en el entorno.
- Requiere un canal de envío de email operativo; sin él, no hay definición ni recuperación de contraseña.
- La primera cuenta `admin` del sistema nace por un comando o seed ejecutado al instalar el sistema, fuera de la interfaz web; es un paso de instalación y no un requisito funcional de esta spec. A partir de esa cuenta, toda cuenta nueva se crea según RF-2.
- Se supone que los roles `admin`, `staff` y `cliente` son datos de referencia fijos: no se crean roles nuevos desde la interfaz.
- Se supone que un email pertenece a una sola persona y no se reutiliza entre cuentas.

## 10. Preguntas abiertas

Ninguna: todas se resolvieron durante la entrevista y quedaron incorporadas en las secciones
anteriores.

## 11. Aprobación

- [x] Aprobada por Claudio el 2026-09-10
