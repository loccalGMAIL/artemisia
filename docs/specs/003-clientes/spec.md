# 003 — Clientes

| | |
|---|---|
| **Estado** | Aprobada |
| **Fecha** | 2026-09-10 |
| **Autor** | Claudio |
| **Rama propuesta** | v00.03.00-clientes |

## 1. Problema

La agencia trabaja con clientes desde antes de tener sistema, pero hoy esa información vive
repartida entre contactos sueltos, planillas y la memoria de quien los atendió. La spec de acceso
ya reservó un rol `cliente` para el portal, y la de presupuestos ya necesita dirigir una propuesta
a un cliente existente, pero ninguna de las dos define qué es un cliente para la agencia, qué datos
tiene, ni cómo una persona que ingresa al portal de clientes queda asociada a él. Sin esa entidad,
no hay a quién dirigir un presupuesto, no hay ficha que reúna el trato comercial con cada cliente,
y una cuenta de portal no tiene forma de saber de qué cliente es.

## 2. Objetivo

Que cada cliente de la agencia exista como un registro único, con su información de contacto y
domicilio, su estado y su historial, y que quien ingrese al portal de clientes vea únicamente su
propia información.

Se sabrá que se logró cuando:

- El staff pueda dar de alta un cliente y elegirlo al crear un presupuesto.
- Un cliente inactivo o archivado deje de ofrecerse para presupuestos nuevos sin perder su ficha.
- Una cuenta del portal de clientes, vinculada a un cliente, vea y pueda actualizar su domicilio y
  sus contactos, y no vea la información de ningún otro cliente.
- Cualquier cambio sobre un cliente se pueda reconstruir: qué valor tenía, quién lo cambió y cuándo.

## 3. Actores

- **Admin** — da de alta y administra clientes, sus contactos y su vínculo con cuentas del portal de clientes; trabaja en el portal de staff.
- **Staff** — da de alta y administra clientes, sus contactos y su vínculo con cuentas del portal de clientes; trabaja en el portal de staff.
- **Cliente** — persona con una cuenta vinculada a un cliente de la agencia; consulta y actualiza su propia ficha desde el portal de clientes.

## 4. Glosario

Los términos de cuenta, rol, portal de staff y portal de clientes están definidos en la spec
`001-acceso-roles-y-paneles`.

- **Cliente** — persona física o jurídica con la que la agencia tiene o tuvo trato comercial.
- **Persona física** — cliente identificado por nombre, apellido y DNI.
- **Persona jurídica** — cliente identificado por razón social y CUIT.
- **Documento** — DNI de una persona física o CUIT de una persona jurídica.
- **Nombre para mostrar** — nombre y apellido de una persona física, o razón social de una persona jurídica; es el nombre con el que el cliente aparece en listados, fichas y documentos.
- **Domicilio** — dirección postal del cliente: calle, número, localidad, provincia y código postal.
- **Contacto** — persona a través de la cual se comunica con el cliente, con nombre, cargo, teléfono y email.
- **Contacto principal** — el único contacto de un cliente usado por defecto para comunicarse con él.
- **Cliente activo / inactivo** — cliente disponible o no disponible para elegir en presupuestos nuevos; ambos estados conservan ficha e historial.
- **Cliente archivado** — cliente retirado del listado y de la operación diaria, marcado también como inactivo, conservado con su ficha e historial y restaurable.
- **Vínculo cuenta-cliente** — asociación entre una cuenta con rol `cliente` y el cliente de la agencia al que representa.
- **Historial del cliente** — asientos de cada alta y cada cambio sobre un cliente, con valor anterior, valor nuevo, autor y fecha.

## 5. Alcance

Alta de clientes como persona física o jurídica con su documento; carga y edición de un domicilio y
de uno o varios contactos, con exactamente uno marcado como principal; estado activo o inactivo de
cada cliente; archivado y restauración sin eliminación física; vinculación y desvinculación de
cuentas con rol `cliente` a un cliente de la agencia, admitiendo varias cuentas por cliente; ficha
del cliente con su identificación, domicilio, contactos, estado y, cuando existan los módulos
correspondientes, su historial de presupuestos, contratos y pagos; historial de cambios sobre cada
cliente; listado de clientes con búsqueda, filtros, orden, paginado y exportación; y autogestión del
cliente sobre su propio domicilio y sus propios contactos desde el portal de clientes.

## 6. Requisitos funcionales

### Alta e identificación

- **RF-1**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá dar de alta un cliente indicando su tipo de persona, física o jurídica.
- **RF-2**: EL SISTEMA asignará a cada cliente exactamente un tipo de persona entre física y jurídica.
- **RF-3**: DONDE el cliente sea persona física, EL SISTEMA exigirá nombre, apellido y DNI.
- **RF-4**: DONDE el cliente sea persona jurídica, EL SISTEMA exigirá razón social y CUIT.
- **RF-5**: EL SISTEMA calculará el nombre para mostrar de cada cliente a partir de su nombre y apellido, o de su razón social, según su tipo de persona.
- **RF-6**: SI el documento de un cliente nuevo coincide con el de un cliente existente, incluidos los archivados (comparación ignorando espacios, puntos y guiones), ENTONCES EL SISTEMA no lo creará y mostrará el mensaje de conflicto en el formulario, indicando el cliente existente.
- **RF-7**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá modificar el nombre, el apellido, la razón social y el documento de un cliente.
- **RF-8**: SI se intenta cambiar el tipo de persona de un cliente existente, ENTONCES EL SISTEMA lo impedirá e indicará que debe darse de alta un cliente nuevo.

### Domicilio

- **RF-9**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá cargar en un cliente un domicilio con calle, número, localidad, provincia y código postal.
- **RF-10**: EL SISTEMA no exigirá domicilio para dar de alta un cliente.
- **RF-11**: EL SISTEMA admitirá a lo sumo un domicilio por cliente.
- **RF-12**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá modificar el domicilio de un cliente.

### Contactos

- **RF-13**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá agregar a un cliente contactos con nombre, cargo, teléfono y email.
- **RF-14**: EL SISTEMA no exigirá ningún contacto para dar de alta un cliente.
- **RF-15**: EL SISTEMA exigirá en cada contacto al menos un teléfono o un email.
- **RF-16**: CUANDO se agrega el primer contacto de un cliente, EL SISTEMA lo marcará como principal.
- **RF-17**: DONDE un cliente tenga más de un contacto, EL SISTEMA mantendrá marcado exactamente uno de ellos como principal.
- **RF-18**: EL SISTEMA permitirá cambiar cuál de los contactos de un cliente es el principal.
- **RF-19**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá modificar y quitar los contactos de un cliente.
- **RF-20**: SI se quita el contacto principal de un cliente que conserva otros contactos, ENTONCES EL SISTEMA marcará otro de ellos como principal.
- **RF-21**: EL SISTEMA identificará como teléfono de contacto del cliente el teléfono de su contacto principal; si el contacto principal no tiene teléfono cargado, tomará el teléfono de otro de sus contactos que sí lo tenga.
- **RF-22**: SI ningún contacto de un cliente tiene teléfono cargado, ENTONCES EL SISTEMA indicará que el cliente no tiene teléfono de contacto.

### Estado activo e inactivo

- **RF-23**: EL SISTEMA asignará a cada cliente exactamente un estado entre activo e inactivo.
- **RF-24**: CUANDO se crea un cliente, EL SISTEMA lo dejará activo.
- **RF-25**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá marcar un cliente como inactivo y reactivarlo.
- **RF-26**: MIENTRAS un cliente esté inactivo, EL SISTEMA no lo ofrecerá para elegir como destinatario de un presupuesto nuevo.
- **RF-27**: EL SISTEMA mantendrá consultable la ficha y el historial de un cliente inactivo.

### Archivado

- **RF-28**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá archivar un cliente.
- **RF-29**: CUANDO se archiva un cliente, EL SISTEMA lo marcará también como inactivo.
- **RF-30**: CUANDO se archiva un cliente, EL SISTEMA dejará de mostrarlo en el listado de clientes y no lo ofrecerá como destinatario de un presupuesto nuevo.
- **RF-31**: EL SISTEMA conservará un cliente archivado junto con su ficha y su historial, sin eliminarlo.
- **RF-32**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá restaurar un cliente archivado.
- **RF-33**: CUANDO se restaura un cliente archivado, EL SISTEMA lo dejará inactivo.
- **RF-34**: EL SISTEMA no eliminará ningún cliente ni ningún asiento de su historial.

### Historial de cambios

- **RF-35**: CUANDO se crea un cliente, EL SISTEMA registrará el alta con el autor y la fecha.
- **RF-36**: CUANDO se modifica la identificación, el domicilio, los contactos, el estado o el archivado de un cliente, EL SISTEMA registrará el valor anterior, el valor nuevo, el autor y la fecha.
- **RF-37**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá consultar el historial de un cliente en orden cronológico.

### Vinculación con cuentas del portal

- **RF-38**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá vincular una cuenta con rol `cliente` a un cliente de la agencia.
- **RF-39**: EL SISTEMA permitirá vincular varias cuentas a un mismo cliente.
- **RF-40**: SI se intenta vincular una cuenta que ya está vinculada a otro cliente, ENTONCES EL SISTEMA lo impedirá y mostrará el motivo.
- **RF-41**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá desvincular una cuenta de un cliente.
- **RF-42**: CUANDO se desvincula una cuenta que tiene sesión abierta en el portal de clientes, EL SISTEMA cerrará esa sesión en la siguiente solicitud que realice.
- **RF-43**: SI una cuenta con rol `cliente` sin vínculo con ningún cliente ingresa al portal de clientes, ENTONCES EL SISTEMA le indicará que su acceso todavía no fue habilitado por la agencia.
- **RF-44**: CUANDO se vincula o se desvincula una cuenta, EL SISTEMA lo registrará con el autor y la fecha.

### Ficha y historial comercial

- **RF-45**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le mostrará la ficha de un cliente con su identificación, su domicilio, sus contactos, su estado y su historial.
- **RF-46**: DONDE el módulo de Presupuestos esté implementado, EL SISTEMA mostrará en la ficha del cliente sus presupuestos, con identificador, título, total, estado y fecha de emisión.
- **RF-47**: DONDE el módulo de Contratos esté implementado, EL SISTEMA mostrará en la ficha del cliente sus contratos.
- **RF-48**: DONDE el módulo de Pagos esté implementado, EL SISTEMA mostrará en la ficha del cliente su historial de pagos.

### Listado y consulta

- **RF-49**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le mostrará el listado de clientes con nombre para mostrar, tipo de persona, documento, estado y fecha de alta.
- **RF-50**: EL SISTEMA no mostrará los clientes archivados en el listado por defecto.
- **RF-51**: EL SISTEMA permitirá incluir los clientes archivados en el listado mediante un filtro explícito.
- **RF-52**: EL SISTEMA permitirá buscar en el listado de clientes por texto que, sin distinguir mayúsculas, esté contenido en el nombre para mostrar, el documento, o el teléfono o el email de alguno de sus contactos.
- **RF-53**: EL SISTEMA permitirá filtrar el listado de clientes por estado y por tipo de persona.
- **RF-54**: EL SISTEMA permitirá ordenar el listado de clientes por nombre para mostrar y por fecha de alta.
- **RF-55**: EL SISTEMA paginará el listado de clientes.
- **RF-56**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá exportar el listado de clientes resultante de la búsqueda, los filtros y el orden aplicados.

### Autogestión desde el portal de clientes

- **RF-57**: MIENTRAS una cuenta con rol `cliente` vinculada a un cliente tenga sesión iniciada en el portal de clientes, EL SISTEMA le mostrará la ficha de ese cliente.
- **RF-58**: MIENTRAS un cliente esté archivado, EL SISTEMA no permitirá a las cuentas vinculadas acceder a su ficha desde el portal de clientes.
- **RF-59**: MIENTRAS una cuenta con rol `cliente` tenga sesión iniciada en el portal de clientes, EL SISTEMA le permitirá modificar el domicilio y los contactos del cliente al que está vinculada.
- **RF-60**: EL SISTEMA no permitirá a una cuenta con rol `cliente` modificar la identificación, el estado ni el archivado de ningún cliente.
- **RF-61**: CUANDO una cuenta con rol `cliente` modifica el domicilio o los contactos de un cliente, EL SISTEMA lo registrará con esa cuenta como autor.
- **RF-62**: SI una cuenta con rol `cliente` solicita la ficha de un cliente distinto de aquel al que está vinculada, ENTONCES EL SISTEMA rechazará la solicitud y mostrará un mensaje de permiso insuficiente.

## 7. Requisitos no funcionales

- **RNF-1**: EL SISTEMA admitirá nombres, apellidos y razones sociales de hasta 150 caracteres.
- **RNF-2**: EL SISTEMA admitirá como DNI únicamente valores numéricos de 7 u 8 dígitos.
- **RNF-3**: EL SISTEMA admitirá como CUIT únicamente valores numéricos de 11 dígitos con dígito verificador válido.
- **RNF-4**: EL SISTEMA admitirá hasta 10 contactos por cliente.
- **RNF-5**: EL SISTEMA mostrará el listado de clientes, con 5.000 clientes cargados, en menos de 2 segundos.
- **RNF-6**: EL SISTEMA generará la exportación del listado de clientes, con 5.000 clientes cargados, en menos de 5 segundos.
- **RNF-7**: EL SISTEMA conservará el historial de un cliente sin purgarlo en ningún plazo.

## 8. Fuera de alcance

- **Condición frente al IVA, facturación y datos impositivos** — no se modelan en esta spec; se incorporan si una spec de Facturación los necesita.
- **Condiciones comerciales** (lista de precios, límite de crédito, condición de pago) — spec propia si se necesita.
- **Contratos y pagos** — tienen sus propias specs; acá solo se reserva el lugar en la ficha del cliente.
- **Importación masiva de clientes** — spec posterior si el volumen de carga inicial lo justifica.
- **Detección automática de clientes duplicados más allá de la coincidencia de documento** — no previsto.
- **Notas libres y archivos adjuntos por cliente** — spec posterior.
- **Asignación de un `staff` responsable por cliente** — no hay caso de uso conocido.
- **Alta de la cuenta con rol `cliente`** — se rige por la spec `001-acceso-roles-y-paneles`; acá solo se vincula una cuenta ya existente a un cliente.
- **Segmentación, etiquetas o categorización comercial de clientes** — spec posterior.
- **Reportes y métricas de cartera de clientes** — spec posterior.
- **Domicilios fuera de la Argentina** — no previsto.

## 9. Dependencias y supuestos

- Requiere la spec `001-acceso-roles-y-paneles`: el rol `cliente`, las cuentas y el portal de clientes.
- Habilita la spec `002-presupuestos`, que necesita un cliente existente con nombre para mostrar y teléfono de contacto.
- Se supone que las provincias y los tipos de documento son datos de referencia fijos, cargados al instalar el sistema y no administrados desde la interfaz.
- Se supone que todo cliente opera dentro de la Argentina.
- Se supone que la edición concurrente de un mismo cliente por dos usuarios de staff se resuelve con la estrategia estándar de la aplicación (el último cambio guardado prevalece); no se define un mecanismo de bloqueo.
- Si la exportación del listado exige una dependencia fuera de la lista del principio 1 de la constitución, se justifica por escrito en el PR que la agregue.

## 10. Preguntas abiertas

Ninguna: todas se resolvieron durante la entrevista y quedaron incorporadas en las secciones
anteriores.

## 11. Aprobación

- [x] Aprobada por Claudio el 2026-09-10
