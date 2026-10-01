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

### Deploy

Railway construye el backend con Docker. Antes de levantar la nueva revision ejecuta las migraciones y la siembra RBAC definida en `railway.toml`. El contenedor arranca mediante `start.sh`, que inicia PHP-FPM y Nginx sobre el puerto asignado por Railway.

El healthcheck operativo es:

```text
/ping -> 200 ok
```

No usar `php artisan serve` como servidor de produccion.

### Staging

Railway tiene un entorno `staging` con MySQL y volumen propios, red privada y correo en `MAIL_DRIVER=log`. La web esta en https://krater-staging-staging.up.railway.app. La fila P sigue pendiente: el primer deploy no ejecuto las migraciones porque los servicios nuevos ya no heredan `railway.toml`. Ver diagnostico, configuracion y propuesta de cierre en `CLAUDE.md`.

No subir commits temporales de validacion a una rama que tambien despliegue produccion. El cierre de seguridad R1 sigue siendo requisito antes de crear usuarios de prueba para personal.

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
| Backend | Laravel 8.83, PHP 8.2, MySQL |
| Frontend web | Vue 2, Vuex, TailwindCSS |
| App movil | Ionic Vue 8, Capacitor 6, Vue 3, Pinia |
| Deploy backend | Railway (Docker, Nginx + PHP-FPM) |
| Deploy PWA | GitHub Pages |

---

## Autores

- **Jesus Olguín**
- **Escuela Nueva Austral**
