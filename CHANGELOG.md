# Changelog

Todos los cambios notables de este proyecto se documentan en este archivo.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/),
y este proyecto usa [Versionado Semántico](https://semver.org/lang/es/).

## [Sin publicar]

## [0.1.0] - 2026-09-06

### Added

- Base del sistema de gestión: catálogos (categorías de cliente, rubros,
  servicios, precios versionados por fecha, proveedores), presupuestos con
  detalle y cálculo automático de totales, contratos (recurrentes, 3 meses),
  producciones (trabajos puntuales) y resúmenes mensuales con líneas extra.
- Seeders con datos iniciales: categorías, rubros, catálogo de servicios con
  precios de referencia y 6 clientes reales de la agencia.
- 9 recursos de Filament para el panel de staff (Clientes, Presupuestos,
  Contratos, Producciones, Resúmenes mensuales, Servicios, Rubros, Categorías
  de cliente, Proveedores), con la acción "Confirmar" que genera Contrato o
  Producción según los rubros del presupuesto, y "Generar resúmenes" que
  precarga los 3 meses de un contrato con el plan base.
- Dos paneles de Filament separados por subdominio: `admin.` (staff, acceso
  total) y `clientes.` (portal de sólo lectura con Contratos, Producciones y
  Resúmenes mensuales, filtrado automáticamente por el cliente autenticado).
- Home pública con accesos directos a ambos paneles.
- Login de clientes: columna `tipo` (Staff/Cliente) y `cliente_id` en
  `users`, con `canAccessPanel()` como barrera real de acceso por panel.
- `UserResource` en el panel admin para crear/editar logins de staff y
  clientes.
- Selects de Rubro (en Servicios) y Categoría de cliente (en Clientes) con
  alta y edición inline, sin necesidad de una entrada propia en el menú.
- Localización de la app a español (`APP_LOCALE=es`) y horario de Argentina
  (`America/Argentina/Buenos_Aires`).
- Dominio base configurable por entorno (`APP_DOMAIN`): `localhost` en
  desarrollo (sin tocar el `hosts`), dominio real en producción.
- Suite de tests (Pest) para las reglas de negocio principales y el scoping
  de datos del panel de clientes.

[Sin publicar]: https://github.com/loccalGMAIL/artemisia/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/loccalGMAIL/artemisia/releases/tag/v0.1.0
