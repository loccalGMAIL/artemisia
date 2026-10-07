# 0001 — Bootstrap Laravel fuera de specs

| | |
|---|---|
| **Estado** | Aceptada |
| **Fecha** | 2026-09-21 |
| **Autor** | Claudio |
| **Supera a** | — |

## Contexto

El repositorio de Artemisia contiene la constitucion, specs, planes y tareas, pero todavia no contiene una aplicacion Laravel inicializada. La spec `001-acceso-roles-y-paneles` necesita una base Laravel existente para poder implementar usuarios, roles, paneles, tests y migraciones.

Durante la descomposicion de tareas de la spec 001 se incluyo una tarea `T1` para instalar el proyecto Laravel. Esa tarea no cubre ningun RF ni RNF de la spec: habilita el trabajo posterior, pero no implementa una regla funcional ni no funcional del producto. Mantenerla como tarea de la spec contradice la convencion de que las tareas de implementacion cubren requisitos trazables.

## Decision

El bootstrap inicial de Laravel se trata como prerequisito fundacional del proyecto, fuera de cualquier spec funcional.

Esto significa que instalar Laravel, crear `composer.json`, instalar dependencias base, publicar archivos iniciales y dejar disponible la estructura `app/`, `database/`, `resources/` y `tests/` ocurre antes de ejecutar las tareas funcionales de la spec 001. Las specs siguen gobernando todo cambio de producto; el bootstrap solo prepara el soporte tecnico minimo para poder implementarlas.

## Alternativas descartadas

### Mantener el bootstrap como T1 de la spec 001

Permitiria tener una lista lineal desde repo vacio hasta funcionalidad, pero mezcla una preparacion tecnica transversal con una spec funcional y deja una tarea sin cobertura RF/RNF dentro de `tasks.md`.

### Crear una spec propia para bootstrap

Haria trazable la instalacion base, pero seria una spec sin comportamiento de producto ni actores reales. Agrega formalismo sin aportar validacion funcional.

### Instalar Laravel directamente sin registrarlo

Seria lo mas rapido, pero deja sin documentar por que una tarea fundacional queda fuera del flujo habitual de specs y volveria a abrir la discusion al generar nuevas tareas.

## Consecuencias

### Positivas

- Mantiene las tareas funcionales alineadas con RF/RNF trazables.
- Evita que cada spec repita o herede trabajo de instalacion base.
- Deja clara la diferencia entre preparar el proyecto y cambiar comportamiento del producto.
- Permite que `001/tasks.md` empiece desde configuracion e implementacion funcional sobre una aplicacion ya existente.

### Negativas

- El bootstrap queda como paso obligatorio previo que no se valida con el spec-validator.
- Hace falta recordar ejecutar y revisar ese prerequisito antes de comenzar la spec 001.
- El primer PR del proyecto puede no referenciar una spec funcional, sino este ADR y la preparacion tecnica.

## Referencias

- Specs afectadas: `docs/specs/001-acceso-roles-y-paneles/spec.md`, `docs/specs/003-clientes/spec.md`, `docs/specs/002-presupuestos/spec.md`, `docs/specs/004-piezas/spec.md`
- Tareas afectadas: `docs/specs/001-acceso-roles-y-paneles/tasks.md`
- Constitucion: `docs/constitution.md`
- ADR relacionados: —
