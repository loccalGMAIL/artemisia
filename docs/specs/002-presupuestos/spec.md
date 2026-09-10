# 002 — Presupuestos

| | |
|---|---|
| **Estado** | Aprobada |
| **Fecha** | 2026-09-10 |
| **Autor** | Claudio |
| **Rama propuesta** | v00.02.00-presupuestos |

## 1. Problema

La agencia trabaja por presupuestos: para cada cliente se arma una propuesta con las piezas o
servicios que se van a producir y su precio. Hoy esas propuestas se escriben a mano en documentos
sueltos y se mandan por fuera de cualquier sistema, así que no queda registro de qué se cotizó, a
qué precio, cuándo se envió ni si el cliente lo aceptó. Cuando un precio de lista cambia no se
sabe qué presupuestos vigentes quedaron desactualizados, y cuando un cliente reclama por un
importe no hay forma de reconstruir qué se le ofreció ni quién lo modificó.

## 2. Objetivo

Que toda propuesta económica de la agencia nazca, se modifique y se cierre dentro del sistema,
con su precio y su historial preservados.

Se sabrá que se logró cuando:

- Se pueda armar un presupuesto tomando servicios del catálogo y descargarlo en PDF para mandarlo.
- Un cambio de precio de lista no altere ningún presupuesto ya emitido.
- Ante cualquier ítem modificado se pueda ver qué valor tenía antes, quién lo cambió y cuándo.
- El estado de cada propuesta (borrador, enviado, aceptado, rechazado) sea consultable en un listado.

## 3. Actores

- **Admin** — administra el catálogo de servicios, arma presupuestos y registra su resultado; trabaja en el portal de staff.
- **Staff** — administra el catálogo de servicios, arma presupuestos y registra su resultado; trabaja en el portal de staff.
- **Cliente** — destinatario del presupuesto. No interviene en el sistema en esta spec: recibe el PDF por fuera y su respuesta la registra el staff.

## 4. Glosario

Los términos de cuenta, rol, portal de staff y portal de clientes están definidos en la spec
`001-acceso-roles-y-paneles`. Los términos de cliente y teléfono de contacto están definidos en la
spec `003-clientes`.

- **Servicio** — trabajo que la agencia ofrece, con nombre, descripción, categoría y precio de lista; vive en el catálogo y se reutiliza entre presupuestos.
- **Catálogo de servicios** — conjunto de servicios disponibles para armar presupuestos.
- **Categoría de trabajo** — agrupación de servicios por tipo de encargo (branding, redes, papelería).
- **Precio de lista** — precio vigente de un servicio en el catálogo.
- **Presupuesto** — propuesta económica dirigida a un cliente, compuesta por ítems, con un estado y un total.
- **Ítem** — línea de un presupuesto: la copia de un servicio con su cantidad y su precio congelado.
- **Precio copiado** — precio que el ítem guarda desde que se agregó al presupuesto; no cambia aunque cambie el precio de lista.
- **Modalidad** — forma de cobro del presupuesto: pago único o abono mensual.
- **Subtotal** — suma de los importes de los ítems de un presupuesto.
- **Descuento** — rebaja aplicada al subtotal, expresada como porcentaje o como monto fijo.
- **Total** — subtotal menos descuento.
- **Fecha de validez** — fecha hasta la cual la agencia sostiene el precio ofrecido; es informativa.
- **Datos de cabecera** — título, cliente destinatario, modalidad, fecha de emisión y fecha de validez de un presupuesto.
- **Historial del presupuesto** — asientos de cada cambio sobre un presupuesto, con valor anterior, valor nuevo, autor y fecha.

## 5. Alcance

Administración del catálogo de servicios con sus precios de lista y su historial de precios; alta
de presupuestos para un cliente existente tomando ítems del catálogo, con copia congelada de
nombre, descripción y precio; edición de cantidad, descripción y actualización de precio de los
ítems mientras el presupuesto esté en borrador o enviado, con cada cambio asentado; modalidad de
pago único o abono mensual; cálculo de subtotal, descuento y total; ciclo de estados borrador →
enviado → aceptado / rechazado registrado por el staff, con posibilidad de revertir; listado y
consulta de presupuestos con su historial; descarga del presupuesto en PDF y acción que abre
WhatsApp con un mensaje de texto precargado para el contacto del cliente.

## 6. Requisitos funcionales

### Catálogo de servicios

- **RF-1**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá dar de alta servicios indicando nombre, descripción, categoría de trabajo y precio de lista.
- **RF-2**: EL SISTEMA asignará a cada servicio exactamente una categoría de trabajo.
- **RF-3**: SI el nombre de un servicio nuevo coincide con el de un servicio existente, incluidos los desactivados (comparación ignorando mayúsculas y espacios exteriores), ENTONCES EL SISTEMA no lo creará y mostrará el mensaje de conflicto en el formulario, indicando el servicio existente.
- **RF-4**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá modificar el nombre, la descripción, la categoría y el precio de lista de un servicio.
- **RF-5**: CUANDO se modifica el precio de lista de un servicio, EL SISTEMA registrará el precio anterior, el precio nuevo, el autor y la fecha.
- **RF-6**: CUANDO se modifica el precio de lista de un servicio, EL SISTEMA no alterará el precio copiado de ningún ítem existente.
- **RF-7**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá desactivar y reactivar un servicio.
- **RF-8**: MIENTRAS un servicio esté desactivado, EL SISTEMA no lo ofrecerá para agregar a un presupuesto.
- **RF-9**: EL SISTEMA conservará los servicios desactivados junto con su historial de precios, sin eliminarlos.
- **RF-10**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá consultar el historial de precios de un servicio.

### Alta del presupuesto

- **RF-11**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá crear un presupuesto dirigido a un cliente existente.
- **RF-12**: CUANDO se crea un presupuesto, EL SISTEMA lo dejará en estado borrador.
- **RF-13**: CUANDO se crea un presupuesto, EL SISTEMA le asignará un identificador correlativo único que no se reutiliza.
- **RF-14**: EL SISTEMA registrará en cada presupuesto un título, el cliente destinatario, la modalidad, la fecha de emisión, la fecha de validez y el autor.
- **RF-15**: EL SISTEMA asignará a cada presupuesto exactamente una modalidad entre pago único y abono mensual.
- **RF-16**: MIENTRAS un presupuesto esté en borrador o enviado, EL SISTEMA permitirá modificar sus datos de cabecera.
- **RF-17**: SI se intenta crear un presupuesto sin cliente destinatario, ENTONCES EL SISTEMA lo impedirá y mostrará el motivo en el formulario.
- **RF-18**: EL SISTEMA exigirá la fecha de validez al crear un presupuesto.
- **RF-19**: SI la fecha de validez cargada no es posterior a la fecha de emisión, ENTONCES EL SISTEMA rechazará el valor y mostrará el motivo.
- **RF-20**: EL SISTEMA permitirá seguir editando y enviando un presupuesto cuyo cliente destinatario haya pasado a inactivo o archivado.

### Ítems

- **RF-21**: MIENTRAS un presupuesto esté en borrador o enviado, EL SISTEMA permitirá agregarle ítems tomados de los servicios activos del catálogo.
- **RF-22**: CUANDO se agrega un ítem, EL SISTEMA copiará al presupuesto el nombre, la descripción y el precio de lista vigentes del servicio.
- **RF-23**: EL SISTEMA permitirá agregar el mismo servicio más de una vez como ítems separados de un mismo presupuesto.
- **RF-24**: EL SISTEMA exigirá en cada ítem una cantidad entera mayor que cero.
- **RF-25**: EL SISTEMA calculará el importe de cada ítem como su precio copiado multiplicado por su cantidad.
- **RF-26**: MIENTRAS un presupuesto esté en borrador o enviado, EL SISTEMA permitirá modificar la cantidad de cada uno de sus ítems.
- **RF-27**: MIENTRAS un presupuesto esté en borrador o enviado, EL SISTEMA permitirá modificar la descripción de cada uno de sus ítems.
- **RF-28**: MIENTRAS un presupuesto esté en borrador o enviado, EL SISTEMA permitirá actualizar el precio copiado de un ítem al precio de lista vigente de su servicio.
- **RF-29**: EL SISTEMA no admitirá en un ítem ningún precio distinto del precio de lista de su servicio al momento de agregarlo o de actualizarlo.
- **RF-30**: MIENTRAS un presupuesto esté en borrador o enviado, EL SISTEMA permitirá quitarle ítems.
- **RF-31**: SI se intenta agregar un ítem a un presupuesto que ya tiene 100 ítems, ENTONCES EL SISTEMA lo impedirá y mostrará el motivo.
- **RF-32**: EL SISTEMA conservará los ítems quitados dentro del historial del presupuesto, sin eliminarlos.
- **RF-33**: CUANDO se agrega, modifica o quita un ítem, EL SISTEMA registrará el hecho con el valor anterior, el valor nuevo, el autor y la fecha.
- **RF-34**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le mostrará el historial de un presupuesto en orden cronológico.

### Totales

- **RF-35**: EL SISTEMA calculará el subtotal de un presupuesto como la suma de los importes de sus ítems.
- **RF-36**: MIENTRAS un presupuesto esté en borrador o enviado, EL SISTEMA permitirá cargarle un descuento expresado como porcentaje del subtotal o como monto fijo.
- **RF-37**: EL SISTEMA admitirá a lo sumo un descuento por presupuesto.
- **RF-38**: EL SISTEMA calculará el total de un presupuesto como su subtotal menos su descuento.
- **RF-39**: SI el descuento cargado dejara el total en un valor menor que cero, ENTONCES EL SISTEMA rechazará el descuento y mostrará el motivo en el formulario.
- **RF-40**: CUANDO se carga, modifica o quita el descuento, EL SISTEMA registrará el valor anterior, el valor nuevo, el autor y la fecha.
- **RF-41**: MIENTRAS un presupuesto tenga modalidad de abono mensual, EL SISTEMA presentará su total identificado como importe mensual.

### Estados

- **RF-42**: EL SISTEMA asignará a cada presupuesto exactamente un estado entre borrador, enviado, aceptado y rechazado.
- **RF-43**: MIENTRAS un presupuesto esté en borrador, EL SISTEMA permitirá marcarlo como enviado.
- **RF-44**: SI se intenta marcar como enviado un presupuesto sin ítems, ENTONCES EL SISTEMA lo impedirá y mostrará el motivo.
- **RF-45**: MIENTRAS un presupuesto esté enviado, EL SISTEMA permitirá marcarlo como aceptado o como rechazado.
- **RF-46**: CUANDO se marca un presupuesto como aceptado o rechazado, EL SISTEMA registrará la fecha de la respuesta del cliente.
- **RF-47**: CUANDO se marca un presupuesto como rechazado, EL SISTEMA permitirá registrar un motivo de rechazo, y lo aceptará vacío.
- **RF-48**: MIENTRAS un presupuesto esté aceptado o rechazado, EL SISTEMA no permitirá modificar sus ítems, su descuento ni sus datos de cabecera.
- **RF-49**: MIENTRAS un presupuesto esté aceptado o rechazado, EL SISTEMA permitirá a un usuario con rol `admin` o `staff` devolverlo al estado enviado.
- **RF-50**: CUANDO cambia el estado de un presupuesto, EL SISTEMA registrará el estado anterior, el estado nuevo, el autor y la fecha.
- **RF-51**: MIENTRAS un presupuesto esté en borrador, EL SISTEMA permitirá descartarlo.
- **RF-52**: CUANDO se descarta un presupuesto, EL SISTEMA lo conservará junto con su historial y dejará de mostrarlo en el listado de presupuestos.
- **RF-53**: EL SISTEMA no eliminará ningún presupuesto ni ningún asiento de su historial.

### Vigencia

- **RF-54**: EL SISTEMA mostrará la fecha de validez de un presupuesto en su ficha y en su PDF.
- **RF-55**: MIENTRAS la fecha de validez de un presupuesto enviado sea anterior a la fecha actual, EL SISTEMA lo señalará como vencido en el listado, sin cambiar su estado.
- **RF-56**: EL SISTEMA permitirá marcar como aceptado o rechazado un presupuesto cuya fecha de validez esté vencida.

### Listado y consulta

- **RF-57**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le mostrará el listado de presupuestos con identificador, cliente, título, modalidad, total, estado y fecha de emisión.
- **RF-58**: EL SISTEMA permitirá filtrar el listado de presupuestos por cliente.
- **RF-59**: EL SISTEMA permitirá filtrar el listado de presupuestos por estado.
- **RF-60**: SI un usuario con rol `cliente` solicita cualquier acción sobre presupuestos o sobre el catálogo, ENTONCES EL SISTEMA la rechazará y mostrará un mensaje de permiso insuficiente.

### PDF y envío

- **RF-61**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá descargar en PDF cualquier presupuesto, en cualquiera de sus estados.
- **RF-62**: EL SISTEMA incluirá en el PDF el identificador del presupuesto, los datos del cliente, el título, la fecha de emisión, la fecha de validez y la modalidad.
- **RF-63**: EL SISTEMA incluirá en el PDF, por cada ítem, su nombre, su descripción, su cantidad, su precio copiado y su importe.
- **RF-64**: EL SISTEMA incluirá en el PDF el subtotal, el descuento y el total del presupuesto.
- **RF-65**: DONDE el presupuesto tenga modalidad de abono mensual, EL SISTEMA indicará en el PDF que el total es un importe mensual.
- **RF-66**: EL SISTEMA no incluirá en el PDF los ítems quitados ni los asientos del historial.
- **RF-67**: SI la generación del PDF falla, ENTONCES EL SISTEMA mostrará un mensaje de error y no descargará ningún archivo.
- **RF-68**: MIENTRAS un usuario con rol `admin` o `staff` consulte un presupuesto, EL SISTEMA le ofrecerá una acción que abra WhatsApp dirigido al teléfono de contacto del cliente, con un mensaje de texto precargado que referencie el identificador y el título del presupuesto.
- **RF-69**: SI el cliente destinatario no tiene teléfono de contacto cargado, ENTONCES EL SISTEMA no ofrecerá la acción de WhatsApp e indicará que falta el teléfono del cliente.
- **RF-70**: EL SISTEMA no adjuntará el PDF al mensaje de WhatsApp.
- **RF-71**: EL SISTEMA no expondrá ningún presupuesto ni su PDF en una dirección accesible sin sesión iniciada.

## 7. Requisitos no funcionales

- **RNF-1**: EL SISTEMA expresará todo importe con exactamente 2 decimales.
- **RNF-2**: EL SISTEMA redondeará cualquier importe calculado al múltiplo de 0,01 más cercano.
- **RNF-3**: EL SISTEMA admitirá descuentos porcentuales entre 0 y 100 inclusive.
- **RNF-4**: EL SISTEMA admitirá hasta 100 ítems por presupuesto.
- **RNF-5**: EL SISTEMA recalculará y mostrará subtotal, descuento y total de un presupuesto de 100 ítems en menos de 1 segundo.
- **RNF-6**: EL SISTEMA generará el PDF de un presupuesto de 100 ítems en menos de 5 segundos.
- **RNF-7**: EL SISTEMA mostrará el listado de presupuestos, con 2.000 presupuestos cargados, en menos de 2 segundos.
- **RNF-8**: EL SISTEMA conservará el historial de un presupuesto y el historial de precios de un servicio sin purgarlos en ningún plazo.

## 8. Fuera de alcance

- **Alta y gestión de clientes** — es la spec `003-clientes` y esta depende de ella; acá solo se elige un cliente existente.
- **Aceptación del presupuesto por el cliente desde el portal de clientes** — spec posterior; hoy la respuesta la registra el staff.
- **Envío automático por email o por la API de WhatsApp Business** — se descartó: exige cuenta de WhatsApp Business y una dependencia fuera del stack. El staff aprieta enviar.
- **Enlace público al presupuesto** — se descartó junto con el envío automático: el staff adjunta el PDF a mano.
- **Revisiones numeradas del presupuesto (v1, v2…)** — se descartó: el presupuesto se edita en borrador o enviado y el historial alcanza para reconstruir qué cambió.
- **Plantillas por tipo de servicio y duplicación de un presupuesto anterior** — comodidad, no necesidad; spec posterior si el armado resulta lento.
- **IVA y discriminación de impuestos** — los precios de lista son finales.
- **Más de una moneda** — todos los importes van en una única moneda.
- **Más de un descuento por presupuesto y descuentos por ítem** — no hay caso de uso conocido.
- **Duración del abono mensual, contrato, facturación y pagos** — van en las specs de Contratos y Pagos; acá el presupuesto solo declara el importe mensual.
- **Generación de piezas de trabajo a partir de un presupuesto aceptado** — va en la spec de Piezas.
- **Costos de proveedores, márgenes y rentabilidad del presupuesto** — spec propia si se necesita.
- **Reportes y métricas de conversión de presupuestos** — spec posterior.
- **Firma o aceptación formal con validez legal** — no previsto.

## 9. Dependencias y supuestos

- Requiere la spec `001-acceso-roles-y-paneles`: los roles `admin`, `staff` y `cliente` y el portal de staff.
- **Requiere la spec `003-clientes` aprobada e implementada antes que esta.** El presupuesto se dirige a un cliente existente y necesita de él, como mínimo, nombre para mostrar y teléfono de contacto para la acción de WhatsApp. Sin ese módulo, esta spec no se puede implementar.
- Se supone que las categorías de trabajo (branding, redes, papelería) son datos de referencia cargados al instalar el sistema y no se administran desde la interfaz.
- Se supone una única moneda para todo el sistema, con precios de lista finales.
- Se supone que el staff tiene WhatsApp disponible en el equipo desde el que trabaja; el sistema solo abre la conversación con el mensaje precargado.
- Se supone que la edición concurrente de un mismo presupuesto por dos usuarios de staff se resuelve con la estrategia estándar de la aplicación (el último cambio guardado prevalece); no se define un mecanismo de bloqueo.
- La generación del PDF exige una dependencia fuera de la lista del principio 1 de la constitución: se justifica por escrito en el PR que la agregue.

## 10. Preguntas abiertas

Ninguna: todas se resolvieron durante la entrevista y quedaron incorporadas en las secciones
anteriores.

## 11. Aprobación

- [x] Aprobada por Claudio el 2026-09-10
