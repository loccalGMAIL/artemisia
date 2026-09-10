# 004 — Piezas

| | |
|---|---|
| **Estado** | Aprobada |
| **Fecha** | 2026-09-10 |
| **Autor** | Claudio |
| **Rama propuesta** | v00.04.00-piezas |

## 1. Problema

Un presupuesto aceptado en el sistema queda ahí: no hay ningún lugar donde se sepa qué hay que
producir concretamente, quién lo tiene que hacer, para cuándo, ni si el cliente ya lo aprobó. Hoy
esa coordinación se hace por fuera del sistema (chats, planillas sueltas), así que se pierde de
vista qué está atrasado, quién es responsable de cada entrega, y cuando hay un reclamo no hay
forma de reconstruir qué versión aprobó realmente el cliente.

## 2. Objetivo

Que cada presupuesto aceptado se traduzca en piezas de trabajo concretas, con un responsable, una
fecha comprometida y un estado que se pueda seguir hasta que el cliente la apruebe y se entregue.

Se sabrá que se logró cuando:

- Un presupuesto aceptado tenga piezas generadas, cada una con responsable y fecha de entrega.
- El cliente pueda aprobar o rechazar una pieza desde su portal, y un rechazo la devuelva
  automáticamente a producción.
- Se pueda reconstruir, para cualquier pieza, cada envío a aprobación y su resultado.
- El listado de piezas muestre qué está atrasado y de quién depende.

## 3. Actores

- **Admin** — genera piezas, las delega, les carga fecha de entrega y sigue su producción; trabaja en el portal de staff.
- **Staff** — genera piezas, las delega, les carga fecha de entrega y sigue su producción; trabaja en el portal de staff.
- **Cliente** — aprueba o rechaza las piezas que le corresponden desde el portal de clientes.

## 4. Glosario

Los términos de cuenta, rol, portal de staff y portal de clientes están definidos en la spec
`001-acceso-roles-y-paneles`. Los términos de presupuesto, ítem, servicio y categoría de trabajo
están definidos en la spec `002-presupuestos`. Los términos de cliente y vínculo cuenta-cliente
están definidos en la spec `003-clientes`.

- **Pieza** — entregable concreto de producción que resulta de un ítem de un presupuesto
  aceptado, o que se crea suelta directamente asociada a un presupuesto aceptado.
- **Pieza suelta** — pieza sin ítem de origen.
- **Estado de la pieza** — situación de producción de una pieza: pendiente, en producción, en
  revisión, en aprobación del cliente, aprobada o entregada.
- **Responsable** — cuenta con rol `admin` o `staff` delegada para producir una pieza.
- **Fecha de entrega comprometida** — fecha objetivo en la que la pieza debería estar lista.
- **Envío a aprobación** — instancia en la que una pieza, con un archivo, se manda a que el
  cliente la apruebe o la rechace.
- **Motivo de rechazo** — texto opcional que el cliente puede dejar al rechazar un envío a
  aprobación.

## 5. Alcance

Generación de piezas a partir de los ítems de un presupuesto aceptado, con posibilidad de dividir
su cantidad en varias piezas o de crear piezas sueltas; delegación de un responsable y carga de
una fecha de entrega comprometida; ciclo de estados de producción desde pendiente hasta entregada,
con reversión automática a producción ante un rechazo del cliente; envío a aprobación con un
archivo adjunto y conservación de cada envío y su resultado; aprobación o rechazo por parte del
cliente vinculado desde el portal de clientes, limitado a lo que le corresponde ver; listado y
consulta de piezas para el staff, con filtros por estado, responsable y cliente; y descarte de
piezas pendientes sin eliminación física.

## 6. Requisitos funcionales

### Generación de piezas

- **RF-1**: CUANDO se acepta un presupuesto, EL SISTEMA le propondrá al staff una pieza por cada
  ítem, con el nombre y la cantidad del ítem.
- **RF-2**: EL SISTEMA permitirá modificar la propuesta antes de confirmarla: dividir la cantidad
  de una pieza propuesta en varias piezas, o quitar piezas de la propuesta.
- **RF-3**: EL SISTEMA asociará cada pieza generada a partir de un ítem con el ítem del que
  proviene.
- **RF-4**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de
  staff, EL SISTEMA le permitirá confirmar la propuesta y crear las piezas.
- **RF-5**: EL SISTEMA permitirá crear piezas sueltas, sin ítem de origen, asociadas directamente
  a un presupuesto aceptado.
- **RF-6**: CUANDO se crea una pieza, EL SISTEMA exigirá que su presupuesto esté en estado
  aceptado.
- **RF-7**: SI se intenta generar piezas a partir de un presupuesto que no está aceptado,
  ENTONCES EL SISTEMA lo impedirá y mostrará el motivo.
- **RF-8**: EL SISTEMA no alterará las piezas ya generadas de un presupuesto si ese presupuesto
  vuelve al estado enviado o si sus ítems se modifican después.

### Datos y clasificación

- **RF-9**: EL SISTEMA registrará en cada pieza un nombre y, opcionalmente, una descripción.
- **RF-10**: EL SISTEMA asignará a cada pieza la categoría de trabajo del servicio del que
  proviene; DONDE la pieza sea suelta, exigirá que se le indique una categoría de trabajo.

### Delegación y agenda

- **RF-11**: MIENTRAS una pieza no esté entregada, EL SISTEMA permitirá a un usuario con rol
  `admin` o `staff` delegarla a una cuenta con rol `admin` o `staff`.
- **RF-12**: EL SISTEMA admitirá a lo sumo un responsable delegado por pieza.
- **RF-13**: CUANDO se delega una pieza, EL SISTEMA la incluirá en el listado de piezas delegadas
  a esa cuenta, disponible bajo el filtro de "mis piezas delegadas".
- **RF-14**: MIENTRAS una pieza no esté entregada, EL SISTEMA permitirá reasignar su responsable.
- **RF-15**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de
  staff, EL SISTEMA le permitirá cargar y modificar la fecha de entrega comprometida de una
  pieza.
- **RF-16**: EL SISTEMA no exigirá fecha de entrega comprometida para crear una pieza.
- **RF-17**: MIENTRAS la fecha de entrega comprometida de una pieza haya pasado y la pieza no
  esté entregada, EL SISTEMA la señalará como atrasada.

### Estados de producción

- **RF-18**: EL SISTEMA asignará a cada pieza exactamente un estado entre pendiente, en
  producción, en revisión, en aprobación del cliente, aprobada y entregada.
- **RF-19**: CUANDO se crea una pieza, EL SISTEMA la dejará en estado pendiente.
- **RF-20**: MIENTRAS una pieza esté pendiente, EL SISTEMA permitirá marcarla como en producción.
- **RF-21**: MIENTRAS una pieza esté en producción, EL SISTEMA permitirá marcarla como en
  revisión.
- **RF-22**: MIENTRAS una pieza esté en revisión, EL SISTEMA permitirá enviarla a aprobación del
  cliente.
- **RF-23**: CUANDO se envía una pieza a aprobación del cliente, EL SISTEMA la dejará en estado
  en aprobación del cliente y registrará el envío con su archivo y su fecha.
- **RF-24**: MIENTRAS una pieza esté aprobada, EL SISTEMA permitirá a un usuario con rol `admin`
  o `staff` marcarla como entregada.
- **RF-25**: MIENTRAS una pieza esté entregada, EL SISTEMA no permitirá modificar su estado, su
  archivo ni su responsable.
- **RF-26**: MIENTRAS una pieza esté pendiente, EL SISTEMA permitirá descartarla.
- **RF-27**: CUANDO se descarta una pieza, EL SISTEMA la conservará junto con su historial y
  dejará de mostrarla en el listado de piezas.

### Envío a aprobación y archivo

- **RF-28**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de
  staff, EL SISTEMA le permitirá subir un archivo a una pieza al enviarla a aprobación del
  cliente.
- **RF-29**: EL SISTEMA admitirá en cada envío a aprobación un único archivo, en formato JPG,
  PNG, PDF o MP4.
- **RF-30**: EL SISTEMA conservará cada archivo enviado a aprobación junto con su fecha, aunque
  la pieza reciba más de un envío.
- **RF-31**: SI un archivo no cumple el formato o el tamaño admitido, ENTONCES EL SISTEMA lo
  rechazará y mostrará el motivo.

### Aprobación del cliente

- **RF-32**: MIENTRAS una pieza esté en estado en aprobación del cliente, EL SISTEMA permitirá a
  una cuenta con rol `cliente` vinculada al cliente destinatario del presupuesto aprobarla o
  rechazarla.
- **RF-33**: CUANDO una cuenta con rol `cliente` aprueba una pieza, EL SISTEMA la dejará en
  estado aprobada y registrará la fecha de la aprobación.
- **RF-34**: CUANDO una cuenta con rol `cliente` rechaza una pieza, EL SISTEMA la devolverá al
  estado en producción y registrará el motivo de rechazo, la fecha, y a qué envío corresponde.
- **RF-35**: EL SISTEMA aceptará vacío el motivo de rechazo.
- **RF-36**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de
  staff, EL SISTEMA le permitirá consultar el historial completo de envíos a aprobación de una
  pieza, con su archivo, su resultado y, si corresponde, su motivo de rechazo.
- **RF-37**: MIENTRAS una cuenta con rol `cliente` esté vinculada al cliente destinatario de una
  pieza, EL SISTEMA le permitirá consultar los envíos a aprobación de esa pieza que ella misma
  resolvió, incluidos los rechazados, aunque la pieza haya vuelto a producción.

### Historial

- **RF-38**: CUANDO se crea una pieza, se le cambia el estado, se le asigna o cambia el
  responsable, o se le carga o modifica la fecha de entrega, EL SISTEMA registrará el hecho con
  el valor anterior, el valor nuevo, el autor y la fecha.
- **RF-39**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de
  staff, EL SISTEMA le permitirá consultar el historial de una pieza en orden cronológico.
- **RF-40**: EL SISTEMA no eliminará ninguna pieza ni ningún asiento de su historial.

### Listado y consulta

- **RF-41**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de
  staff, EL SISTEMA le mostrará el listado de piezas con su nombre, su presupuesto, su cliente,
  su estado, su responsable y su fecha de entrega comprometida.
- **RF-42**: EL SISTEMA permitirá filtrar el listado de piezas por estado, por responsable y por
  cliente.
- **RF-43**: EL SISTEMA permitirá a cada usuario con rol `admin` o `staff` ver, bajo un filtro
  específico, únicamente las piezas delegadas a su propia cuenta.
- **RF-44**: EL SISTEMA permitirá filtrar el listado de piezas por piezas atrasadas.

### Portal de clientes

- **RF-45**: MIENTRAS una cuenta con rol `cliente` tenga sesión iniciada en el portal de
  clientes, EL SISTEMA le mostrará únicamente las piezas del cliente al que está vinculada que
  estén en aprobación del cliente, aprobadas o entregadas.
- **RF-46**: EL SISTEMA no mostrará en el portal de clientes las piezas en estado pendiente, en
  producción ni en revisión.
- **RF-47**: SI una cuenta con rol `cliente` intenta aprobar o rechazar una pieza que no está en
  aprobación del cliente, ENTONCES EL SISTEMA la rechazará y mostrará un mensaje de permiso
  insuficiente.
- **RF-48**: SI una cuenta con rol `cliente` intenta actuar sobre una pieza de un cliente
  distinto de aquel al que está vinculada, ENTONCES EL SISTEMA la rechazará y mostrará un mensaje
  de permiso insuficiente.

## 7. Requisitos no funcionales

- **RNF-1**: EL SISTEMA rechazará cualquier archivo que supere los 100 MB.
- **RNF-2**: EL SISTEMA mostrará el listado de piezas, con 5.000 piezas cargadas, en menos de 2
  segundos.
- **RNF-3**: EL SISTEMA conservará el historial de una pieza y sus envíos a aprobación sin
  purgarlos en ningún plazo.

## 8. Fuera de alcance

- **Sesiones de fotografía o grabación con fecha y hora propia** — mencionadas en el dominio del
  proyecto; spec posterior si se necesitan además de la fecha de entrega comprometida.
- **Canal de aviso al delegar una pieza** (email, notificación push, etc.) — el sistema registra
  la delegación y la muestra entre las piezas de esa cuenta, pero no define cómo se le avisa.
- **Reportes de productividad por responsable** — spec posterior.
- **Comentarios o intercambio entre cliente y staff sobre una pieza, más allá del motivo de
  rechazo** — no previsto.
- **Herramientas de comparación o anotación entre versiones de archivo** — el sistema conserva
  cada archivo enviado, pero no ofrece compararlos ni anotarlos.
- **Integración con herramientas externas de diseño** (Canva, Google Drive, etc.) — el archivo se
  sube directo al sistema, sin integraciones.
- **Costos de producción y su relación con proveedores** — spec propia si se necesita.
- **Facturación y pagos asociados a una pieza** — van en las specs de Contratos y Pagos.
- **Aprobación conjunta de varias piezas en un solo paso** — cada pieza se aprueba o rechaza de
  forma individual.

## 9. Dependencias y supuestos

- Requiere la spec `001-acceso-roles-y-paneles`: los roles `admin`, `staff` y `cliente`, y los
  portales de staff y de clientes.
- Requiere la spec `002-presupuestos`: un presupuesto aceptado con sus ítems, servicios y
  categorías de trabajo.
- Requiere la spec `003-clientes`: el vínculo entre una cuenta con rol `cliente` y el cliente
  destinatario del presupuesto, para mostrarle únicamente lo que le corresponde.
- Se supone que las categorías de trabajo ya sembradas por la spec `002` alcanzan para clasificar
  también las piezas sueltas.

## 10. Preguntas abiertas

Ninguna: todas se resolvieron durante la entrevista y quedaron incorporadas en las secciones
anteriores.

## 11. Aprobación

- [x] Aprobada por Claudio el 2026-09-10
