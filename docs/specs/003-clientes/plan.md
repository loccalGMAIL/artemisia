# 003 — Plan técnico: Clientes

| | |
|---|---|
| **Spec** | docs/specs/003-clientes/spec.md (versión aprobada) |
| **Estado** | Aprobado |
| **Fecha** | 2026-09-10 |
| **Autor** | Claudio |

## 1. Resumen del enfoque

El cliente es una entidad propia (`Client`) con archivado nativo de Laravel (SoftDeletes) para
cumplir "nunca se borra físicamente"; domicilio embebido por ser 1:1; contactos en tabla propia con
un principal garantizado por constraint de base; vínculo con cuentas del portal resuelto con una
columna `client_id` en `users`; y un historial genérico de una sola tabla (`client_histories`) que
registra cada alta y cada cambio con su snapshot antes/después.

## 2. Chequeo contra la constitución

- **Principio 1 (Stack mínimo)**: sin dependencias nuevas. La exportación del listado (RF-56) se
  resuelve con CSV generado con las funciones nativas de PHP, no con un paquete de exportación.
- **Principio 2 (La spec manda)**: este plan cubre exactamente los RF/RNF de
  `docs/specs/003-clientes/spec.md`; ningún componente agrega comportamiento no pedido.
- **Principio 3 (Lógica fuera de la interfaz)**: toda regla (unicidad de documento, contacto
  principal, teléfono de contacto, archivado/restauración, autorización del portal) vive en
  Actions y en el modelo `Client`; los recursos Filament solo listan, filtran y disparan Actions.
- **Principio 4 (TDD estricto)**: ver sección 9; cada regla listada en la sección 7 tiene su test
  unitario antes de implementarse.
- **Principio 5 (Persistencia explícita)**: `clients` usa SoftDeletes (archivar = soft delete,
  restaurar = restore), nunca hard delete; `client_histories` es una tabla de solo alta (sin
  update ni delete) que preserva cada cambio, incluidos los contactos quitados.
- **Principio 6 (Idioma)**: clases, columnas y migraciones en inglés (`Client`, `ClientContact`,
  `ClientHistory`); toda etiqueta, mensaje y validación visible sale de `lang/es/clients.php`.

## 3. Decisiones técnicas

### D-1: Domicilio embebido en `clients`, sin tabla propia

- **Decisión**: calle, número, localidad, provincia y código postal son columnas nullable de la
  tabla `clients`.
- **Motivo**: RF-11 fija la cardinalidad en "a lo sumo un domicilio por cliente"; es un 1:1
  opcional, no una colección.
- **Alternativa descartada**: tabla `client_addresses` separada. Añade un join permanente para un
  dato que siempre es único por cliente, sin ningún beneficio.
- **Consecuencias**: `UpdateClientAddressAction` opera directamente sobre `clients`.

### D-2: Nombre para mostrar calculado, no almacenado

- **Decisión**: `Client::displayName()` es un accessor que arma el nombre a partir de
  `first_name`/`last_name` o `company_name` según `person_type`. La búsqueda (RF-52) consulta esas
  columnas directamente, no una columna derivada.
- **Motivo**: evita mantener sincronizada una copia desnormalizada ante cada edición.
- **Alternativa descartada**: columna `display_name` persistida y recalculada en cada guardado.
  Agrega una fuente más de inconsistencia sin necesidad real (5.000 filas no justifican
  desnormalizar para performance, ver RNF-5).
- **Consecuencias**: el listado y el PDF de `002-presupuestos` consumen el accessor, no una
  columna.

### D-3: Archivado = SoftDeletes de Laravel sobre `clients`

- **Decisión**: `Client` usa el trait `SoftDeletes`; archivar (RF-28) es `delete()`, restaurar
  (RF-32) es `restore()`.
- **Motivo**: coincide exactamente con "SoftDeletes… con autor y fecha" del principio 5 y con
  RF-28 a RF-34, con soporte nativo del framework (scopes, `withTrashed()`, `restore()`).
- **Alternativa descartada**: columna `archived_at` propia con su propio scope global. Duplica lo
  que SoftDeletes ya resuelve, violando el minimalismo del principio 1.
- **Consecuencias**: el listado (RF-50) usa el scope por defecto de Eloquent (excluye
  soft-deleted); RF-51 usa `withTrashed()`.

### D-4: Unicidad de documento con índice único simple, incluyendo archivados

- **Decisión**: índice `UNIQUE` sobre `clients.document`, sin condicionarlo a `deleted_at IS
  NULL`.
- **Motivo**: RF-6 exige que la verificación de duplicados incluya a los clientes archivados. El
  patrón habitual `unique()->whereNull('deleted_at')` dejaría reutilizar el documento de un
  archivado, violando el RF.
- **Alternativa descartada**: índice único parcial condicionado a `deleted_at IS NULL` (el patrón
  estándar de Laravel con SoftDeletes). Descartado por la razón anterior.
- **Consecuencias**: la validación de unicidad en `CreateClientAction` debe consultar con
  `withTrashed()` para dar el mensaje de conflicto correcto (RF-6), no solo confiar en el error de
  base de datos.

### D-5: Historial genérico en una única tabla

- **Decisión**: `client_histories` con un discriminador `field` (enum `ClientHistoryField`:
  `identification`, `address`, `contacts`, `status`, `archived`, `account_link`) y columnas JSON
  `old_value` / `new_value`.
- **Motivo**: todas las secciones de la spec piden la misma forma de registro (valor anterior,
  valor nuevo, autor, fecha); una tabla por sección multiplicaría migraciones idénticas.
- **Alternativa descartada**: una tabla de historial por cada área (identificación, domicilio,
  contactos, estado, archivado, vínculos). Descartada por redundancia de esquema.
- **Consecuencias**: toda Action que modifica un cliente escribe un `ClientHistory::create()` al
  final de su `handle()`.

### D-6: Vínculo cuenta-cliente como columna en `users`, no tabla pivote

- **Decisión**: columna `client_id` nullable + FK en la tabla `users` (definida por la spec
  `001-acceso-roles-y-paneles`).
- **Motivo**: la cardinalidad es 1 cliente → N cuentas y 1 cuenta → a lo sumo 1 cliente (RF-39,
  RF-40); es exactamente lo que resuelve una FK del lado "muchos", sin tabla intermedia.
- **Alternativa descartada**: tabla pivote `client_account_links`. Sería necesaria si una cuenta
  pudiera vincularse a más de un cliente, cosa que RF-40 prohíbe explícitamente.
- **Consecuencias**: este plan agrega una migración que modifica `users`, tabla que pertenece a la
  spec `001`. Ver riesgo en sección 11.

### D-7: Exportación del listado en CSV nativo

- **Decisión**: `ExportClientListAction` arma el CSV con `fputcsv` sobre un stream, aplicando los
  mismos filtros, búsqueda y orden que el listado.
- **Motivo**: cumple RF-56 sin agregar dependencia.
- **Alternativa descartada**: paquete `maatwebsite/excel` o similar. No está en la lista de
  dependencias del principio 1 y un CSV no lo justifica.
- **Consecuencias**: el archivo exportado es `.csv`, no `.xlsx`.

### D-8: Contacto principal garantizado también a nivel de base

- **Decisión**: columna generada virtual `primary_marker` (`client_id` si `is_primary = true`,
  si no `NULL`) con índice `UNIQUE` sobre esa columna, además de la validación en la Action.
- **Motivo**: refuerza RF-17 (exactamente un principal) incluso ante una escritura concurrente que
  la validación de aplicación no llegue a serializar.
- **Alternativa descartada**: confiar solo en la regla de negocio de la Action. Una carrera entre
  dos solicitudes podría dejar dos contactos marcados como principales a la vez.
- **Consecuencias**: cualquier cambio de principal debe des-marcar el anterior y marcar el nuevo
  dentro de la misma transacción, en ese orden.

### D-9: Contactos quitados se eliminan físicamente; el historial preserva el snapshot

- **Decisión**: `RemoveClientContactAction` hace `delete()` físico sobre la fila de
  `client_contacts`; el dato queda preservado únicamente como snapshot en `client_histories`.
- **Motivo**: el principio 5 nombra explícitamente a "clientes" (no a sus contactos) entre las
  entidades que nunca se borran físicamente; el mismo patrón usa `002-presupuestos` para sus
  ítems quitados.
- **Alternativa descartada**: SoftDeletes también en `client_contacts`. Sería redundante: el dato
  ya queda preservado en `client_histories`, y mantener ambos duplica el mismo hecho.
- **Consecuencias**: `client_contacts` no lleva `deleted_at`.

### D-10: `provinces` como tabla de referencia sembrada

- **Decisión**: tabla `provinces` (id, name) sembrada por seeder con las 24 provincias argentinas;
  `clients.province_id` es FK nullable a `provinces`.
- **Motivo**: la spec supone en su sección 9 que "las provincias… son datos de referencia fijos,
  cargados al instalar el sistema y no administrados desde la interfaz".
- **Alternativa descartada**: columna de texto libre para provincia. Permitiría inconsistencias de
  escritura ("Bs As" vs "Buenos Aires") que una FK a referencia fija evita.
- **Consecuencias**: un seeder `ProvinceSeeder` se agrega a `database/seeders`.

### D-11: "Tipos de documento" no necesita tabla de referencia propia

- **Decisión**: el tipo de documento no se modela como catálogo aparte; queda determinado 1:1 por
  `person_type` (física → DNI, jurídica → CUIT), tal como fijan RF-3 y RF-4.
- **Motivo**: la spec no define ni un tercer tipo de documento ni una combinación distinta a esa;
  agregar un catálogo para dos valores fijos y ya cubiertos por el enum de tipo de persona sería
  esquema sin uso.
- **Alternativa descartada**: tabla `document_types` sembrada, sugerida de forma genérica por el
  supuesto de la sección 9 de la spec. Se deja de lado porque ningún RF pide administrarla ni
  admite un tercer valor.
- **Consecuencias**: ninguna columna `document_type_id`; la etiqueta ("DNI" o "CUIT") se resuelve
  en la UI a partir de `person_type`.

## 4. Modelo de datos

### provinces

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| name | string(60) | no | — | único |

- **Índices**: único sobre `name`.
- **Soft deletes**: no.

### clients

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| person_type | string(20) | no | — | enum `ClientType`: `individual`, `company` |
| first_name | string(150) | sí | null | requerido si `person_type = individual` (RF-3) |
| last_name | string(150) | sí | null | requerido si `person_type = individual` (RF-3) |
| company_name | string(150) | sí | null | requerido si `person_type = company` (RF-4) |
| document | string(11) | no | — | solo dígitos; DNI (7-8) o CUIT (11) normalizado |
| status | string(20) | no | `active` | enum `ClientStatus`: `active`, `inactive` |
| street | string(150) | sí | null | domicilio (RF-9) |
| street_number | string(20) | sí | null | |
| city | string(100) | sí | null | |
| province_id | bigint unsigned | sí | null | FK a `provinces` |
| postal_code | string(15) | sí | null | |
| created_by | bigint unsigned | no | — | FK a `users`, autor del alta |
| created_at / updated_at | timestamp | — | — | |
| deleted_at | timestamp | sí | null | archivado (SoftDeletes) |

- **Índices**: `UNIQUE(document)` (incluye archivados, ver D-4); índice sobre `status`; índice
  sobre `deleted_at`; índice sobre `created_at` (orden por fecha de alta, RF-54).
- **Claves foráneas**: `province_id` → `provinces.id` (nullable, `set null` on delete);
  `created_by` → `users.id`.
- **Enums**: `ClientType` (`individual`, `company`); `ClientStatus` (`active`, `inactive`).
- **Soft deletes**: sí.

### client_contacts

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| client_id | bigint unsigned | no | — | FK a `clients` |
| name | string(150) | no | — | |
| role | string(100) | sí | null | cargo |
| phone | string(30) | sí | null | al menos uno entre phone/email (RF-15) |
| email | string(150) | sí | null | |
| is_primary | boolean | no | false | |
| primary_marker | bigint unsigned, generado | sí | — | `client_id` si `is_primary`, si no `NULL` (D-8) |
| created_at / updated_at | timestamp | — | — | |

- **Índices**: `UNIQUE(primary_marker)`; índice sobre `client_id`.
- **Claves foráneas**: `client_id` → `clients.id` (`cascade` on delete solo aplicable si algún día
  se purgara `clients`, lo cual el principio 5 prohíbe; en la práctica nunca dispara).
- **Soft deletes**: no (ver D-9).

### client_histories

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| id | bigint unsigned | no | auto | PK |
| client_id | bigint unsigned | no | — | FK a `clients` |
| field | string(30) | no | — | enum `ClientHistoryField` |
| old_value | json | sí | null | null en el alta (RF-35) |
| new_value | json | no | — | |
| author_id | bigint unsigned | no | — | FK a `users` |
| created_at | timestamp | no | — | sin `updated_at`: tabla de solo alta |

- **Índices**: índice compuesto `(client_id, created_at)` para la consulta cronológica (RF-37).
- **Claves foráneas**: `client_id` → `clients.id`; `author_id` → `users.id`.
- **Enums**: `ClientHistoryField` (`identification`, `address`, `contacts`, `status`, `archived`,
  `account_link`).
- **Soft deletes**: no aplica (nunca se actualiza ni se borra una fila ya escrita).

### users (modificación — tabla de la spec `001-acceso-roles-y-paneles`)

| Columna | Tipo | Nulo | Default | Notas |
|---|---|---|---|---|
| client_id | bigint unsigned | sí | null | FK a `clients`; solo tiene sentido en cuentas con rol `cliente` |

- **Índices**: índice sobre `client_id`.
- **Claves foráneas**: `client_id` → `clients.id` (`set null` on delete, aunque `clients` nunca
  hace hard delete).

Migraciones a crear, en orden:

1. `create_provinces_table`
2. `create_clients_table`
3. `create_client_contacts_table`
4. `create_client_histories_table`
5. `add_client_id_to_users_table`

## 5. Estructura de módulos

- **Models**: `Client` (nuevo, con `HasFactory`, `SoftDeletes`), `ClientContact` (nuevo),
  `ClientHistory` (nuevo), `Province` (nuevo); `User` (modificado: relación `client()`).
- **Enums**: `ClientType`, `ClientStatus`, `ClientHistoryField`.
- **Actions**:
  - `CreateClientAction` — alta, valida identificación y unicidad, deja activo, registra historial.
  - `UpdateClientIdentificationAction` — edita nombre/apellido/razón social/documento; rechaza
    cambio de `person_type`.
  - `UpdateClientAddressAction` — edita el domicilio embebido.
  - `AddClientContactAction` — agrega contacto; marca principal si es el primero; valida tope
    (RNF-4) y que tenga teléfono o email.
  - `UpdateClientContactAction` — edita un contacto existente.
  - `RemoveClientContactAction` — quita un contacto; reasigna principal si correspondía (RF-20).
  - `SetPrimaryContactAction` — cambia cuál contacto es el principal.
  - `ActivateClientAction` / `DeactivateClientAction` — cambian `status`.
  - `ArchiveClientAction` — soft delete + fuerza `status = inactive`.
  - `RestoreClientAction` — restore + mantiene `status = inactive`.
  - `LinkAccountToClientAction` — valida que la cuenta no tenga otro vínculo, asigna `client_id`.
  - `UnlinkAccountFromClientAction` — limpia `client_id`, invalida la sesión activa de esa cuenta.
  - `ExportClientListAction` — genera el CSV del listado filtrado.
- **Policies**: `ClientPolicy` (staff: `viewAny`, `view`, `create`, `update`, `archive`, `restore`,
  `linkAccount`, `unlinkAccount`, `export` — todas iguales para `admin` y `staff`);
  `ClientPortalPolicy` (cliente: `view`, `updateAddress`, `updateContacts`, siempre acotado a
  `auth()->user()->client_id`).
- **Filament Resources / Pages / Widgets**:
  - Panel `staff`: `ClientResource` (list/create/edit/view), relation manager de contactos,
    relation manager de historial (solo lectura), acciones de archivar/restaurar/exportar,
    sección de vínculo de cuentas.
  - Panel `client`: `ClientProfilePage` — página única con la ficha propia, formularios de
    domicilio y contactos.
- **Rutas**: ninguna manual; ambos paneles ya existen (spec `001`) y Filament resuelve el ruteo de
  sus recursos.
- **Traducciones**: `lang/es/clients.php` con etiquetas de campos, mensajes de conflicto (RF-6,
  RF-40) y mensajes de permiso insuficiente (RF-43, RF-62).

## 6. Contratos de Actions y servicios

- **CreateClientAction::handle(array $data): Client** — valida identificación según
  `person_type`, normaliza y verifica unicidad de `document` (con `withTrashed()`), crea el
  cliente activo, registra `ClientHistory` de alta. Lanza `ValidationException` en conflicto de
  documento o campos faltantes.
- **UpdateClientIdentificationAction::handle(Client $client, array $data): Client** — rechaza si
  `data['person_type']` difiere del actual; registra historial con snapshot anterior/nuevo.
- **UpdateClientAddressAction::handle(Client $client, array $data): Client** — sin validaciones de
  obligatoriedad; registra historial.
- **AddClientContactAction::handle(Client $client, array $data): ClientContact** — valida tope de
  10 contactos y teléfono-o-email; marca principal si es el primero; registra historial.
- **UpdateClientContactAction::handle(ClientContact $contact, array $data): ClientContact** —
  registra historial con el cliente dueño del contacto.
- **RemoveClientContactAction::handle(ClientContact $contact): void** — si era principal y quedan
  otros, marca otro como principal en la misma transacción; registra historial con el snapshot
  del contacto quitado.
- **SetPrimaryContactAction::handle(Client $client, ClientContact $contact): void** — desmarca el
  principal anterior y marca el nuevo dentro de una transacción.
- **ActivateClientAction::handle(Client $client): Client** / **DeactivateClientAction::handle**
  — cambian `status`, registran historial.
- **ArchiveClientAction::handle(Client $client): Client** — `delete()` + `status = inactive`,
  todo en una transacción; registra historial.
- **RestoreClientAction::handle(Client $client): Client** — `restore()`, mantiene
  `status = inactive`; registra historial.
- **LinkAccountToClientAction::handle(Client $client, User $account): void** — lanza excepción si
  `$account->client_id` ya está asignado a otro cliente; registra historial.
- **UnlinkAccountFromClientAction::handle(User $account): void** — limpia el vínculo e invalida la
  sesión activa de esa cuenta (coordinado con el mecanismo de corte de sesión de la spec `001`);
  registra historial.
- **ExportClientListAction::handle(array $filters): StreamedResponse** — aplica los mismos
  filtros/orden/búsqueda que el listado y devuelve el CSV en streaming.

## 7. Reglas de negocio y validaciones

- Persona física exige nombre, apellido y DNI; persona jurídica exige razón social y CUIT (RF-1
  a RF-4).
- El tipo de persona no se puede cambiar una vez creado el cliente (RF-8).
- El documento se normaliza (sin espacios, puntos ni guiones) y es único, incluidos los clientes
  archivados (RF-6).
- El domicilio es opcional y a lo sumo uno por cliente (RF-9 a RF-12).
- Cada contacto exige al menos un teléfono o un email (RF-15); hasta 10 contactos por cliente
  (RNF-4).
- Exactamente un contacto principal cuando el cliente tiene al menos un contacto (RF-16 a RF-18,
  RF-20).
- El teléfono de contacto del cliente es el del principal, o el de otro contacto si el principal
  no tiene teléfono cargado (RF-21); su ausencia total se señala explícitamente (RF-22).
- El alta deja el cliente activo (RF-24); archivar fuerza el estado inactivo (RF-29); restaurar
  mantiene el estado inactivo (RF-33).
- Un cliente inactivo o archivado no se ofrece para un presupuesto nuevo, pero conserva ficha e
  historial (RF-26, RF-27, RF-30).
- Toda alta y toda modificación de identificación, domicilio, contactos, estado o archivado queda
  registrada con valor anterior, valor nuevo, autor y fecha (RF-35, RF-36).
- Una cuenta se vincula a lo sumo a un cliente; un cliente admite varias cuentas vinculadas (RF-39,
  RF-40).
- Desvincular una cuenta con sesión abierta corta esa sesión (RF-42).
- Un cliente archivado bloquea el acceso al portal de sus cuentas vinculadas (RF-58).
- Desde el portal, una cuenta vinculada solo edita domicilio y contactos, nunca identificación,
  estado ni archivado (RF-59, RF-60); cada edición propia queda registrada con esa cuenta como
  autor (RF-61).
- DNI: exactamente 7 u 8 dígitos numéricos (RNF-2). CUIT: 11 dígitos numéricos con dígito
  verificador válido según el algoritmo módulo 11 estándar de AFIP (RNF-3).

## 8. Autorización

- `admin` y `staff` tienen exactamente los mismos permisos sobre clientes: alta, edición,
  contactos, activar/desactivar, archivar/restaurar, vincular/desvincular cuentas, consultar
  historial y exportar. `ClientPolicy` no distingue entre ambos roles.
- `cliente` nunca pasa por `ClientPolicy`: sus acciones se autorizan con `ClientPortalPolicy`,
  siempre acotadas a `auth()->user()->client_id`; cualquier intento sobre un `client_id` distinto
  se rechaza (RF-62).
- Un cliente archivado revoca el `view` de `ClientPortalPolicy` para sus cuentas vinculadas
  (RF-58), aunque la cuenta en sí siga activa según la spec `001`.

## 9. Estrategia de tests

- **Unit**: unicidad de documento incluyendo archivados; exigencia de campos según tipo de
  persona; inmutabilidad de `person_type`; reasignación del contacto principal al quitar el
  actual; cálculo del teléfono de contacto con y sin teléfono en el principal; validación de
  formato de DNI y del dígito verificador de CUIT; transición archivar → inactivo y restaurar →
  inactivo; tope de 10 contactos.
- **Feature**: alta completa de un cliente desde el panel de staff; búsqueda, filtros, orden y
  paginado del listado; exportación del listado filtrado; vinculación y desvinculación de una
  cuenta (con corte de sesión); acceso denegado a una cuenta sin vínculo; autogestión del
  domicilio y los contactos desde el portal de clientes; rechazo al intentar ver la ficha de otro
  cliente; bloqueo del portal para un cliente archivado.
- **Factories nuevas**: `ClientFactory`, `ClientContactFactory`, `ClientHistoryFactory`.
- **Seeders**: `ProvinceSeeder` (datos de referencia, no de prueba).

## 10. Mapa RF → componente

| RF | Descripción corta | Cubierto por |
|---|---|---|
| RF-1, RF-2, RF-3, RF-4, RF-5 | Alta con tipo de persona y campos requeridos | `CreateClientAction`, migración `create_clients_table` |
| RF-6 | Unicidad de documento, incluidos archivados | Índice `UNIQUE(document)`, `CreateClientAction` (D-4) |
| RF-7 | Edición de identificación | `UpdateClientIdentificationAction` |
| RF-8 | Tipo de persona inmutable | `UpdateClientIdentificationAction` |
| RF-9, RF-10, RF-11, RF-12 | Domicilio opcional, único, editable | Columnas de domicilio en `clients`, `UpdateClientAddressAction` (D-1) |
| RF-13, RF-14, RF-15 | Alta de contactos | migración `create_client_contacts_table`, `AddClientContactAction` |
| RF-16, RF-17, RF-18, RF-20 | Contacto principal | `primary_marker` + índice único (D-8), `SetPrimaryContactAction`, `RemoveClientContactAction` |
| RF-19 | Editar y quitar contactos | `UpdateClientContactAction`, `RemoveClientContactAction` |
| RF-21, RF-22 | Teléfono de contacto del cliente | Accessor `Client::contactPhone()` |
| RF-23, RF-24 | Estado activo/inactivo, alta activo | Enum `ClientStatus`, `CreateClientAction` |
| RF-25 | Marcar inactivo / reactivar | `ActivateClientAction`, `DeactivateClientAction` |
| RF-26, RF-30 | No ofrecido en presupuestos nuevos | Scope `Client::availableForBudgets()` |
| RF-27 | Ficha e historial consultables inactivo | `ClientResource` (sin filtrar por estado) |
| RF-28, RF-32 | Archivar / restaurar | `ArchiveClientAction`, `RestoreClientAction`, SoftDeletes (D-3) |
| RF-29, RF-33 | Archivar/restaurar fuerza inactivo | Lógica interna de ambas Actions |
| RF-31 | Conserva ficha e historial archivado | SoftDeletes, sin hard delete |
| RF-34 | Nunca elimina cliente ni historial | SoftDeletes + `client_histories` append-only |
| RF-35, RF-36 | Registro de alta y de cambios | Tabla `client_histories`, escritura al final de cada Action |
| RF-37 | Consulta cronológica del historial | Relation manager de historial en `ClientResource` |
| RF-38, RF-39, RF-40 | Vincular cuenta, varias por cliente, una cuenta a un solo cliente | `users.client_id`, `LinkAccountToClientAction` (D-6) |
| RF-41 | Desvincular cuenta | `UnlinkAccountFromClientAction` |
| RF-42 | Corte de sesión al desvincular | `UnlinkAccountFromClientAction` |
| RF-43 | Cuenta sin vínculo, mensaje de acceso no habilitado | `ClientPortalPolicy`, página del portal |
| RF-44 | Registro de vinculación/desvinculación | `client_histories` (`field = account_link`) |
| RF-45, RF-46, RF-47, RF-48 | Ficha con historial comercial | `ClientResource` (página Ver), secciones condicionales |
| RF-49, RF-50, RF-51, RF-53, RF-54, RF-55 | Listado: columnas, archivado oculto/filtro, filtros, orden, paginado | `ClientResource` (tabla) |
| RF-52 | Búsqueda por texto | `Client::scopeSearch()` |
| RF-56 | Exportación del listado | `ExportClientListAction` (D-7) |
| RF-57, RF-59, RF-62 | Ficha propia y su edición en el portal | `ClientProfilePage`, `ClientPortalPolicy` |
| RF-58 | Bloqueo de portal si archivado | `ClientPortalPolicy` |
| RF-60 | Cliente no edita identificación/estado/archivado | `ClientPortalPolicy` |
| RF-61 | Autoría de la edición propia | Actions de domicilio/contactos, `client_histories` |
| RNF-1 | Longitud de nombre/apellido/razón social | Validación en Actions de identificación |
| RNF-2 | Formato de DNI | Validación en `CreateClientAction` / Model |
| RNF-3 | Formato y dígito verificador de CUIT | Validación en `CreateClientAction` / Model |
| RNF-4 | Tope de 10 contactos | `AddClientContactAction` |
| RNF-5 | Listado en menos de 2s con 5.000 clientes | Índices de `clients` (`document`, `status`, `deleted_at`, `created_at`) |
| RNF-6 | Exportación en menos de 5s con 5.000 clientes | `ExportClientListAction` (streaming) |
| RNF-7 | Historial sin purga | `client_histories` sin tarea de limpieza |

## 11. Riesgos

- **La migración `add_client_id_to_users_table` modifica una tabla de la spec `001`, que todavía
  no tiene su propio plan.md**: mitigación — si el plan de `001` define un nombre o estructura de
  tabla de cuentas distinta, esta migración se ajusta antes de implementarse; no se ejecuta código
  hasta que ambos planes estén alineados.
- **Edición concurrente del mismo cliente por dos usuarios de staff** (supuesto ya documentado en
  la spec: gana el último guardado): mitigación — ninguna en esta versión; si aparecen conflictos
  reales en producción, se evalúa optimistic locking en una spec/plan posterior.
- **Búsqueda por texto (RF-52) sin índice de texto completo**: a 5.000 filas un `LIKE %term%`
  sobre columnas indexadas por igualdad sigue siendo rápido; mitigación — si el volumen crece muy
  por encima de lo previsto en RNF-5, se evalúa entonces un mecanismo dedicado (fuera de este
  plan, y del stack actual).
- **Dígito verificador de CUIT mal implementado dejaría pasar documentos inválidos**: mitigación —
  suite de tests unitarios con CUITs válidos e inválidos conocidos antes de dar por cerrada la
  regla.

## 12. Fuera de alcance del plan

- No incluye el modelo de cuentas (`users`, roles) en sí: se asume tal como lo define la spec
  `001-acceso-roles-y-paneles`, solo se le agrega la columna `client_id`.
- No incluye importación masiva de clientes preexistentes: la spec no la contempla (sección 8,
  Fuera de alcance).
- No incluye ningún mecanismo de bloqueo optimista para edición concurrente (ver Riesgos).
- No incluye las secciones de presupuestos, contratos o pagos de la ficha más allá de un punto de
  extensión: `ClientResource` deja el lugar (RF-46 a RF-48) pero el contenido real lo agregan los
  planes de esos módulos cuando existan.

## 13. Preguntas abiertas

Ninguna: todas las decisiones técnicas quedaron resueltas en la sección 3, con su alternativa
descartada.

## 14. Aprobación

- [x] Aprobado por Claudio el 2026-09-10
