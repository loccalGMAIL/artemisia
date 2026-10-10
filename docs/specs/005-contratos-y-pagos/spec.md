# 005 — Contratos y pagos

| | |
|---|---|
| **Estado** | Aprobada |
| **Fecha** | 2026-10-10 |
| **Autor** | Claudio |
| **Rama propuesta** | v00.06.00-contratos-y-pagos |

## 1. Problema

Cuando un cliente acepta un presupuesto de abono mensual, la agencia le cobra todos los meses, y
eso hoy se lleva por fuera del sistema (planillas, chats, anotaciones sueltas). No hay un lugar
donde se sepa desde cuándo y hasta cuándo corre el abono, cuánto se le debe a cada cliente este
mes, qué extras o descuentos se acordaron en el período, ni qué pagó y qué debe. Cuando un cliente
reclama por un importe, o cuando hay que saber quién está atrasado, hay que reconstruirlo a mano.

## 2. Objetivo

Que cada abono mensual aceptado tenga un contrato, que cada mes quede registrado lo que el cliente
debe, y que cada pago recibido se asiente contra esa deuda, con el cliente pudiendo consultar su
estado de cuenta.

Se sabrá que se logró cuando:

- Un presupuesto de abono mensual aceptado se pueda convertir en un contrato con su plazo y su día de vencimiento.
- Cada mes aparezca, sin intervención, el período a cobrar de cada contrato vigente.
- Se puedan sumar extras y descuentos con su justificación a un período y ver su total, lo pagado y el saldo.
- Se pueda ver qué períodos están pendientes, parcialmente pagados, pagados o vencidos, y de qué cliente.
- El cliente pueda consultar desde su portal sus contratos, sus períodos y sus pagos, sin poder modificarlos.

## 3. Actores

- **Admin** — crea contratos, carga extras, descuentos y pagos, y sigue la deuda de los clientes; trabaja en el portal de staff.
- **Staff** — crea contratos, carga extras, descuentos y pagos, y sigue la deuda de los clientes; trabaja en el portal de staff.
- **Cliente** — consulta sus contratos, el detalle de cada período y sus pagos desde el portal de clientes; no modifica nada.

## 4. Historias de usuario

- **HU-1**: Como staff, quiero crear un contrato a partir de un presupuesto de abono mensual aceptado, para que el cobro mensual quede registrado desde el primer mes.
- **HU-2**: Como staff, quiero que cada mes se genere el período a cobrar de cada contrato, para saber qué se le debe a cada cliente sin armarlo a mano.
- **HU-3**: Como staff, quiero sumar extras y descuentos con su justificación a un período, para cobrar lo que realmente corresponde ese mes y dejar constancia de por qué.
- **HU-4**: Como staff, quiero registrar pagos parciales contra un período, para que el saldo refleje lo que falta cobrar.
- **HU-5**: Como staff, quiero anular un pago cargado por error dejando la justificación, para corregir sin perder el rastro.
- **HU-6**: Como staff, quiero ver qué períodos están vencidos y de qué cliente, para reclamar el pago.
- **HU-7**: Como cliente, quiero ver mis contratos, lo que debo y lo que pagué, para controlar mi cuenta con la agencia.
- **HU-8**: Como staff, quiero reactivar un contrato que cancelé por error, para no tener que rehacer el presupuesto y el contrato.

## 5. Glosario

Los términos de cuenta, rol, portal de staff y portal de clientes están definidos en la spec
`001-acceso-roles-y-paneles`. Los términos de presupuesto, modalidad (pago único y abono mensual),
estado aceptado e importe mensual están definidos en la spec `002-presupuestos`. Los términos de
cliente, cliente inactivo o archivado y vínculo cuenta-cliente están definidos en la spec
`003-clientes`.

- **Contrato** — acuerdo de cobro mensual con un cliente, que nace de un presupuesto de abono mensual aceptado y tiene fecha de inicio, plazo, día de vencimiento e importe mensual.
- **Plazo** — duración del contrato: una cantidad de meses (plazo fijo) o sin fecha de fin (indefinido).
- **Último mes del plazo** — en un contrato de plazo fijo, el mes que resulta de sumar a su mes de inicio la cantidad de meses del plazo menos uno.
- **Importe mensual del contrato** — monto de la cuota que el contrato cobra cada mes; se toma del total del presupuesto.
- **Día de vencimiento** — día del mes, entre 1 y 28, en que vence el pago de cada período del contrato.
- **Estado del contrato** — situación del contrato: vigente, finalizado o cancelado.
- **Período** — el mes a cobrar de un contrato, con su cuota, sus conceptos, su total, sus pagos y su saldo.
- **Cuota** — importe mensual del contrato que el período lleva como base.
- **Concepto** — línea adicional de un período: un extra o un descuento.
- **Extra** — concepto que suma al período algo que el cliente debe pagar además de la cuota, con descripción e importe.
- **Descuento** — concepto que resta del período, con importe y justificación obligatoria.
- **Justificación** — texto escrito por el usuario que explica por qué se aplica un descuento o por qué se anula un pago; no es el mensaje con que el sistema rechaza una operación, que se llama motivo.
- **Total del período** — cuota más extras menos descuentos.
- **Pago** — importe recibido del cliente y asentado contra un período, con fecha y forma de pago.
- **Forma de pago** — modo en que se recibió un pago: efectivo, transferencia u otro.
- **Pago vigente** — pago que no fue anulado y que se computa en lo pagado.
- **Pagado** — suma de los pagos vigentes de un período.
- **Saldo** — total del período menos lo pagado.
- **Estado de pago** — situación de cobro de un período, que se calcula a partir de sus pagos vigentes y su saldo: pendiente, parcial o pagado.
- **Vencido** — señal de un período con saldo cuya fecha de vencimiento ya pasó; no es un estado de pago.
- **Referencia de factura** — número y fecha de la factura que la agencia emitió por fuera del sistema para un período.
- **El sistema (como autor)** — autor que se asienta en el historial cuando un cambio lo produce el propio sistema y no un usuario, como la generación de un período o la finalización de un contrato.
- **Historial** — asientos de cada cambio sobre un contrato o un período, con valor anterior, valor nuevo, autor y fecha.

## 6. Alcance

Alta de contratos a partir de presupuestos de abono mensual aceptados, con plazo fijo o indefinido,
día de vencimiento y precarga desde un contrato previo del mismo cliente; estados vigente,
finalizado y cancelado, con reactivación de los cancelados; modificación del importe mensual y del
día de vencimiento hacia adelante; generación mensual automática de períodos; extras y descuentos
con justificación; referencia de la factura emitida por fuera; pagos parciales contra un período
con anulación justificada; saldo, estado de pago y señal de vencido; listados y fichas para el
staff, incluidas las secciones reservadas en la ficha del cliente; y consulta de solo lectura para
el cliente desde su portal.

## 7. Requisitos funcionales

### Contratos

- **RF-1**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá crear un contrato a partir de un presupuesto aceptado de modalidad abono mensual.
- **RF-2**: SI se intenta crear un contrato a partir de un presupuesto que no está aceptado, ENTONCES EL SISTEMA lo impedirá y mostrará el motivo.
- **RF-3**: SI se intenta crear un contrato a partir de un presupuesto de modalidad pago único, ENTONCES EL SISTEMA lo impedirá y mostrará el motivo.
- **RF-4**: SI el importe mensual del presupuesto es cero, ENTONCES EL SISTEMA no creará el contrato y mostrará el motivo.
- **RF-5**: SI el presupuesto ya tiene un contrato, ENTONCES EL SISTEMA no creará otro y mostrará el contrato existente.
- **RF-6**: CUANDO se crea un contrato, EL SISTEMA copiará del presupuesto el cliente y el importe mensual.
- **RF-7**: EL SISTEMA exigirá la fecha de inicio, el día de vencimiento y el plazo al crear un contrato.
- **RF-8**: EL SISTEMA admitirá como plazo una cantidad entera de meses mayor que cero o la condición de indefinido.
- **RF-9**: SI el día de vencimiento cargado no es un número entero entre 1 y 28, ENTONCES EL SISTEMA rechazará el valor y mostrará el motivo.
- **RF-10**: SI el importe mensual que se carga al modificar un contrato no es mayor que cero o tiene más de 2 decimales, ENTONCES EL SISTEMA rechazará el valor y mostrará el motivo.
- **RF-11**: EL SISTEMA admitirá como fecha de inicio de un contrato cualquier fecha válida, pasada, actual o futura.
- **RF-12**: SI, a la fecha de alta, ya comenzó el mes siguiente al último mes del plazo de un contrato de plazo fijo, ENTONCES EL SISTEMA no creará el contrato y mostrará el motivo.
- **RF-13**: CUANDO se abre el alta de un contrato para un cliente que ya tuvo contratos, EL SISTEMA propondrá el día de vencimiento y el plazo del contrato de fecha de inicio más reciente, cualquiera sea su estado y, ante igual fecha de inicio, el dado de alta más recientemente.
- **RF-14**: CUANDO el contrato de fecha de inicio más reciente del cliente es de plazo fijo, EL SISTEMA propondrá como fecha de inicio el mes siguiente al último mes de su plazo.
- **RF-15**: CUANDO el contrato de fecha de inicio más reciente del cliente es de plazo indefinido, EL SISTEMA no propondrá fecha de inicio.
- **RF-16**: EL SISTEMA permitirá modificar los valores propuestos antes de confirmar el alta.
- **RF-17**: EL SISTEMA no copiará de un contrato anterior el importe mensual, que sale siempre del presupuesto de origen.
- **RF-18**: EL SISTEMA no alterará un contrato si su presupuesto vuelve al estado enviado o si se modifica después.
- **RF-19**: EL SISTEMA asignará a cada contrato exactamente un estado entre vigente, finalizado y cancelado.
- **RF-20**: CUANDO se crea un contrato, EL SISTEMA lo dejará en estado vigente.
- **RF-21**: MIENTRAS un contrato esté vigente, EL SISTEMA permitirá cancelarlo.
- **RF-22**: CUANDO se cancela un contrato, EL SISTEMA dejará de generarle períodos.
- **RF-23**: CUANDO se cancela un contrato, EL SISTEMA conservará los períodos ya generados con sus conceptos, su saldo y sus pagos.
- **RF-24**: MIENTRAS un contrato esté cancelado o finalizado, EL SISTEMA seguirá admitiendo pagos sobre sus períodos con saldo.
- **RF-25**: MIENTRAS un contrato esté cancelado, EL SISTEMA permitirá reactivarlo.
- **RF-26**: CUANDO se reactiva un contrato, EL SISTEMA retomará la generación de períodos y creará los que falten hasta el mes actual, sin superar la cantidad de meses de su plazo.
- **RF-27**: SI el plazo fijo de un contrato cancelado ya transcurrió por completo, ENTONCES EL SISTEMA no permitirá reactivarlo y mostrará el motivo.
- **RF-28**: EL SISTEMA no permitirá reactivar un contrato finalizado.
- **RF-29**: CUANDO comienza el mes siguiente al último mes del plazo de un contrato de plazo fijo vigente, EL SISTEMA lo marcará como finalizado.
- **RF-30**: MIENTRAS un contrato esté vigente, EL SISTEMA permitirá modificar su importe mensual.
- **RF-31**: MIENTRAS un contrato esté vigente, EL SISTEMA permitirá modificar su día de vencimiento.
- **RF-32**: CUANDO se modifica el importe mensual o el día de vencimiento de un contrato, EL SISTEMA aplicará el cambio a los períodos que se generen desde ese momento, sin alterar los ya generados, incluido el del mes en curso.
- **RF-33**: CUANDO se crea un contrato, se cambia su estado, o se modifica su importe mensual o su día de vencimiento, EL SISTEMA registrará el hecho con el valor anterior, el valor nuevo, el autor, que es el sistema cuando el cambio es automático, y la fecha.
- **RF-34**: EL SISTEMA no eliminará ningún contrato ni ningún asiento de su historial.
- **RF-35**: MIENTRAS el cliente de un contrato esté inactivo o archivado, EL SISTEMA seguirá generando los períodos de sus contratos vigentes y admitiendo pagos sobre ellos.

### Períodos

- **RF-36**: CUANDO comienza un mes o se crea un contrato, EL SISTEMA generará, para cada contrato vigente, los períodos que le falten desde su mes de inicio hasta el mes actual, sin superar la cantidad de meses de su plazo.
- **RF-37**: SI la fecha de inicio de un contrato es posterior al mes actual, ENTONCES EL SISTEMA no generará períodos hasta que llegue su mes de inicio.
- **RF-38**: EL SISTEMA generará a lo sumo un período por contrato y por mes.
- **RF-39**: DONDE el contrato sea de plazo fijo, EL SISTEMA no generará más períodos que meses tenga el plazo, contados desde el mes de inicio.
- **RF-40**: CUANDO se genera un período, EL SISTEMA le asignará como cuota el importe mensual del contrato vigente en ese momento y como vencimiento el día de vencimiento del contrato en ese mes, aunque esa fecha ya haya pasado, en cuyo caso el período figurará como vencido desde su generación.
- **RF-41**: CUANDO se genera un período, EL SISTEMA registrará el hecho en el historial del período con el sistema como autor y la fecha.

### Conceptos y factura

- **RF-42**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá agregar un extra a un período, con descripción e importe mayor que cero.
- **RF-43**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá agregar un descuento a un período, con justificación e importe mayor que cero.
- **RF-44**: SI se intenta agregar un descuento sin justificación, ENTONCES EL SISTEMA lo impedirá e indicará que falta la justificación.
- **RF-45**: EL SISTEMA admitirá más de un extra y más de un descuento en un mismo período.
- **RF-46**: SI un período ya tiene 100 conceptos cargados, sin contar los quitados, ENTONCES EL SISTEMA no admitirá otro y mostrará el motivo.
- **RF-47**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá quitar un extra o un descuento de un período.
- **RF-48**: EL SISTEMA conservará los conceptos quitados dentro del historial del período, sin eliminarlos.
- **RF-49**: EL SISTEMA calculará el total de un período como su cuota más sus extras menos sus descuentos.
- **RF-50**: SI un descuento dejara el total de un período por debajo de cero, ENTONCES EL SISTEMA lo rechazará y mostrará el motivo.
- **RF-51**: SI quitar un extra o agregar un descuento dejara el total de un período por debajo de lo ya pagado, ENTONCES EL SISTEMA lo rechazará y mostrará el motivo.
- **RF-52**: CUANDO se agrega o se quita un concepto, EL SISTEMA registrará el hecho con su tipo, su descripción o justificación, su importe, el autor y la fecha.
- **RF-53**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá cargar y modificar la referencia de factura de un período, con número y fecha.
- **RF-54**: SI se carga el número de la factura sin su fecha, o la fecha sin su número, ENTONCES EL SISTEMA lo rechazará y mostrará el motivo.
- **RF-55**: EL SISTEMA aceptará un período sin referencia de factura.
- **RF-56**: EL SISTEMA admitirá el mismo número de factura en más de un período.
- **RF-57**: CUANDO se carga o se modifica la referencia de factura de un período, EL SISTEMA registrará el valor anterior, el valor nuevo, el autor y la fecha.

### Pagos

- **RF-58**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le permitirá registrar un pago sobre un período, con fecha, importe y forma de pago.
- **RF-59**: EL SISTEMA admitirá como forma de pago efectivo, transferencia u otro.
- **RF-60**: EL SISTEMA permitirá cargar una referencia opcional en cada pago.
- **RF-61**: EL SISTEMA admitirá más de un pago sobre un mismo período.
- **RF-62**: SI un período ya tiene 100 pagos vigentes, ENTONCES EL SISTEMA no admitirá otro y mostrará el motivo.
- **RF-63**: SI el importe de un pago no es mayor que cero, ENTONCES EL SISTEMA lo rechazará y mostrará el motivo.
- **RF-64**: SI el importe de un pago supera el saldo del período, ENTONCES EL SISTEMA lo rechazará y mostrará el motivo, indicando el saldo.
- **RF-65**: SI el saldo de un período cambió entre que el usuario abrió el registro del pago y lo confirmó, ENTONCES EL SISTEMA validará el importe contra el saldo vigente al confirmar y rechazará el pago si lo supera, mostrando el motivo y el saldo actual.
- **RF-66**: SI la fecha de un pago es posterior a la fecha actual, ENTONCES EL SISTEMA lo rechazará y mostrará el motivo.
- **RF-67**: EL SISTEMA admitirá como fecha de un pago cualquier fecha pasada o actual, aunque sea anterior al mes del período o al inicio del contrato.
- **RF-68**: EL SISTEMA calculará lo pagado de un período como la suma de sus pagos vigentes.
- **RF-69**: EL SISTEMA calculará el saldo de un período como su total menos lo pagado.
- **RF-70**: EL SISTEMA calculará para cada período exactamente un estado de pago entre pendiente, parcial y pagado.
- **RF-71**: MIENTRAS un período tenga saldo y ningún pago vigente, EL SISTEMA lo mostrará como pendiente.
- **RF-72**: MIENTRAS un período tenga saldo y al menos un pago vigente, EL SISTEMA lo mostrará como parcial.
- **RF-73**: MIENTRAS el saldo de un período sea cero, aunque su total sea cero y no tenga pagos, EL SISTEMA lo mostrará como pagado.
- **RF-74**: MIENTRAS la fecha de vencimiento de un período sea anterior a la fecha actual y su saldo sea mayor que cero, EL SISTEMA lo señalará como vencido, sin cambiar su estado de pago.
- **RF-75**: MIENTRAS un pago esté vigente, EL SISTEMA permitirá a un usuario con rol `admin` o `staff` anularlo, indicando una justificación.
- **RF-76**: SI se intenta anular un pago sin justificación, ENTONCES EL SISTEMA lo impedirá e indicará que falta la justificación.
- **RF-77**: CUANDO se anula un pago, EL SISTEMA dejará de computarlo en lo pagado y lo conservará con su justificación, el autor y la fecha.
- **RF-78**: EL SISTEMA no permitirá modificar ningún dato de un pago ya registrado, y un error se corrige anulándolo y registrando uno nuevo.
- **RF-79**: CUANDO se registra o se anula un pago, EL SISTEMA registrará el hecho en el historial del período con el autor y la fecha.
- **RF-80**: EL SISTEMA no eliminará ningún pago ni ningún asiento de historial.

### Consulta del staff

- **RF-81**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le mostrará el listado de contratos con cliente, importe mensual, fecha de inicio, plazo y estado.
- **RF-82**: EL SISTEMA permitirá filtrar el listado de contratos por cliente.
- **RF-83**: EL SISTEMA permitirá filtrar el listado de contratos por estado.
- **RF-84**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le mostrará la ficha de un contrato con sus períodos, y de cada uno su mes, su vencimiento, su total, lo pagado, su saldo y su estado de pago.
- **RF-85**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le mostrará el detalle de un período con su cuota, sus extras, sus descuentos con su justificación, su referencia de factura y sus pagos vigentes y anulados.
- **RF-86**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le mostrará el historial de un contrato y el de un período en orden cronológico.
- **RF-87**: MIENTRAS un usuario con rol `admin` o `staff` tenga sesión iniciada en el portal de staff, EL SISTEMA le mostrará el listado de períodos con cliente, contrato, mes, vencimiento, total, lo pagado, saldo y estado de pago.
- **RF-88**: EL SISTEMA permitirá filtrar el listado de períodos por cliente.
- **RF-89**: EL SISTEMA permitirá filtrar el listado de períodos por estado de pago.
- **RF-90**: EL SISTEMA permitirá filtrar el listado de períodos por vencidos.
- **RF-91**: EL SISTEMA mostrará en la ficha del cliente sus contratos, con lo que cumple el RF-47 de la spec `003-clientes`.
- **RF-92**: EL SISTEMA mostrará en la ficha del cliente su historial de pagos, con lo que cumple el RF-48 de la spec `003-clientes`.

### Portal de clientes

- **RF-93**: MIENTRAS una cuenta con rol `client` vinculada a un cliente tenga sesión iniciada en el portal de clientes, EL SISTEMA le mostrará únicamente los contratos de ese cliente.
- **RF-94**: EL SISTEMA mostrará, de cada contrato del cliente de la cuenta `client`, sus períodos con la cuota, los extras, los descuentos con su justificación, el total, lo pagado, el saldo, el estado de pago y el vencimiento.
- **RF-95**: EL SISTEMA mostrará a la cuenta `client` el historial de pagos vigentes de su cliente.
- **RF-96**: EL SISTEMA no mostrará a la cuenta `client` los pagos anulados.
- **RF-97**: SI una cuenta con rol `client` intenta ver un contrato, un período o un pago de un cliente distinto de aquel al que está vinculada, ENTONCES EL SISTEMA la rechazará y mostrará un mensaje de permiso insuficiente.
- **RF-98**: SI una cuenta con rol `client` intenta cualquier acción que cree, modifique, cancele, reactive, anule, quite o cargue datos en contratos, períodos o pagos, ENTONCES EL SISTEMA la rechazará y mostrará un mensaje de permiso insuficiente.

## 8. Requisitos no funcionales

- **RNF-1**: EL SISTEMA expresará todo importe con exactamente 2 decimales.
- **RNF-2**: EL SISTEMA redondeará cualquier importe calculado al múltiplo de 0,01 más cercano.
- **RNF-3**: EL SISTEMA mostrará el listado de períodos, con 5.000 períodos cargados, en menos de 2 segundos.
- **RNF-4**: EL SISTEMA generará los períodos de un mes para 500 contratos vigentes, con 12 períodos ya generados cada uno, en menos de 10 segundos, midiendo la generación completa del mes.
- **RNF-5**: EL SISTEMA mostrará el portal de contratos de un cliente con 60 períodos y 200 pagos en menos de 2 segundos.
- **RNF-6**: EL SISTEMA conservará el historial de contratos y períodos, los pagos y sus anulaciones sin purgarlos en ningún plazo.

## 9. Fuera de alcance

- **Emisión de factura electrónica, IVA y datos impositivos** — la factura se emite por fuera; el sistema solo guarda su referencia.
- **Cobro online e integración con plataformas de pago** — los pagos se cargan a mano; ninguna forma de pago se conecta con una plataforma.
- **Avisos o recordatorios de vencimiento** (email, WhatsApp) — el sistema muestra lo vencido, pero no avisa.
- **Intereses por mora y recargos automáticos** — un recargo se carga a mano como extra.
- **Saldo a favor y pagos repartidos entre períodos** — cada pago va contra un solo período y no puede superar su saldo; un cobro que cubre dos períodos se carga como dos pagos.
- **Prorrateo del primer mes** — el período del mes de inicio cobra la cuota completa.
- **Aumentos automáticos del abono** (por inflación o por índice) — el importe mensual se modifica a mano, hacia adelante.
- **Contratos para presupuestos de pago único** — no generan contrato ni deuda mensual en esta spec.
- **Renovación directa de un contrato** — cada etapa nueva exige un presupuesto nuevo y un contrato nuevo; solo se precargan las bases del anterior.
- **Reactivación de un contrato finalizado** — un contrato que terminó su plazo no vuelve a vigente; se arma uno nuevo.
- **Duplicar un presupuesto anterior** — sigue fuera de alcance de los presupuestos.
- **Recibos y comprobantes de pago en PDF** — spec posterior si se necesitan.
- **Reportes de cobranza, morosidad y conciliación bancaria** — spec posterior.
- **Firma o aceptación formal del contrato por el cliente** — no previsto.
- **Vínculo obligatorio de un extra con una pieza** — un extra es un concepto libre del staff.

## 10. Criterios de finalización

- Todos los RF y RNF tienen tests que pasan.
- Un contrato vigente tiene su período del mes sin que nadie intervenga, y si la generación no corrió algún mes, los períodos faltantes se reponen solos.
- Un pago parcial deja el período en parcial, con el saldo correcto, y el pago que lo completa lo deja en pagado.
- Un pago anulado deja de contar en lo pagado y sigue consultable para el staff con su justificación.
- Un contrato cancelado por error se reactiva y repone los períodos que quedaron sin generar.
- Un cliente con sesión en su portal ve sus contratos, períodos y pagos, y no puede modificar nada ni ver lo de otro cliente.
- La ficha del cliente muestra sus contratos y su historial de pagos.

## 11. Dependencias y supuestos

- Requiere la spec `001-acceso-roles-y-paneles`: los roles `admin`, `staff` y `client`, y los portales de staff y de clientes.
- Requiere la spec `002-presupuestos`: un presupuesto aceptado de modalidad abono mensual, cuyo total es el importe mensual del contrato.
- Requiere la spec `003-clientes`: el cliente, el vínculo entre su cuenta `client` y el cliente, y los lugares de la ficha reservados para los contratos y los pagos. El acceso al portal de un cliente archivado sigue lo definido en esa spec.
- Se supone que todas las fechas, meses y vencimientos se calculan en la hora de la Argentina (`America/Argentina/Buenos_Aires`). La aplicación hoy trabaja en UTC, de modo que el plan técnico tiene que resolver la diferencia sin alterar lo ya acordado en las specs `002` y `004`.
- Se supone una sola moneda para todos los importes, como en la spec `002`.
- Se supone que existe una ejecución programada diaria en el entorno, que dispara la generación mensual de períodos.
- Se supone que el primer período de un contrato es el de su mes de inicio.
- Se supone que se pueden agregar o quitar conceptos y registrar pagos en cualquier período, sin importar su estado de pago ni el del contrato, mientras se respeten los límites de los requisitos.
- Se supone que el cliente no necesita ver los pagos anulados.
- Se supone que la edición concurrente del mismo período por dos usuarios de staff se resuelve con la estrategia estándar de la aplicación, el último cambio guardado prevalece, salvo al registrar pagos, que se rige por el RF-65.

## 12. Preguntas abiertas

Ninguna: todas se resolvieron durante la entrevista y la revisión, y quedaron incorporadas en las
secciones anteriores.

## 13. Aprobación

- [x] Aprobada por Claudio el 2026-10-10
