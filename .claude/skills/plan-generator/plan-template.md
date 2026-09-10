# NNN — Plan técnico: \<Nombre de la funcionalidad\>

| | |
|---|---|
| **Spec** | docs/specs/NNN-nombre/spec.md (versión aprobada) |
| **Estado** | Borrador / En revisión / Aprobado |
| **Fecha** | AAAA-MM-DD |
| **Autor** | |

## 1. Resumen del enfoque

Dos o tres frases: cómo se resuelve la spec, en términos técnicos. Es lo que alguien lee para saber
por dónde arranca sin leer el plan entero.

## 2. Chequeo contra la constitución

Cada principio, una línea. Cómo lo cumple el plan, o "no aplica" con motivo.

- **Principio 1 (\<título\>)**: …
- **Principio 2 (\<título\>)**: …
- **Principio 3 (\<título\>)**: …
- **Principio 4 (\<título\>)**: …
- **Principio 5 (\<título\>)**: …
- **Principio 6 (\<título\>)**: …

## 3. Decisiones técnicas

Una decisión por bloque. Sin alternativa descartada, no es una decisión.

### D-1: \<Título de la decisión\>

- **Decisión**: qué se elige.
- **Motivo**: por qué.
- **Alternativa descartada**: qué se consideró y por qué no.
- **Consecuencias**: qué implica esto para el resto del plan.

### D-2: …

## 4. Modelo de datos

Tablas nuevas o modificadas. Una por bloque.

### \<nombre_tabla\>

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| … | | | | |

- **Índices**: …
- **Claves foráneas**: …
- **Enums**: `\<EnumClass\>` con valores `\<a, b, c\>`.
- **Soft deletes**: sí / no.

Migraciones a crear, en orden.

## 5. Estructura de módulos

Qué archivos y clases se agregan o modifican, agrupados por capa. No incluye contenido, solo
inventario.

- **Models**: `\<Modelo\>` (nuevo / modificado).
- **Enums**: `\<Enum\>`.
- **Actions**: `\<AccionClase\>` — responsabilidad en una línea.
- **Policies**: `\<Policy\>`.
- **Filament Resources / Pages / Widgets**: qué panel, qué recurso.
- **Rutas**: rutas nuevas o modificadas.
- **Traducciones**: claves nuevas en `lang/es/`.

## 6. Contratos de Actions y servicios

Firma pública de cada Action nueva y qué devuelve. Sin implementación.

- **\<AccionClase\>::handle(\<params\>)**: qué hace, qué devuelve, qué excepciones lanza.

## 7. Reglas de negocio y validaciones

Reglas que viven en Actions o Models (no en la UI), listadas para que las tareas después las cubran
con tests unitarios.

- \<Regla en una frase\>.

## 8. Autorización

Qué roles y permisos participan. Qué policy cubre qué acción.

## 9. Estrategia de tests

- **Unit**: qué reglas y cálculos se cubren.
- **Feature**: qué flujos completos se cubren.
- **Factories nuevas o modificadas**: …
- **Seeders**: solo datos de referencia (categorías, roles, permisos), no datos de prueba.

## 10. Mapa RF → componente

Cada RF y RNF de la spec, ligado a la parte del plan que lo cubre. Si un RF no aparece acá, o el
plan es incompleto o el RF sobra.

| RF | Descripción corta | Cubierto por |
|---|---|---|
| RF-1 | … | Action `\<X\>`, migración `\<Y\>` |
| RF-2 | … | Resource `\<Z\>`, policy `\<W\>` |
| RNF-1 | … | Índice sobre `\<tabla.columna\>` |

## 11. Riesgos

Qué puede salir mal en la implementación, y cómo se mitiga. Un riesgo por línea.

- **\<Riesgo\>**: mitigación.

## 12. Fuera de alcance del plan

Cosas que la spec pide pero que este plan pospone (con motivo), o cosas que no están en la spec y
podrían tentar a hacer y que este plan explícitamente NO hace.

## 13. Preguntas abiertas

`[NECESITA ACLARACIÓN: …]`. El plan no se aprueba con preguntas abiertas.

## 14. Aprobación

- [ ] Aprobado por \<nombre\> el AAAA-MM-DD
