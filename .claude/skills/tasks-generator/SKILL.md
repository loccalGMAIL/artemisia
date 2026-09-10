---
name: tasks-generator
description: Usá esta skill cuando exista una spec y un plan aprobados, y haga falta descomponer el trabajo en tareas concretas — pedidos como "generá las tareas de la 001", "armemos el tasks.md", o cuando el usuario mencione pasar del plan a la implementación. Produce docs/specs/NNN-nombre/tasks.md a partir de spec.md y plan.md. Cada tarea es corta (20-30 min), en orden de dependencias, con TDD estricto donde el commit del test precede al de la implementación. No redacta specs (esa es spec-generator), no redacta planes (esa es plan-generator) y no escribe código.
---

# Generador de tareas

Convierte un plan aprobado en una lista de tareas ordenada, con criterios de terminación
verificables. Es lo que hace posible frenar después de cada paso y revisar en chico.

## Precondiciones

1. Existe `docs/specs/NNN-nombre/spec.md` aprobada.
2. Existe `docs/specs/NNN-nombre/plan.md` aprobado y sin `[NECESITA ACLARACIÓN]`.
3. Existe `docs/constitution.md`.

Si alguna falla, no arranques: decí qué falta.

## Proceso

1. **Leé**: `spec.md`, `plan.md`, `docs/constitution.md`, `AGENTS.md`. Revisá los `tasks.md` de
   specs anteriores para respetar convenciones.
2. **Descomponé el plan** en tareas de 20-30 minutos de trabajo real. Si una tarea se estira más,
   partila.
3. **Ordená por dependencias.** Cada tarea depende como mucho de las anteriores. Si dos son
   paralelas, marcalas y explicá.
4. **Aplicá TDD estricto** (constitución principio 4): cada regla de negocio y cada flujo crítico
   tiene una tarea de test que precede a la de implementación. El commit del test va antes que el
   de la implementación. Nombralas `T\<N\>-test` y `T\<N\>-impl` cuando ayude a leer.
5. **Cubrí todos los RF y RNF** del plan. Al final, cada RF tiene que aparecer en al menos una
   tarea. Si falta cobertura, o el plan no lo cubría o hay que sumar tarea.
6. **Redactá** usando `tasks-template.md` de esta skill.
7. **Pedí aprobación explícita.** No arranques a implementar hasta tenerla.

## Reglas por tarea

Cada tarea tiene, sin excepción:

- **ID** correlativo `T1, T2, T3…`.
- **Título** imperativo y corto ("Crear migración de `services`", "Escribir test de duplicado por
  nombre").
- **Tipo**: `test` | `impl` | `migration` | `ui` | `refactor` | `docs`.
- **Cubre**: RF-x, RF-y (o `RNF-x`). Si no cubre nada, no va: es andamiaje que sobra o hay que
  reencuadrarla.
- **Depende de**: IDs anteriores o `—`.
- **Hecho cuando**: criterio verificable en una frase. Sin "debería funcionar" ni "queda listo".
- **Checkbox** al inicio: `- [ ]`.

## Reglas del conjunto

- **Orden TDD**: dentro de cada bloque funcional, el test antecede a la implementación. La suite
  tiene que quedar verde después de cada `impl`, aunque falten features.
- **Nada de "implementar toda la funcionalidad X" como una tarea**: eso es una spec, no una tarea.
- **Sin dependencias circulares.** Si `T5` depende de `T7`, algo está mal numerado.
- **Fases opcionales** si ayudan a leer (Migraciones, Actions, Filament, Portal), pero el orden
  global manda por sobre la fase.
- **Máximo ~40 tareas por spec.** Si te vas mucho más allá, la spec era demasiado grande y hay que
  partirla, no espesar el `tasks.md`.

## Al terminar

Además del `tasks.md`, decí en el chat:

- Total de tareas y cuántas son de test vs impl.
- Cualquier RF que hayas tenido que cubrir con más de una tarea y por qué.
- Cualquier hueco del plan que se te haya hecho visible al descomponer (candidato a volver al
  plan, no a taparlo en las tareas).
