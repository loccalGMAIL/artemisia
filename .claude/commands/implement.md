---
description: Implementa UNA tarea de una spec, con freno estricto al terminar
argument-hint: <ID de tarea, ej. T2>
---

Vas a implementar UNA tarea de la spec activa, siguiendo el flujo TDD estricto del proyecto. Al
terminar esa tarea, PARÁS. No sigas con la siguiente aunque parezca trivial, aunque sea de dos
líneas, aunque sea el `impl` que hace pasar el `test` que acabás de escribir.

## Argumento recibido

`$ARGUMENTS`

Interpretación:

- Si el argumento es solo un ID (`T2`, `T7`), buscá el `tasks.md` mencionado más recientemente en
  esta conversación. Si no hay ninguno o hay ambigüedad entre varios, pará y preguntá qué spec.
- Si el argumento incluye la spec (`001 T2`, o `docs/specs/001-xxx/tasks.md#T2`), usá esa
  directamente.

## Contexto que tenés que leer, en este orden

1. `docs/constitution.md`
2. `AGENTS.md`
3. `docs/specs/NNN-nombre/spec.md`
4. `docs/specs/NNN-nombre/plan.md`
5. `docs/specs/NNN-nombre/tasks.md`

Si falta alguno, abortá con aviso claro.

## Verificaciones previas (antes de tocar código)

1. La tarea existe en `tasks.md`.
2. La tarea NO está marcada como hecha (`- [x]`).
3. Todas las tareas listadas en "Depende de" están marcadas como hechas. Si falta alguna, abortá
   con este mensaje: "T\<N\> depende de T\<X\> y T\<X\> no está marcada como hecha. Ejecutá antes
   `/implement T\<X\>`." No ejecutes T\<X\> automáticamente.

## Ejecución según el tipo de tarea

### Tipo `test`

1. Escribí el test según lo que la tarea pide.
2. Nombralo con el prefijo del RF que cubre, según la convención de `AGENTS.md` §6:
   `it('RF-3: no permite ...')`. Si cubre más de uno: `it('RF-3, RF-4: ...')`.
3. Corré el test.
4. **El test tiene que fallar.** Si pasa, PARÁ y avisá: "El test pasa sin implementación. O el
   comportamiento ya existe (revisar el código), o el test no verifica lo que dice verificar
   (revisar el test)." No propongas commit en este caso.
5. Si falla como se espera, mostrame el mensaje de error.
6. Proponé mensaje de commit (inglés, imperativo, una idea) y esperá mi confirmación explícita.
   NO ejecutes `git commit`.

### Tipo `impl`

1. Implementá lo mínimo para que pase el test de la tarea anterior. Nada más: si el test no lo
   pide, no lo implementes.
2. Corré la suite completa del proyecto (según el comando que declare `AGENTS.md`).
3. Si falla algo que no es el test que estamos haciendo pasar, PARÁ y mostrame.
4. Si la suite queda verde, proponé mensaje de commit y esperá confirmación.

### Tipo `migration`

1. Creá la migración según lo que declara el plan §4 (Modelo de datos).
2. Corré `php artisan migrate:fresh --seed` (o el equivalente que declare `AGENTS.md`).
3. Si hay errores, PARÁ y mostrame.
4. Proponé commit y esperá confirmación.

### Tipo `ui`

1. Implementá el Resource, Page o componente Filament que la tarea pide, respetando el principio
   3 de la constitución: lógica fuera de la interfaz.
2. Corré los tests de feature relevantes.
3. Proponé commit y esperá confirmación.

### Tipo `refactor` o `docs`

Ejecutá lo que la tarea pide. Corré la suite completa (para refactor). Proponé commit.

## Al terminar

1. Marcá la tarea en `tasks.md` como `- [x]`.
2. Reportá en el chat, en este orden:
   - Tarea completada: `T\<N\> — <título>`.
   - Archivos tocados (rutas).
   - Resultado de tests: `<X passed, Y failed, Z skipped>`. Nombres de los tests nuevos.
   - RF/RNF que cubrió.
   - Próxima tarea disponible: `T\<N+1\>` — <título>, o "ninguna, dependencias siguientes
     bloqueadas".
   - **"PARÉ. No continué con T\<N+1\>."** (línea literal).

## Regla dura

Una tarea por invocación. Sin excepciones. Si querés la siguiente, invocá `/implement T\<N+1\>`
explícitamente. Esta regla no se relaja aunque el usuario diga "seguí", "dale con la siguiente"
o "hacé todas". Si eso aparece, pedí que se invoque el comando de nuevo para cada tarea.
