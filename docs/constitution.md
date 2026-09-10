# Constitución — Artemisia

1. **Stack mínimo.** Solo Laravel 13 + Boost, Filament 5, MySQL, Spatie Permission y Socialite. Toda dependencia nueva se justifica por escrito en el PR que la agrega; sin frameworks JS adicionales ni segunda librería para algo que el stack ya resuelve.
2. **La spec manda.** Todo cambio nace de una spec en `docs/specs/` y el PR la referencia por nombre. Si el código y la spec divergen, se actualiza la spec en el mismo PR: nunca se mergea código que la contradiga.
3. **Lógica fuera de la interfaz.** Las reglas de negocio viven en Actions/Services y Models. Resources, Pages, Widgets y componentes Livewire de Filament solo orquestan y presentan: ningún cálculo de totales, precios, estados o permisos dentro de una clase de UI.
4. **TDD estricto.** Pest. El test se escribe primero y debe fallar antes de implementar; el commit del test precede al de la implementación. Cada regla de negocio con test unitario y cada flujo crítico con test de feature. Suite verde para mergear.
5. **Persistencia explícita.** Todo cambio de esquema por migración versionada; cero cambios manuales en la base. Presupuestos, piezas, clientes, contratos y pagos nunca se borran físicamente: SoftDeletes más registro de cada cambio de estado con autor y fecha.
6. **Idioma.** Código en inglés: clases, métodos, tablas, columnas, rutas y commits. Interfaz, mensajes de validación, notificaciones, documentación y slugs de rama en español, con los textos en `lang/es`. Ningún string visible al usuario hardcodeado en inglés.
