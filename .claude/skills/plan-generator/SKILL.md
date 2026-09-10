---
name: plan-generator
description: Usá esta skill cuando exista una spec aprobada y haya que redactar el plan técnico — pedidos como "armemos el plan de la 001", "cómo lo implementamos", "necesito el plan.md", o cuando el usuario mencione pasar de la spec a la implementación. Produce docs/specs/NNN-nombre/plan.md a partir de la spec, la constitución y el AGENTS.md. Chequea el plan contra la constitución antes de proponerlo y marca qué RF cubre cada parte. No redacta specs (esa es spec-generator), no descompone en tareas (esa es tasks-generator) y no escribe código.
---

# Generador de planes técnicos

Traduce el QUÉ de una spec aprobada al CÓMO. El plan es el contrato entre la spec y el código: si
algo no está acá, el código no lo puede inventar.

## Precondiciones

Antes de redactar, verificá que se cumplan las tres:

1. Existe `docs/specs/NNN-nombre/spec.md` y su estado es "Aprobada".
2. La spec no tiene `[NECESITA ACLARACIÓN]` pendientes.
3. Existe `docs/constitution.md`. Si no, avisá y pedí que se cree antes.

Si alguna falla, no arranques: decí qué falta.

## Proceso

1. **Leé el contexto.** En este orden: `docs/specs/NNN-nombre/spec.md`, `docs/constitution.md`,
   `AGENTS.md`, ADR relevantes de `docs/adr/`, y los `plan.md` de specs anteriores para respetar
   convenciones y no reinventar lo ya resuelto.
2. **Chequeo previo contra la constitución.** Antes de escribir nada, listá qué principios de la
   constitución tocan esta spec y cómo el plan va a cumplirlos. Si algo del enfoque los violaría
   (dependencia nueva sin justificar, cálculo dentro de una clase de UI, migración destructiva,
   borrado físico), avisá y ofrecé la alternativa que sí cumple. Sin este chequeo, no seguís.
3. **Preguntas técnicas mínimas.** Solo si la spec deja libre una decisión técnica que cambia el
   plan (elegir entre dos estructuras válidas, umbral no especificado, integración con algo
   externo). De UNA en UNA, esperando respuesta. Cuatro es el techo, no el objetivo. No propongas
   soluciones a lo que ya está claro en la spec.
4. **Redactá** usando `plan-template.md` de esta skill, sin saltarte secciones.
5. **Mapeo RF → componente.** Cada RF y RNF de la spec tiene que aparecer en la tabla de mapeo,
   ligado a la parte del plan que lo cubre. Si un RF no encaja en ningún componente, o el plan es
   incompleto o el RF sobra: pará y pediné aclaración.
6. **Marcá lo que no sepas** como `[NECESITA ACLARACIÓN: pregunta concreta]`. No inventes decisiones
   técnicas: un hueco visible es información.
7. **Pedí aprobación explícita** al terminar. No pases a tareas ni escribas código hasta tenerla.

## Reglas

- El plan describe CÓMO se implementa el QUÉ de la spec. No agrega funcionalidad: si algo no está
  en la spec, no está en el plan. Si aparece una necesidad nueva, se vuelve a la spec y se enmienda.
- El plan sí baja a esquema: tablas, columnas, tipos, claves foráneas, índices, enums, migraciones.
  Es la diferencia principal con la spec.
- Toda decisión técnica lleva su alternativa descartada y una línea de por qué. Sin alternativa,
  no es una decisión, es una preferencia.
- Sin dependencias nuevas fuera del stack del AGENTS.md salvo que la spec lo exija y el plan lo
  justifique por escrito.
- Idioma del código en el que esté escrito el AGENTS.md; idioma de la prosa del plan, el de la spec.
- Sin código de ejemplo. Pseudocódigo solo si un algoritmo es lo suficientemente complejo como
  para que la descripción en prosa dé margen a interpretación.

## Chequeo de constitución

Esta sección va siempre en el plan, aunque parezca redundante. Es lo que evita que el plan se
apruebe contra la constitución sin darse cuenta.

Formato mínimo por principio:

- **Principio N (\<título\>)**: cómo lo cumple el plan, o "no aplica" con motivo.

Si algún principio no se cumple, el plan no está listo: pará y avisá.
