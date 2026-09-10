---
name: adr-generator
description: Usá esta skill cuando el usuario tenga que registrar una decisión técnica transversal que afecte a varias specs o al proyecto entero — pedidos como "escribamos un ADR sobre X", "documentemos esta decisión", "esto merece un ADR", o cuando aparezca una decisión (elegir entre dos librerías, un patrón que se va a repetir, una convención de esquema o naming, una decisión de infraestructura) que no encaja en una spec porque no es una funcionalidad. Produce docs/adr/NNNN-slug.md con numeración de cuatro dígitos. Entrevista corta y formato Nygard. No redacta specs, planes ni tareas, y no escribe código.
---

# Generador de ADR

Los ADR (Architecture Decision Records) registran decisiones técnicas transversales: las que
afectan a varias specs o al proyecto entero. Sin ADR, en seis meses no vas a recordar por qué
elegiste X y vas a discutirlo de nuevo.

## Cuándo corresponde un ADR y cuándo no

**Sí ADR**: elegir entre dos librerías, un patrón que se va a repetir (cómo se hacen los enums, cómo
se estructura la autorización), una convención de esquema o naming, un idioma del código, una
decisión de infraestructura, deprecar algo.

**No ADR**: decisiones que viven dentro de una sola spec (van en `plan.md §3 Decisiones técnicas`),
principios del proyecto (van en `docs/constitution.md`), workflow del equipo (va en `AGENTS.md`).

Si la decisión aplica a una única funcionalidad, no es ADR. Si aparece "esta decisión también va a
regir para X, Y y Z", sí es ADR.

## Proceso

1. **Leé** los ADR previos en `docs/adr/` para no contradecir uno vigente sin marcarlo como
   superado, y `docs/constitution.md` para no proponer algo que la contradiga (los ADR no pueden
   sobreescribir la constitución).
2. **Entrevista corta.** De UNA en UNA. Cuatro es el techo, no el objetivo. Preguntá solo si la
   respuesta cambia lo que se escribe:
   - Qué problema o disyuntiva la motivó.
   - Qué alternativas se consideraron.
   - Qué se decide.
   - Qué consecuencias tiene (positivas y negativas).
3. **Numeración**: mirá `docs/adr/` y usá el siguiente libre con cuatro dígitos:
   `docs/adr/NNNN-<slug-en-kebab-case>.md`. Slug en español, sin acentos ni ñ.
4. **Redactá** usando `adr-template.md` de esta skill, sin saltarte secciones.
5. **Si supera a un ADR anterior**, marcá el anterior como "Superado por NNNN" en su propio archivo,
   y el nuevo lleva "Supera a MMMM" en su header.
6. **Estado inicial**: `Propuesta`. Pedí aprobación explícita y solo cuando la tengas cambialo a
   `Aceptada` con la fecha.

## Reglas

- **Una decisión, un ADR.** Si estás por escribir "decidimos A y también B", son dos ADR.
- **La decisión va en una frase.** El resto del ADR justifica esa frase.
- **Toda alternativa descartada** con una línea de por qué. Sin eso, en seis meses parece que la
  decisión fue obvia y no lo era.
- **Consecuencias negativas también.** Un ADR sin contras es un ADR mal escrito: toda decisión
  técnica cierra puertas.
- **No cambies un ADR aceptado.** Si la decisión cambia, se escribe un ADR nuevo que supera al
  anterior. Los ADR son historia, no wiki.
- **Idioma**: el de la constitución del proyecto.
