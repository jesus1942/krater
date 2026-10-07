# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**ENA srl** (Escuela Nueva Austral) — app PWA de gestion de facturas y gastos, construida sobre Crater (open-source). Autores: Jesus Olguin y Escuela Nueva Austral. El nombre visible de la app es "ENA srl" (config/app.php, titulo del blade, nombre de la empresa en la DB).

El proyecto tiene dos partes:
1. **Backend** — Laravel 8 + Vue 2 SPA (el app web original, en `resources/assets/js/`). PHP namespace: `Crater\`.
2. **PWA** — Ionic + Capacitor + Vue 3, en `mobile/`. Consume la API REST del backend Laravel.

### Preferencias de trabajo
- Sin emojis ni emoticones en ningun archivo
- Mockups a mano cuando se necesiten wireframes
- Todos los commits deben escribirse en español rioplatense
- El README.md es el principal — debe estar siempre actualizado y en español
- Desarrollo y push a `claude/web-app-migration-laf9yy` en el repo `jesus1942/krater`, que despliega solo staging. Produccion sigue `produccion`; promover por merge solo despues de verificar staging (fila P2).
- El logo de la app es un placeholder; el definitivo lo pasa Jesus Olguin
- A Jesus le gusta como funciona el modulo de configuraciones del panel web; usarlo como patron de referencia al construir pantallas de ajustes (ver seccion "Modulo de configuraciones" abajo)

## Modulo de configuraciones (patron de referencia)

A Jesus le gusta esta parte de la app comparada con otras que usa. Es un buen patron y conviene replicarlo (en la PWA y en features nuevas):

**Frontend** (`resources/assets/js/views/settings/`):
- `SettingsIndex.vue` es un layout con sidebar propio: lista de ~14 categorias (perfil, empresa, preferencias, personalizacion, notificaciones, impuestos, metodos de pago, campos custom, notas, categorias de gasto, mail, discos, backups, actualizaciones)
- Cada categoria es un componente Vue independiente montado en `<router-view>` anidado bajo `/admin/settings/*` — una pantalla, una responsabilidad
- En mobile el sidebar colapsa a un `sw-select`; entrar a `/admin/settings` redirige a la primera categoria
- Cada pantalla guarda por separado (boton Guardar propio, no un submit global)

**Backend** (patron key-value en tres niveles):
- `settings` — configuracion global de la instalacion (`Setting::setSetting/getSetting`)
- `company_settings` — configuracion por empresa (`CompanySetting::setSettings($array, $companyId)`)
- `user_settings` — preferencias por usuario (idioma, etc.)
- Endpoints REST granulares por dominio (`/api/v1/company/settings`, `/api/v1/me/settings`, etc.) que reciben `{settings: {clave: valor}}` — upsert por clave, sin migraciones al agregar opciones nuevas

**Por que es recomendable:** agregar una opcion nueva no requiere migracion (key-value), cada pantalla es chica y autonoma, y el guardado granular evita perder cambios. Al portar configuraciones a la PWA de `mobile/`, seguir este mismo esquema: una vista por categoria + store Pinia que pegue a los endpoints de settings existentes.

## PWA (mobile/)

### Commands
```bash
cd mobile
npm install              # instalar dependencias
npm run dev              # dev server (vite, proxea a localhost:8000)
npm run build            # build de produccion
npm run sync             # sincronizar con Capacitor (Android/iOS)
npm run android          # abrir en Android Studio
npm run ios              # abrir en Xcode
```

### Stack
- **Ionic Vue 8** — componentes UI nativos (IonList, IonCard, IonModal, IonTabs, IonFab, etc.)
- **Capacitor 6** — empaqueta la PWA para iOS/Android
- **Vue 3 + Composition API** — todos los componentes usan `<script setup lang="ts">`
- **Pinia** — stores en `mobile/src/stores/` (auth, dashboard, invoices, estimates, expenses, customers, payments, items)
- **Vue Router 4** — rutas en `mobile/src/router/index.ts`; el guard de navegacion lee `localStorage.auth_token`
- **Axios** — cliente HTTP en `mobile/src/services/http.ts`; inyecta el Bearer token automaticamente

### Navegacion
Estructura de tabs (TabsPage.vue):
- Tab Dashboard → /tabs/dashboard
- Tab Facturas → /tabs/invoices
- Tab Gastos → /tabs/expenses
- Tab Mas → /tabs/more (acceso a clientes, presupuestos, cobros, items, reportes, configuracion)
- FAB flotante → IonActionSheet con acciones rapidas de creacion

### Identidad visual
- Color primario: `#5851D8` (morado Crater)
- Fuente: Poppins (cargada desde Google Fonts en `theme/variables.css`)
- Los colores de Ionic se sobreescriben en `mobile/src/theme/variables.css`
- Los status badges usan clases CSS `.status-{draft|sent|paid|overdue|accepted|rejected}` definidas en ese mismo archivo

### Convenciones PWA
- Todos los montos monetarios se almacenan en **centavos** (enteros) en la API. La funcion `formatMoney` divide por 100 antes de mostrar.
- Los stores Pinia solo tienen acciones async que llaman a la API; no hay logica de negocio fuera de la API.
- Los formularios de creacion y edicion comparten el mismo componente (e.g. `InvoiceCreatePage.vue`); si `route.params.id` existe, es modo edicion.
- El logo de la app va en `mobile/public/assets/icon/`. Usar `icon-192.png` e `icon-512.png` para el manifest PWA.

### Backend web (Vue 2 legacy)
```bash
npm run dev          # one-time development build
npm run watch        # rebuild on file changes
npm run hot          # hot module replacement (HMR)
npm run production   # minified production build with asset versioning
```

**Backend tests**
```bash
./vendor/bin/pest                        # full test suite
./vendor/bin/pest tests/Feature/Invoice  # single directory
./vendor/bin/pest --filter "can create"  # single test by name
```

**Code style**
```bash
./vendor/bin/php-cs-fixer fix   # fix PHP style (PSR-12 + custom rules in .php-cs-fixer.dist.php)
```

**Artisan**
```bash
php artisan key:generate   # required after fresh clone
php artisan migrate        # run pending migrations
php artisan db:seed        # seed demo data
```

## Architecture

### Backend (Laravel 8)

All API routes live under `/api/v1/` and are defined in `routes/api.php`. Controllers are namespaced under `app/Http/Controllers/V1/` and grouped by domain:

```
Auth / Onboarding / Dashboard /
Customer / Invoice / Estimate / Payment / Expense /
Item / Settings / Report / Backup / Update / Mobile
```

The `Mobile` group (`V1/Mobile/AuthController`) exposes endpoints consumed by the companion mobile apps — keep these contracts stable.

Models live in `app/Models/`. The company-scoping pattern is central: most queries are scoped to the authenticated user's `company_id`. Custom fields (`CustomField`, `CustomFieldValue`) can be attached to Invoices and Estimates — the `HasCustomFieldsTrait` handles this.

PDF generation is done server-side via `barryvdh/laravel-dompdf`. The trait `GeneratesPdfTrait` is used on Invoice, Estimate, and Payment models. Blade templates for PDFs are under `resources/views/app/pdf/`.

Authentication uses Laravel Sanctum. Institutional API routes require `active-account`, `tenant` and explicit `permission:` middleware. The legacy `admin` middleware now requires a current `role_user` assignment; the `users.role` label never grants authority. Own profile, bootstrap and fixed catalogs have an exact documented allowlist in `RouteAuthorizationMatrixTest`.

### Frontend (Vue 2 SPA)

Entry point: `resources/assets/js/app.js`  
Alias `@` maps to `resources/assets/js/` (configured in `webpack.mix.js`).

**Routing** (`router.js`) uses three layout shells:
- `LayoutBasic` — authenticated app shell with sidebar
- `LayoutLogin` — unauthenticated pages
- `LayoutWizard` — onboarding wizard

**State** (Vuex, `store/`) manages auth, notifications, and bootstrap data loaded once on login via `GET /api/v1/bootstrap`.

**API calls** go through axios, configured in `bootstrap.js` with request/response interceptors for auth tokens and error handling. There is no dedicated service layer — components call axios directly or through Vuex actions.

**Component library**: `@bytefury/spacewind` provides base UI primitives (buttons, inputs, modals, tables). Custom base components extend these under `components/base/`.

**Styling**: TailwindCSS 2 with a custom theme in `tailwind.config.js`. Component styles are extracted to `public/assets/css/app.css` by Mix.

### Testing

Tests use Pest 0.3 on top of PHPUnit 9. The `.env.testing` file sets `DB_CONNECTION=sqlite` with `:memory:` so no database setup is needed for tests. Feature tests hit the full HTTP layer; Unit tests cover models and helpers.

### Build pipeline

Laravel Mix (`webpack.mix.js`) compiles:
- `resources/assets/js/app.js` -> `public/assets/js/app.js`
- `resources/assets/sass/crater.scss` -> `public/assets/css/app.css`

In production, Mix appends a content hash to filenames and writes a `mix-manifest.json` used by the Laravel `mix()` helper in Blade.

### Storage

File uploads (expense receipts, company logos) go through Spatie Media Library. The active file disk (local, S3, or Dropbox) is configured at runtime via `Settings > File Disk` and stored in the `file_disks` table.

## Deployment

### PWA — GitHub Pages

El workflow `.github/workflows/deploy-pwa.yml` se dispara en cada push a la rama de trabajo que toque archivos de `mobile/`. Pasos:

1. `npm install && npm run build` en `mobile/` con `VITE_BASE_URL=/krater/`
2. Copia `index.html` a `404.html` (SPA routing en Pages)
3. Publica `mobile/dist/` en la rama `gh-pages` via `peaceiris/actions-gh-pages`

**URL resultante:** `https://jesus1942.github.io/krater/`

**Secrets de GitHub que hay que configurar en el repo:**
- `VITE_API_URL` — URL completa del backend Railway (ej: `https://krater-production.up.railway.app/api/v1`)
- `PAGES_CNAME` — (opcional) dominio propio si se configura uno

**Para activar GitHub Pages:** Settings > Pages > Source: `gh-pages` branch, folder `/`.

El router usa `createWebHashHistory` (URLs con `#`) para compatibilidad con Pages sin servidor.

### Backend — Railway

Archivos relevantes: `Dockerfile`, `start.sh`, `nginx.conf` y `railway.toml`.

Railway construye el backend con el `Dockerfile`. El flujo operativo es:
1. `preDeployCommand` corre migraciones, siembra RBAC y marca Crater como instalado.
2. No se define `startCommand` en `railway.toml`: el `CMD` del Dockerfile ejecuta `start.sh`.
3. `start.sh` expande `$PORT`, inicia PHP-FPM y deja Nginx en foreground.
4. Railway valida `/ping` antes de considerar sano el deploy.

No reemplazar este arranque por `php artisan serve` en produccion.

### Staging — fila P operativa (01/10/2026)

El proyecto tiene un entorno real `staging`, separado de `production`:

| Recurso | Identificador |
| --- | --- |
| Entorno staging | `5df473d4-f69a-45cb-97f8-61cdf77ed474` |
| Web `krater-staging` | `2968f192-9763-4ca9-a631-97aa39fd3b3f` |
| MySQL `MySQL-staging` | `330c15e7-74fe-4836-9420-a1e509d79d0b` |
| Volumen MySQL, 5000 MB, `/var/lib/mysql` | `fd07f2a6-db8e-41a9-874b-2cf87fcd4d3c` |

- URL: https://krater-staging-staging.up.railway.app.
- Repositorio y rama: `jesus1942/krater`, `claude/web-app-migration-laf9yy`.
- Base `krater_staging`, con credenciales y `APP_KEY` propios; no se copiaron datos de produccion.
- MySQL usa red privada, sin dominio publico ni proxy TCP.
- `DB_HOST=${{MySQL-staging.RAILWAY_PRIVATE_DOMAIN}}`, `DB_PORT=3306`, `DB_DATABASE=${{MySQL-staging.MYSQL_DATABASE}}`, `DB_USERNAME=${{MySQL-staging.MYSQL_USER}}` y `DB_PASSWORD=${{MySQL-staging.MYSQL_PASSWORD}}`. No usar referencias al servicio `MySQL` de produccion.
- `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL` con la URL anterior, `MAIL_DRIVER=log`. No usar SMTP real para pruebas.
- El arranque web conserva el `CMD` del Dockerfile; nunca usar `start.sh` como predeploy porque queda en foreground.

**Cambio de plataforma comprobado y resuelto con excepcion autorizada:** Railway rechaza `railwayConfigFile` para servicios nuevos con `INVALID_ARGUMENT`: Config as Code esta deprecado. Su documentacion indica que los servicios nuevos no pueden adoptar `railway.toml`; los servicios legacy lo conservan hasta el 01/12/2026. Fuente: https://docs.railway.com/infrastructure-as-code#migrating-from-config-as-code.

El primer deploy dio `SUCCESS` y `/ping` dio 200, pero `/login` dio 500 por ausencia de `company_settings`. Se corrigio el predeploy y el orden de los catalogos en las migraciones antiguas sin renombrarlas. El deploy `69666d8f-3f39-49cd-a37f-97637d4b3476` migro el esquema; el siguiente `6f87d8d9-f27b-4097-bd76-7e83c67d2f5b` confirmo `Nothing to migrate`, 75 permisos RBAC y `Aplicacion marcada como instalada`. El navegador abre `/login` con el formulario de ingreso. El healthcheck basico no certifica el esquema: verificar tambien el predeploy y la ruta de ingreso.

El usuario autorizo expresamente la excepcion al prompt el 01/10/2026: configurar **solo en staging** el predeploy terminante equivalente al del repositorio. Al aplicar cambios, verificar el comando efectivo y generar un deploy nuevo con la configuracion actual; no dar por hecho que un redeploy de una revision anterior incorpora el predeploy. Usar el nombre corto del seeder evita que el shell consuma las barras del namespace; Laravel lo resuelve a `Database\Seeders\RbacSeeder`:

```sh
/bin/sh -c 'php artisan migrate --force && php artisan db:seed --class=RbacSeeder --force && php artisan crater:mark-installed'
```

Pendiente de plataforma: migrar a Infrastructure as Code antes del corte legacy, con plan revisado que conserve todos los recursos y no afecte produccion. No aplicar un plan que proponga borrar servicios o volumenes.

### Ramas y promocion — fila P2 (01/10/2026)

- `production` / servicio `krater` sigue **`produccion`**, creada desde `cc4c3dc4b4c867b64fd9beaf5f098b0fd8c9812a`, el SHA productivo comprobado antes del cambio. Deploy de cambio de rama `0f8b1b2b-5b9b-4d0d-97aa-2c0c2db20059`: `SUCCESS`, mismo SHA, sin cambios de datos ni infraestructura MySQL.
- `staging` / `krater-staging` sigue **`claude/web-app-migration-laf9yy`**. Cada push de desarrollo despliega solo staging.
- Circuito: desarrollar y probar localmente -> push a rama de trabajo -> verificar SHA exacto, predeploy y smoke real en staging -> revisar diff -> merge a `produccion` -> verificar deploy y smoke de produccion. No mover `produccion` a un SHA no verificado ni volver a conectar produccion a la rama de desarrollo.
- Los commits de validacion temporal se retiran antes de promover: revisar el diff completo de `produccion...claude/web-app-migration-laf9yy`. No basta con un `/ping` 200 para aprobar una release.

**Cuenta ficticia de staging:** `php artisan ena:preparar-staging` exige simultaneamente `APP_ENV=staging` y base `krater_staging`; no tiene `--force`. Crea una institucion ficticia, niveles Primario/Secundario, preferencias y `total-admin.staging@example.invalid` con asignacion explicita `total_admin`. La clave inicial sale exclusivamente de `STAGING_ADMIN_PASSWORD` (24 caracteres como minimo), variable del servicio web de staging: no se imprime ni se guarda en el repo. Repetir el comando conserva clave y asignaciones, incluidas revocaciones. No usar `db:seed` general ni copiar datos productivos para crear estas pruebas.

En staging agregar `&& php artisan ena:preparar-staging` al predeploy terminante autorizado, despues de las migraciones, `RbacSeeder` y `crater:mark-installed`. **Nunca agregarlo al predeploy productivo.** El doble corte rechaza su ejecucion alli aun si se lo invoca por error. Validacion local: `PrepareStagingTest` prueba produccion, base incorrecta, clave ausente, asignacion explicita, repeticion y colision de email; no requiere datos de la escuela. Siembra e ingreso reales verificados en deployment staging `9f352b8e-fa10-4583-a8e7-8ed82dfdbbf8`, SHA `8772379`, `SUCCESS`: login, bootstrap y selector de niveles 200. El push no genero deploy productivo. Evidencia en `docs/SUITEENA_BITACORA.md`.

No crear usuarios de prueba para personal en produccion hasta cerrar R1.

### Cierre del bloque heredado — fila R1

Estado: operativo en staging y produccion, SHA final `01951af`, 01/10/2026. La migracion `2026_10_01_180000_harden_user_access` convierte solo al superusuario legacy en una asignacion explicita `total_admin`, conserva revocaciones y registra conteos sin datos personales. Si produccion tenia un superusuario y no queda un total admin vigente, bloquea el predeploy. `RbacSeeder` deja de conceder roles segun etiquetas legacy.

Usuarios exige permisos de lectura/gestion, alcance y jerarquia de `canManageUser`; nadie se edita a si mismo por `/users` (usar `/me`). Las altas son `staff` sin asignaciones; las bajas desactivan y revocan tokens. R2 sigue pendiente: no hay aun UI para asignar roles.

Clientes y busqueda toman empresa de `TenantContext` y filtran responsables legacy y canonicos por nivel. Finanzas requiere permisos para leer y escribir. El borrado fisico de clientes queda solo para total admin hasta la baja logica de la fila 5, porque puede afectar hermanos de otros niveles.

Los enlaces PDF/recibos se firman con vencimiento: un dia en API/UI, siete dias en correo. Los enlaces viejos sin firma dejan de funcionar. Los PDF nuevos se guardan en `finance_private`, fuera de `public`; nginx bloquea las carpetas locales legacy y PDF bajo `/storage` y `/media`. Copias historicas en buckets publicos externos requieren revision del administrador del bucket; la app no cambia ACL de archivos antiguos.

Para la matriz real usar solo staging: `ena:preparar-staging --matriz` prepara preceptor/administrativo de Primario, dos familias y alumnos ficticios. Mantiene el doble corte entorno/base y no cambia claves, bajas o revocaciones existentes. Nunca ejecutar en produccion ni agregar al predeploy productivo.

Staging usa `LOG_CHANNEL=stderr` para observar excepciones y `QUEUE_CONNECTION=sync`: no tiene tabla `jobs` ni worker, por lo que la configuracion `database` provocaba un 500 despues de guardar la factura. No se cambio ninguna variable productiva. El conector no permite leer el valor de la cola productiva; su revision queda registrada para la fila 6.

Validacion R1: 35 tests/258 aserciones, 39 verificaciones HTTP con sesiones reales y recheck sobre el SHA final `a40f866`. Deployment staging `e73c5e4e-d30f-43d5-8d6d-944e10a1df97` en SUCCESS; creacion de factura ficticia 200, PDF real 200, sin firma/firma alterada/vencimiento alterado 403 y archivos publicados iguales al build.

Despliegue inicial de R1 en produccion: `f300eb5c-19df-4c35-9aab-d1f97bbccaf2`, SUCCESS, rama `produccion`, mismo SHA probado. Predeploy: usuarios 2 antes/2 despues, 1 superusuario legacy y 1 total admin vigente. Smoke real de 10 endpoints/archivos aprobado; las sesiones con roles limitados se probaron exclusivamente en staging. No se crearon cuentas productivas de prueba. R2/R2b se cerraron posteriormente; ver la promocion del 02/10/2026. Filas 5 a 8 pendientes.

Revision final del cache: los PDF privados se guardan en directorios independientes por ID de media; los caminos legacy no cambian. Gate final 36 tests/261 aserciones. SHA `01951af` verificado en staging `276a95ca-8f2e-4a66-a713-5750defd0c13` (cache PDF activado, alta y descarga real aprobadas) y produccion `5739491b-adea-4805-ac8e-0f7e639358ff`, ambos SUCCESS. Smoke productivo repetido sobre la version final. Ver cierre detallado en bitacora.

**Variables de entorno que configurar en Railway:**
```
APP_KEY=           (generar con: php artisan key:generate --show)
APP_URL=           (URL que asigne Railway)
DB_HOST=           (de la variable $MYSQLHOST que Railway inyecta)
DB_PORT=           ($MYSQLPORT)
DB_DATABASE=       ($MYSQLDATABASE)
DB_USERNAME=       ($MYSQLUSER)
DB_PASSWORD=       ($MYSQLPASSWORD)
CORS_ALLOWED_ORIGINS=https://jesus1942.github.io
FILESYSTEM_DISK=public
CACHE_DRIVER=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

**Base de datos:** agregar el plugin MySQL desde el dashboard de Railway. Las variables `$MYSQL*` se inyectan automaticamente en el servicio.

**CORS:** la variable `CORS_ALLOWED_ORIGINS` acepta origenes separados por coma. Siempre incluir el dominio de Pages. `config/cors.php` lee esta variable.

### R2 — usuarios, roles y alcances (revision en staging)

La ficha `/admin/users/{id}/access` separa asignaciones e historial de permisos
 efectivos. Los endpoints `/roles`, `/users/{user}/role-assignments`,
`/role-assignments/{id}/revoke` y `/users/{user}/effective-permissions` usan tenant,
permisos y `AccessManager`. Bootstrap y `/me` entregan permisos por nivel;
Vue ya no deriva autoridad de `users.role`.

Cada alta es una nueva fila de `role_user`, con division o seccion propia y
`managed_scope=true`; `revoked_at` retira autoridad de inmediato. Las fechas
`starts_at`/`ends_at` de la API son dias inclusivos y se guardan en
`starts_on`/`ends_on`. Los alcances antiguos de `user_scopes` siguen operativos
solo a traves de sus roles vigentes, sin volver a conceder roles revocados.

Los cambios se serializan por institucion; no se permite autoasignacion ni
administrar jerarquias iguales/superiores salvo otro total admin. Al revocar o
dar de baja un total admin tiene que quedar otro activo sin vencimiento. Las
altas nuevas de total admin son inmediatas y sin fin programado. La auditoria
semantica del otorgamiento/revocacion es atomica: si falla, no cambia el acceso.

Gate reproducible con PHPUnit 9: `php phpunit.phar -c phpunit-rbac.xml` (o un
runner PHPUnit 9 compatible instalado). No declara aprobada la suite Pest/JMac
legacy. El archivo de bootstrap independiente evita esa incompatibilidad.
La migracion agrega historial sin borrar datos y su `down` se bloquea: recuperar
la unicidad antigua exigiria descartar historia, por lo que requiere una
migracion de reversion revisada.

R2/R2b promovidas con autorizacion de Jesus el 02/10/2026, SHA `1289f3c`.
Metodo de verificacion: componentes Vue reales en JSDOM y HTTP autenticado
en staging/MySQL; no se declara una recorrida visual manual con sesion.


### R2b — experiencia por rol y registro delegado

`preceptor_registrar` es adicional al preceptor vigente, de alcance division.
`students.register` permite alta con matricula inicial atomica y edicion de
identidad basica; no permite baja, traslado, familiares ni notas sensibles.
Solo direccion del nivel/general o total admin lo administran via R2. Alcance,
cupo y autoridad se revalidan bajo bloqueo; auditoria semantica obligatoria.

Vue elige inicio por permisos y evita montar rutas prohibidas; buscador,
selectores y botones se ocultan segun bootstrap. `placement-options` se pide
al abrir el alta, nunca al leer Alumnos ni al editar datos basicos delegados.
Preceptor/docente pueden leer ciclos y estructura, conservando el scope.

Pruebas reproducibles: `php phpunit.phar -c phpunit-rbac.xml` y
`node tests/smoke/role-ui.cjs` (PHP en PATH, o variable `PHP` apuntando al binario).
JSDOM monta los componentes Vue y falla ante llamadas prohibidas; no reemplaza
la revision visual con sesion real. El ensayo HTTP de staging se ejecuta con
`php tests/smoke/staging-role-experience.php`, solo en `staging/krater_staging`.
Usa loopback temporal con parada garantizada y datos ficticios; no imprime
claves/tokens ni habilita endpoints de depuracion. Este ensayo es exclusivo de staging.


Predeploy exclusivo de staging para R2b (terminante, despues de migrar/sembrar):
```sh
/bin/sh -c 'sh pre-deploy.sh && php artisan ena:preparar-staging --matriz && php tests/smoke/staging-role-experience.php'
```
Deployment `aaf5c0c2-5044-4b48-bc37-c9a76599dd42`, codigo `cc9edaa`,
SUCCESS; 63 comprobaciones HTTP en PHP 8.2/MySQL aprobadas. Evidencia sin
secretos: `docs/audits/2026-10-02-r2b-smoke.json`. La revision visual autenticada
no se realizo en esa revision. Estado posterior: ver cierre productivo del 02/10/2026.


### Etiquetas y autorizacion de promocion (02/10/2026)

Jesus autoriza promover R2/R2b despues de corregir las etiquetas y verificar
staging. Esta instruccion reemplaza el corte previo. `helpers/navigation.js`
resuelve las etiquetas españolas del menu y Configuracion/Mi perfil segun
`system.settings.manage`, tanto en sidebar como en encabezado y layout.
La app arranca en español: `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=es`,
`config/app.php` y VueI18n usan es como predeterminado y respaldo. Bootstrap y
Mi perfil resuelven es sin escribir preferencias cuando falta el idioma o es
vacio/no soportado; una preferencia explicita soportada se conserva. Las altas
nuevas usan el idioma de la app, sin heredar el ingles legacy de la empresa.
El gate DOM usa la instancia y mutacion de VueI18n reales: nueve recorridos
en/es/sin idioma, roles limitados y total admin, y cobertura española de todas
las claves del catalogo ingles. El smoke de staging retira/restaura solo el
idioma de una cuenta ficticia y verifica bootstrap, Mi perfil y validacion
española por HTTP. Nunca se ejecuta este ensayo en produccion.


### Predeploy terminante del repositorio (02/10/2026)

`railway.toml` invoca `sh pre-deploy.sh`. El script usa `set -eu` y pasos
separados para migraciones, RbacSeeder y mark-installed, con marcadores
comprobables en logs. El comando legacy con `&&` solo acreditaba migrate en
produccion y prevalecia sobre el ajuste del servicio. No volver a una cadena
sin shell. El script compartido no contiene fixtures ni pruebas; staging
lo invoca antes de su preparacion y sus 68 comprobaciones HTTP exclusivas.


### R2/R2b operativas — cierre productivo (02/10/2026)

- Rama `produccion`, SHA `1289f3cf0335d90df120d0a1b36bb72c735e3497`.
- Staging `bc6f07ce-995c-4441-9dc1-7f17537f2cf2`, SUCCESS: script completo,
  76 permisos/14 roles y 68 HTTP con MySQL; cuenta sin idioma y con idioma
  vacio resuelve es en bootstrap y Mi perfil, con validacion española.
- Produccion `fd03f25b-e03c-447e-b51d-d66841389054`, SUCCESS: `Nothing to migrate`,
  siembra de 76 permisos/14 roles y marcador `SuiteEna predeploy: completo`.
  La migracion de historial ya se aplico en `7864247f-6c9c-4b48-909d-24b32257d1f1`.
- Gate local: 64 tests/493 aserciones, nueve recorridos DOM por tres roles
  en/es/sin idioma, login predeterminado español y cobertura de catalogo es.
- `APP_LOCALE=es` y `APP_FALLBACK_LOCALE=es` explicitos en ambos servicios.
- Evidencia y smoke publico: `docs/audits/2026-10-02-r2-r2b-production.json`.
  Las filas siguientes no se adelantan por esta promocion.


### Toda la institución — validación antes de la fila 6

El nivel del encabezado sigue siendo el contexto de lectura. `ResolvesSchoolLevel`
valida el nivel de escritura desde el binding en edición y desde el formulario en
alta global. No cambiar TenantContext para guardar ni aceptar reclasificación por
PUT. Alumnos y facturación cargan opciones del nivel del registro; informes admiten
consolidado exclusivamente para total admin, con filtro opcional por nivel.

Gate: `php phpunit.phar -c phpunit-rbac.xml`, `node tests/smoke/institution-ui.cjs`
y `node tests/smoke/role-ui.cjs`. El recorrido nuevo utiliza seis entidades y tres
niveles, más los cinco informes PDF. Smoke de MySQL:
`php tests/smoke/staging-institution.php`, exclusivamente en staging/krater_staging.
Ejecutarlo después del smoke de roles, en el predeploy terminante autorizado de
staging. No añadirlo a `pre-deploy.sh`, railway.toml ni al servicio productivo.
Estado: probado en staging, código `152a40f`, deployment `013cfefa-06be-417c-9126-c1e388feb74c` SUCCESS. Gate local 70 tests/578 aserciones; MySQL 280 comprobaciones institucionales y 68 de roles aprobadas. Evidencia: `docs/audits/2026-10-03-institution-staging.json`. Producción permanece en `1289f3c`.
Jesús exige detener antes de promover a `produccion`.

El smoke institucional conserva evidencia en bloques de 40 solicitudes. El servidor
temporal drena sus logs y registra stacks en un archivo efímero; imprimir solo clases
y ubicaciones. Las cookies Secure se devuelven cifradas en memoria por loopback y el
cupo de ingreso se separa con una IP ficticia por ejecución. Nunca desactivar CSRF
ni autorización. Los jobs de cobro esperan el hash persistido antes de generar PDF.


### Toda la institución promovida y fila 6 en staging (04/10/2026)

Jesús aprobó la revisión de Claude de 152a40f/f761890 y autorizó promover.
Producción de la app quedó en f7618902abc4d86d752b37514df7145520ad5583,
Railway cbfe545a-4dd0-418e-ad83-1407a44dac40 SUCCESS; predeploy y smoke público
aprobados. Evidencia: docs/audits/2026-10-03-institution-production.json.
El corte anterior de promoción queda reemplazado solo para esa funcionalidad.

Fila 6: ena:backup, ena:restore-test, ena:smoke y post-deploy-smoke.sh. Backup
cifrado AES-256 a bucket backups-staging con referencias (sin copiar claves),
retención 7 diarios/4 semanales/6 mensuales. Dos workers separados, Dockerfile
explícito (dockerfilePath selecciona builder DOCKERFILE), sin predeploy,
restart NEVER; cron 0 6 * * * y 0 7 1 * * UTC. Los servicios nuevos no aceptan
railwayConfigFile: configurar opciones con el conector/UI, no nuevos toml.

MySQL-restore-test usa mysql:9.7.2, red privada y base nueva por corrida.
Backup real de 80 tablas descargado/restaurado el 04/10/2026; huellas idénticas,
auditoría sin niveles nulos/ajenos/inexistentes; smoke detectó pérdida de nivel
simulada en transacción con exit 1, rollback conservó conteos y volvió a exit 0.
La restauración se limita a staging/krater_staging y host distinto al operativo.
APP_KEY debe conservarse mientras existan archivos cifrados con esa clave.

Gate local 84 tests/639 aserciones. Staging 280 HTTP institucionales + 68 roles,
smoke de conteos y HTTP aprobados. Ver docs/operations/BACKUPS_Y_SMOKE.md y
docs/audits/2026-10-04-backups-staging.json. Pendientes: pin existente de
MySQL-staging/MySQL a mysql:9.7.2 y verificar/activar backup nativo del volumen
staging. Ambas bases ya ejecutan 9.7.2 (producción fue actualizada por Railway
vuln-remediation); no degradar a 9.4. El conector no expone cambios de imagen
de servicio existente ni backups nativos; no hay sesión web autenticada.
La fila 6 NO está promovida ni Operativa; completar pendientes y parar antes
de promover. Jesús creará bucket de producción después de aprobar fila 6.

El worker mensual ejecuta solo ena:restore-test; el smoke HTTP postdeploy se
ejecuta por separado, con shell explícita cuando se verifica junto al restore.
BACKUP_SOURCE_ENVIRONMENT distingue el entorno del archivo y el del worker;
para archivos productivos futuros usar APP_ENV=restore-test, source=production
y un MySQL destino dedicado. APP_ENV=production siempre rechaza restaurar,
staging solo admite su propia fuente krater_staging. El destino requiere host
de restauración designado y UUID distinto. No se ensayaron archivos productivos.

Código final probado: 2938725, web staging eb26191a SUCCESS. Smoke después
de SUCCESS y restore final: 953474fc SUCCESS, 04/10/2026 14:08 UTC, 7 checks
HTTP/assets, 80 tablas idénticas, UUID distinto y pérdida/rollback aprobados.
Se retiró el predeploy temporal en configuración del worker (próximo deploy);
la agenda mensual conserva start ena:restore-test. Los pins existentes y el
backup nativo siguen pendientes por falta de sesión autenticada en Railway.
