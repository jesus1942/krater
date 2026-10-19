# Dominio escuelanuevaaustral.com.ar y correo institucional

Estado 19/10/2026: dominio comprado en DonWeb, registro en NIC Argentina en
proceso (24 a 48 horas). Dominios ya agregados en Railway, pendientes de DNS.

## Esquema

| Host | Destino | Uso |
| --- | --- | --- |
| `escuelanuevaaustral.com.ar` y `www` | servicio `krater` (production) | Landing publica |
| `app.escuelanuevaaustral.com.ar` | servicio `krater` (production) | Suite |
| `staging.escuelanuevaaustral.com.ar` | servicio `krater-staging` | Staging |

El middleware `SplitPublicHosts` (commit 7ca9a0e) sirve solo la landing en
los hosts de `LANDING_HOSTS` y redirige (301) el resto de las rutas web a
`APP_URL`. Sin la variable no cambia nada.

Los certificados SSL los emite y renueva Railway (Let's Encrypt) cuando los
registros DNS apuntan al servicio. No se compran.

## Registros DNS (DonWeb o el DNS que se use)

| Tipo | Nombre | Valor |
| --- | --- | --- |
| CNAME | `app` | `20sx53nt.up.railway.app` |
| TXT | `_railway-verify.app` | `railway-verify=59df5ef174e45199ce98bf36915345e82be47df185a1c28a09f460692f4efc2c` |
| CNAME | `www` | `jmqj4od3.up.railway.app` |
| TXT | `_railway-verify.www` | `railway-verify=eef8bff6e074078ba76a208692840b113f99b41b8157c7c80696a1a97afa5960` |
| CNAME | `@` (raiz) | `q2pmg9ks.up.railway.app` |
| TXT | `_railway-verify` | `railway-verify=34edfeac1aaed0b2736a31987b01919458b2abf1966c218551a3fe7927b729ea` |
| CNAME | `staging` | `12eiosaf.up.railway.app` |
| TXT | `_railway-verify.staging` | `railway-verify=9ea004ee3a8d7d22195737637607cdba2cae7b897358f09e869ddadbf62a057c` |

Si el panel no admite CNAME en la raiz: usar un DNS con CNAME flattening
(Cloudflare) o redirigir la raiz a `www` desde DonWeb.

## Correo: Google Workspace for Education Fundamentals

Decision de Jesus (19/10/2026): correo con Google. Gratis para instituciones
educativas sin fines de lucro reconocidas; Google revisa la solicitud en 1 a
2 semanas, despues de verificar el dominio.

1. Alta en el formulario de Education Fundamentals con una cuenta
   administradora que no sea @gmail.com (se crea en el alta, por ejemplo
   `admin@escuelanuevaaustral.com.ar`).
2. Verificar el dominio con el registro TXT `google-site-verification=...`
   que da Google (en `@`).
3. Correo entrante: MX `@` -> `smtp.google.com`, prioridad 1.
4. SPF: TXT `@` -> `v=spf1 include:_spf.google.com ~all` (un solo registro SPF;
   si despues se suma otro emisor se combina en el mismo).
5. DKIM: generar la clave en Admin > Gmail > Autenticar correo, publicar el TXT
   `google._domainkey` y activar la firma.
6. DMARC: TXT `_dmarc` -> `v=DMARC1; p=none; rua=mailto:admin@escuelanuevaaustral.com.ar`
   para empezar en modo observacion.

### Envio desde la Suite

Configuracion > Correo (fila C, clave cifrada en `mail_settings`), con una
casilla dedicada, por ejemplo `no-responder@escuelanuevaaustral.com.ar`:
SMTP `smtp.gmail.com`, puerto 587, TLS, usuario la casilla y contrasena de
aplicacion (requiere verificacion en dos pasos en esa cuenta). Alternativa:
relay SMTP de Workspace (`smtp-relay.gmail.com`) autorizado en la consola.
Staging sigue con `MAIL_DRIVER=log`: nunca envia correo real.

La misma cuenta escolar habilita el acceso a Drive para el archivo externo de
backups, pendiente desde la fila 6.

## Al activar el dominio

1. Cargar los registros y esperar que Railway marque los cuatro dominios como
   verificados con certificado emitido.
2. En `krater` (production): `APP_URL=https://app.escuelanuevaaustral.com.ar`,
   `LANDING_HOSTS=escuelanuevaaustral.com.ar,www.escuelanuevaaustral.com.ar`,
   y sumar el dominio a `CORS_ALLOWED_ORIGINS` si la PWA lo usa.
3. En `krater-staging`: `APP_URL=https://staging.escuelanuevaaustral.com.ar`.
4. Verificar login, PDF firmado y la landing en ambos dominios; la URL
   `krater-production.up.railway.app` sigue funcionando.
