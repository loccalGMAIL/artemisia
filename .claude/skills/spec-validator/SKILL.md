---
name: spec-validator
description: Usá esta skill cuando la implementación de una spec esté (o parezca) terminada y haya que verificar que los requisitos se cumplen — pedidos como "validá la spec 001", "está cumplida la spec X", "recorré los RF y decime cuáles pasan", "verificá cobertura de la spec". Recorre RF y RNF uno por uno, dice qué test lo cubre y el resultado de ejecutarlo, chequea los criterios de finalización y da un veredicto final (cumplida / no cumplida, con lista de faltantes). No escribe código, no crea tests faltantes, no modifica la spec ni las tareas.
---

# Validador de specs

Cierra el ciclo del SDD: dice si la implementación cumple la spec o no, con evidencia. Sin este
paso, "está terminado" es una opinión.

## Precondiciones

1. Existe `docs/specs/NNN-nombre/spec.md` (aprobada o en cualquier estado — la validación aplica
   igual).
2. Existen los tests del proyecto (`tests/Unit/`, `tests/Feature/` para Laravel; equivalente en
   otros stacks).
3. La suite es ejecutable en el entorno actual.

Si falta algo, avisá antes de empezar. No inventes un veredicto sobre lo que no viste.

## Proceso

1. **Leé**: `docs/specs/NNN-nombre/spec.md` completa. Anotá cada RF y cada RNF.
2. **Inventariá los tests** relevantes. Buscá por nombre, descripción y por RF citado en el test si
   el proyecto lo referencia. No asumas cobertura por proximidad de nombres: verificá.
3. **Ejecutá la suite** (o el subconjunto relevante) y capturá el resultado por test. Si algún
   test falla por entorno (base no migrada, servicio caído), decilo antes de dar veredicto.
4. **Recorré RF por RF y RNF por RNF.** Por cada uno:
   - Test o tests que lo cubren (con nombre y archivo).
   - Resultado de esos tests (pasa / falla / no ejecutado).
   - Si no hay test: `sin cobertura`.
5. **Chequeá los criterios de finalización** de la spec, si los hay. Uno por uno, con evidencia.
6. **Veredicto final**, en el formato de abajo. Sin ambigüedad: cumplida o no cumplida, y por qué.

## Formato de salida

```
# Validación de docs/specs/NNN-nombre/spec.md

## Contexto de ejecución

- Fecha: AAAA-MM-DD HH:MM
- Comando: `<comando ejecutado>`
- Resultado global de la suite: \<X passed, Y failed, Z skipped\>

## Requisitos funcionales

| RF | Descripción | Test(s) | Resultado |
|---|---|---|---|
| RF-1 | … | tests/Unit/…::it_… | ✅ pasa |
| RF-2 | … | tests/Feature/…::it_… | ❌ falla |
| RF-3 | … | — | ⚠️ sin cobertura |

## Requisitos no funcionales

| RNF | Descripción | Verificación | Resultado |
|---|---|---|---|
| RNF-1 | … | benchmark X | ✅ 1.8s (umbral 3s) |

## Criterios de finalización

- [x] \<Criterio 1\> — \<evidencia\>
- [ ] \<Criterio 2\> — \<qué falta\>

## Veredicto

**\<CUMPLIDA / NO CUMPLIDA\>**

Motivos (si no cumplida):
- RF-X sin cobertura.
- RF-Y con test que falla: \<mensaje breve\>.
- Criterio Z incompleto.
```

## Reglas

- **No creás tests.** Si falta cobertura, lo reportás; no lo tapás escribiéndolo vos.
- **No modificás la spec** para acomodarla a lo implementado. Si algo cambió, se enmienda la spec
  con un cambio explícito, no como efecto colateral de la validación.
- **RNF también.** Umbrales de tiempo, tamaño, volumen: si el RNF dice "menos de 3s" y no hay forma
  de verificarlo, es `sin cobertura`, no "presumiblemente cumple".
- **Un test que falla no es sin cobertura**: es cobertura con resultado negativo, y el veredicto
  es distinto.
- **Sé literal con la spec.** Si un RF dice "informa el conflicto", verificá que el test compruebe
  que se informa, no solo que no se crea el duplicado.
- **Veredicto sin gris.** Cumplida o no cumplida. "Casi cumplida" no existe: si falta uno, no
  cumple.

## Al terminar

Además del reporte, decí en el chat:

- Total de RF cubiertos vs sin cobertura.
- Cualquier test que cubra un RF pero de forma parcial (verifica un caso, no todos los del RF).
- Cualquier test que exista y no esté ligado a ningún RF (candidato a documentar o a borrar).
