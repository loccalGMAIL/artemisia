---
name: spec-generator
description: Usá esta skill cuando el usuario quiera crear o redactar una especificación (spec) de una funcionalidad o módulo nuevo — pedidos como "necesito una spec de X", "armemos la spec del módulo de presupuestos", o cuando describa una funcionalidad nueva antes de programarla en un proyecto con desarrollo guiado por specs (carpeta docs/specs/, constitución, AGENTS.md). Conduce una entrevista de requisitos de a una pregunta por vez y produce docs/specs/NNN-nombre/spec.md con criterios de aceptación en notación EARS. Para revisar o auditar una spec existente usá spec-reviewer. No planifica la implementación (esa es plan-generator) ni escribe código.
---

# Generador de specs

Convierte una idea vaga en una especificación acordada. La spec es el contrato: si algo no está
acá, no se implementa.

## Proceso

1. **Leé el contexto.** `docs/constitution.md` y `AGENTS.md` si existen, y las specs previas de
   `docs/specs/` para respetar convenciones y no contradecir lo ya acordado. Si el proyecto usa
   otra ruta, seguí la del proyecto: la constitución manda sobre esta skill.
2. **Entrevistá al usuario.** Preguntas de UNA en UNA, esperando respuesta antes de la siguiente.
   Diez es el techo, no el objetivo: cortá antes si las respuestas ya no cambian lo que hay que
   construir. Priorizá casos límite, comportamiento ante errores y qué queda fuera. Descartá las
   preguntas con respuesta obvia por defecto. No propongas soluciones técnicas: si el usuario
   pregunta "¿cómo lo harías?", redirigí al QUÉ.
3. **Elegí el número.** Mirá `docs/specs/` y usá el siguiente libre con tres dígitos:
   `docs/specs/NNN-<nombre-en-kebab-case>/spec.md`. Slug en español, sin acentos ni ñ.
4. **Redactá** usando `spec-template.md` de esta skill, sin saltarte secciones. Criterios de
   aceptación siempre en notación EARS, numerados `RF-1, RF-2, …` los funcionales y `RNF-1,
   RNF-2, …` los no funcionales. Cada requisito tiene que ser verificable: si no se te ocurre cómo
   comprobarlo, está mal escrito.
5. **Marcá lo que no sepas** como `[NECESITA ACLARACIÓN: pregunta concreta]`. Nunca rellenes un
   hueco inventando: un hueco visible es información, una suposición silenciosa es deuda.
6. **Pedí aprobación explícita** al terminar. No pases al plan ni escribas código hasta tenerla.
7. **Una vez aprobada**, proponé el nombre de rama según la convención del proyecto (en Artemisia,
   `vXX.XX.XX-[problema o solución]`) y recordá que el PR referencia la spec por nombre.

## Reglas

- La spec describe QUÉ y POR QUÉ. Prohibido incluir stack, arquitectura, nombres de archivos,
  algoritmos o firmas de funciones: eso va en el plan.
- **Entidades sí, esquema no.** La spec nombra conceptos del negocio como los nombra el usuario
  (presupuesto, ítem, contrato, resumen mensual) porque sin eso no se entiende nada. Lo prohibido
  es bajar a tabla, columna, clave foránea, índice o migración.
- Incluí siempre la sección "Fuera de alcance". Es la que evita que la funcionalidad crezca sola.
- Un requisito, una frase. Si necesitás un "y" para unir dos comportamientos, son dos requisitos.
- Sin adjetivos no medibles ("rápido", "intuitivo", "robusto") no son requisitos. Escribí el
  umbral o no lo escribas. Todo requisito con umbral, volumen o tiempo va como `RNF-n`.
- Idioma: el de la constitución del proyecto. Si no la hay, el del usuario.

## Notación EARS

Cinco patrones. Elegí el que corresponda, no los mezcles:

| Patrón | Forma | Cuándo |
|---|---|---|
| Ubicuo | EL SISTEMA \<hará\> | siempre cierto |
| Dirigido por evento | CUANDO \<disparador\>, EL SISTEMA \<hará\> | responde a algo |
| Estado | MIENTRAS \<estado\>, EL SISTEMA \<hará\> | durante una condición |
| Opcional | DONDE \<característica\>, EL SISTEMA \<hará\> | solo si está presente |
| No deseado | SI \<condición\>, ENTONCES EL SISTEMA \<hará\> | errores y casos límite |

**Bien escrito:**

> RF-4: SI ya existe un cliente con el mismo nombre (comparación ignorando mayúsculas y espacios
> exteriores), ENTONCES EL SISTEMA no creará el duplicado y mostrará el mensaje de conflicto en el
> formulario, indicando el cliente existente.

> RF-7: CUANDO se aprueba un presupuesto, EL SISTEMA registrará el cambio de estado con autor y
> fecha.

> RNF-2: EL SISTEMA generará el resumen mensual de un cliente con hasta 200 ítems en menos de 3
> segundos.

**Mal escrito, para contrastar:**

> RF-4: El sistema debe manejar bien los duplicados y ser rápido.

Sin patrón EARS, sin criterio verificable, dos ideas en una frase y un adjetivo no medible.
