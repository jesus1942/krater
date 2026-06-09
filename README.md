# Escuela Nueva Austral

Sistema de gestion de facturas, presupuestos, gastos y cobros para **Escuela Nueva Austral**.

Desarrollado por **Jesus Olguín** y **Escuela Nueva Austral**, basado en [Crater](https://craterapp.com) (open source).

---

## Que incluye

- Facturas con estados (borrador, enviada, pagada, vencida)
- Presupuestos con fecha de expiracion
- Registro de gastos por categoria
- Cobros y metodos de pago
- Clientes
- Productos y servicios
- Reportes de ventas, gastos y ganancias
- App movil PWA (instalable en celular)

---

## Estructura del proyecto

```
/                  → Backend Laravel 8 (API REST)
mobile/            → App PWA (Ionic + Vue 3 + Capacitor)
```

---

## App movil (PWA)

La app esta publicada en GitHub Pages y se puede instalar directamente desde el navegador del celular.

**URL:** https://jesus1942.github.io/krater/

**Credenciales iniciales:**
- Email: `admin@craterapp.com`
- Contrasena: `crater@123`

> Cambia la contrasena despues del primer ingreso.

---

## Backend (Railway)

El backend corre en Railway con base de datos MySQL.

### Variables de entorno necesarias en Railway

```
APP_KEY=
APP_URL=
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
CORS_ALLOWED_ORIGINS=https://jesus1942.github.io
FILESYSTEM_DISK=public
CACHE_DRIVER=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

### Primer deploy

Despues de configurar las variables, Railway corre automaticamente:

```bash
php artisan migrate --force
php artisan db:seed --class=UsersTableSeeder
```

---

## Desarrollo local

### Backend

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

O con Docker:

```bash
docker-compose up -d
```

### App movil

```bash
cd mobile
npm install
npm run dev
```

---

## Tecnologias

| Capa | Tecnologia |
|------|------------|
| Backend | Laravel 8, PHP 7.4, MySQL |
| Frontend web | Vue 2, Vuex, TailwindCSS |
| App movil | Ionic Vue 8, Capacitor 6, Vue 3, Pinia |
| Deploy backend | Railway (Docker + Nixpacks) |
| Deploy PWA | GitHub Pages |

---

## Autores

- **Jesus Olguín**
- **Escuela Nueva Austral**
