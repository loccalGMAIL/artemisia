# NNN — \<Nombre de la funcionalidad\>

| | |
|---|---|
| **Estado** | Borrador / En revisión / Aprobada |
| **Fecha** | AAAA-MM-DD |
| **Autor** | |
| **Rama propuesta** | vXX.XX.XX-\<slug\> |

## 1. Problema

Qué duele hoy, en dos o tres frases. Cómo se resuelve actualmente y por qué eso no alcanza.
Sin solución todavía.

## 2. Objetivo

El resultado esperado, en una frase. Debajo, cómo se sabrá que se logró: señales observables,
no sensaciones.

## 3. Actores

Quién usa esto y con qué rol. Uno por línea.

- **\<Actor\>** — qué hace con la funcionalidad.

## 4. Historias de usuario

Narrativa desde el actor: por qué querría usar esto y qué gana. Formato "como \<actor\>, quiero
\<acción\>, para \<beneficio\>". Es el porqué; los criterios verificables van en Requisitos
funcionales.

- **HU-1**: Como \<actor\>, quiero \<acción\>, para \<beneficio\>.
- **HU-2**: …

## 5. Glosario

Los términos del dominio que aparecen en los requisitos, definidos una sola vez. Si un término ya
está definido en una spec anterior, referenciala en vez de redefinirlo.

- **\<Término\>** — definición.

## 6. Alcance

Qué entra, en prosa corta. Es el resumen que alguien lee para saber de qué va la spec sin leer los
requisitos.

## 7. Requisitos funcionales

Notación EARS, uno por línea, numerados y verificables.

- **RF-1**: EL SISTEMA …
- **RF-2**: CUANDO …, EL SISTEMA …
- **RF-3**: SI …, ENTONCES EL SISTEMA …

## 8. Requisitos no funcionales

Solo los que tengan umbral, volumen o tiempo. Si no hay número, no va acá.

- **RNF-1**: …

## 9. Fuera de alcance

Lo que explícitamente NO se construye en esta spec, con una línea de por qué. Si algo se pospone
para otra spec, decilo acá.

- …

## 10. Criterios de finalización

Cuándo la funcionalidad se considera terminada a nivel producto. No confundir con el "Hecho
cuando" de cada tarea, que es operativo. Acá van condiciones observables sobre el sistema completo.

- Todos los RF y RNF tienen tests que pasan.
- \<Criterio de negocio verificable propio de esta spec\>.
- \<Criterio de negocio verificable propio de esta spec\>.

## 11. Dependencias y supuestos

Qué tiene que existir antes (specs previas, datos cargados, decisiones tomadas) y qué se está
dando por cierto sin haberlo verificado.

## 12. Preguntas abiertas

Todo lo que quedó sin resolver, como `[NECESITA ACLARACIÓN: pregunta concreta]`. La spec no se
aprueba con preguntas abiertas: o se responden o se mueven a "Fuera de alcance".

## 13. Aprobación

- [ ] Aprobada por \<nombre\> el AAAA-MM-DD
