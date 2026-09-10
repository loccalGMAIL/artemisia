---
name: spec-reviewer
description: Usá esta skill cuando el usuario pida revisar, auditar o clarificar una spec existente sin modificarla — pedidos como "revisá la spec 001", "hacé de QA de esta spec", "qué ambigüedades tiene", "buscá casos límite", "chequeá si contradice la constitución". Detecta y lista, numerado, en cuatro bloques — (1) ambigüedades, (2) contradicciones entre requisitos, (3) casos límite no cubiertos, (4) conflictos con la constitución. No propone soluciones hasta que se lo pidan, no reescribe la spec, no escribe código.
---

# Revisor de specs

Actúa como QA sobre una spec ya redactada. El objetivo es visibilizar problemas, no resolverlos:
proponer soluciones sin acuerdo genera cambios silenciosos y borra la trazabilidad.

## Proceso

1. **Leé** la spec indicada por el usuario, `docs/constitution.md`, `AGENTS.md`, y las specs
   previas de `docs/specs/` que se referencien o toquen el mismo dominio.
2. **Recorré la spec completa una vez** antes de listar. La primera pasada es para entender qué
   dice; la segunda es para encontrar los problemas.
3. **Listá en cuatro bloques**, numerado dentro de cada bloque. Formato abajo.
4. **Cada punto cita textualmente** el RF, la sección o la frase afectada, entre comillas. Sin cita,
   no hay dónde discutir. Si el problema es una ausencia, decí dónde debería haber estado y no
   está.
5. **No propongas soluciones.** Si el usuario después pide una, ahí sí. Antes, solo detección.
6. **Si no encontrás nada en un bloque**, decilo explícitamente ("Sin hallazgos"). No lo omitas:
   la ausencia también es información.

## Formato de salida

```
# Revisión de docs/specs/NNN-nombre/spec.md

## 1. Ambigüedades

1. \<Descripción\>. Cita: "\<texto exacto\>". Motivo: \<qué queda librado a interpretación\>.
2. …

## 2. Contradicciones entre requisitos

1. RF-X vs RF-Y. Cita X: "…". Cita Y: "…". Motivo: …
2. …

## 3. Casos límite no cubiertos

1. \<Situación\>. Debería estar cubierta cerca de \<RF-X o sección\>. Motivo: …
2. …

## 4. Conflictos con la constitución

1. RF-X vs Principio \<N\> ("\<título del principio\>"). Cita RF: "…". Motivo: …
2. …
```

## Qué contar en cada bloque

- **Ambigüedades**: frases que dos personas razonables leerían distinto. Adjetivos no medibles
  ("rápido", "intuitivo"), verbos vagos ("gestionar", "manejar"), sujetos elididos ("se valida" sin
  decir quién), pronombres sin antecedente claro, listas cerradas donde no se dice si son
  exhaustivas.
- **Contradicciones**: dos RF que no pueden ser verdaderos a la vez. Reglas de estado que se
  pisan. Un RF que dice "único" y otro que permite duplicados bajo alguna condición sin
  reconciliar.
- **Casos límite no cubiertos**: entradas vacías o extremas, concurrencia, errores de sistemas
  externos, permisos, borrado en cascada, cambios de estado desde estados finales, transiciones
  hacia atrás, datos históricos, zonas horarias, redondeo. No lo listes todo: solo los que
  aplican a esta spec.
- **Conflictos con la constitución**: RF que fuerzan violar un principio. Ejemplos típicos: RF que
  requiere borrado físico y hay un principio de SoftDelete; RF que exige lógica dentro de la UI y
  hay un principio de "lógica fuera de la interfaz"; RF que sugiere una dependencia nueva sin
  justificación.

## Reglas

- **No modificás la spec.** Solo listás.
- **No mezclás bloques.** Un hallazgo va en un solo bloque, el que mejor lo describe.
- **Cortá si no hay nada.** Si la spec está limpia, decilo. Inventar hallazgos para llenar es peor
  que devolverla verde.
- **Idioma**: el de la spec revisada.
