# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Escuela Nueva Austral** — app PWA de gestion de facturas y gastos, construida sobre Crater (open-source). Autores: Jesus Olguin y Escuela Nueva Austral.

El proyecto tiene dos partes:
1. **Backend** — Laravel 8 + Vue 2 SPA (el app web original, en `resources/assets/js/`). PHP namespace: `Crater\`.
2. **PWA** — Ionic + Capacitor + Vue 3, en `mobile/`. Consume la API REST del backend Laravel.

### Preferencias de trabajo
- Sin emojis ni emoticones en ningun archivo
- Mockups a mano cuando se necesiten wireframes
- Todos los commits deben escribirse en español rioplatense
- El README.md es el principal — debe estar siempre actualizado y en español
- Push siempre a la rama `claude/web-app-migration-laf9yy` en el repo `jesus1942/krater`
- El logo de la app es un placeholder; el definitivo lo pasa Jesus Olguin

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

Authentication uses Laravel Sanctum (token-based). There are two middleware groups to be aware of: `auth:sanctum` for regular users and `abilities:super-admin` for admin-only routes.

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

Archivos relevantes: `railway.toml`, `nixpacks.toml`.

Railway detecta PHP automaticamente con Nixpacks. El comando de start:
1. Corre `php artisan migrate --force`
2. Cachea config, rutas y vistas
3. Levanta el servidor en `$PORT`

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
