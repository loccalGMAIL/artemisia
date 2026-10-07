---
description: Implementa una spec completa tarea por tarea, con TDD estricto y commits automáticos
argument-hint: <spec, ej. 001> [tarea desde la que arrancar, ej. T12]
---

Vas a implementar TODAS las tareas pendientes de una spec, de corrido, siguiendo el flujo TDD
estricto del proyecto. Commiteás vos, sin pedir confirmación por cada tarea. Parás solo ante una
condición de freno (ver más abajo) o al terminar la spec.

## Argumento recibido

`$ARGUMENTS`

- Primer valor: número de spec (`001`) o ruta a su `tasks.md`.
- Segundo valor, opcional: ID de tarea desde la que arrancar (`T12`). Si falta, arrancás en la
  primera tarea sin `- [x]`.

## Contexto que tenés que leer, en este orden

1. `docs/constitution.md`
2. `AGENTS.md`
3. `docs/specs/NNN-nombre/spec.md`
4. `docs/specs/NNN-nombre/plan.md`
5. `docs/specs/NNN-nombre/tasks.md`

Si falta alguno, abortá con aviso claro.

## Antes de arrancar

1. Confirmá que estás en una rama con formato `vXX.XX.XX-slug` (AGENTS.md §5) y no en `master`.
2. El árbol de trabajo tiene que estar limpio. Si no, avisá y pará.
3. Corré la suite y Pint una vez para partir de verde.

## Bucle por tarea

Recorré `tasks.md` en orden. Para cada tarea sin `- [x]`:

1. Verificá que todas las tareas de "Depende de" estén marcadas. Si no, pará y avisá.
2. Ejecutá según el tipo:
   - `test`: escribí el test con el prefijo del RF/RNF (`it('RF-3: ...')`, AGENTS.md §6). Corrélo:
     **tiene que fallar**, y por el motivo esperado (falta la clase o el comportamiento), no por
     un error de sintaxis.
   - `impl`: lo mínimo para que pase el test anterior. Suite completa verde y
     `./vendor/bin/pint` aplicado.
   - `migration`: según plan §4. `php artisan migrate:fresh --seed` sin errores.
   - `ui`: Resource/Page de Filament que solo orquesta (constitución, principio 3). Tests de
     feature relevantes verdes.
   - `refactor` o `docs`: lo que pida la tarea; suite verde si toca código.
3. Los textos visibles van a `lang/es`, nunca hardcodeados (constitución, principio 6).
4. Marcá la tarea `- [x]` en `tasks.md`.
5. Commit propio por tarea: inglés, imperativo, una idea, con el `Co-Authored-By` que indique la
   atribución vigente. El commit del test siempre precede al de su implementación. Incluí
   `tasks.md` en el mismo commit.

Pint se corre antes de cada commit; si reformatea, el cambio entra en ese mismo commit.

## Condiciones de freno

Parás, NO commiteás lo roto, y reportás qué pasó y qué opciones hay, cuando:

- una tarea `test` pasa sin implementación (o el test no verifica lo que dice verificar);
- la suite queda roja y no se arregla dentro del alcance de la tarea actual;
- aparece una decisión que la spec y el plan no resuelven (ambigüedad, contradicción entre
  documentos, dependencia fuera del stack de la constitución, cambio de la spec): preguntá con
  opciones y recomendación;
- hace falta una acción destructiva o externa no prevista (borrar datos, force-push, mergear,
  publicar credenciales, tocar bases que no sean las del proyecto).

No frenan: decisiones menores de implementación que no contradicen spec ni plan (un default de
columna, un nombre de método privado). Anotalas para el reporte final.

Si la spec y el código divergen, se actualiza la spec en el mismo PR (constitución, principio 2),
pero eso es una decisión: frená y preguntá antes de cambiarla.

## Al terminar la spec

1. Suite completa y `./vendor/bin/pint --test` en verde.
2. Invocá la skill `spec-validator` sobre la spec y mostrá su veredicto. Si hay faltantes, listalos
   y pará sin abrir el PR.
3. `git push -u origin <rama>` y abrí el PR contra `master` con `gh pr create`: spec de origen,
   qué cambia, cómo probarlo, decisiones tomadas; cierre con la línea de atribución vigente.
4. **No mergees.** El merge lo pide el usuario.

## Reporte final

- Tareas hechas (rango) y cantidad de commits.
- Resultado de la suite: `<X passed, Y failed, Z skipped>`.
- Veredicto del `spec-validator`.
- Decisiones menores tomadas, una línea cada una.
- Link del PR.
