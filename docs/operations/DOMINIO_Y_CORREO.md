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

## Correo: opcion gratuita (decision de Jesus, 19/10/2026)

Google Workspace Business cuesta unos US$10,92 por usuario (40 empleados) y la
escuela es una SRL, asi que no corresponde Education Fundamentals. Se usa:

1. **Cloudflare (gratis) como DNS** del dominio: resuelve el CNAME en la raiz
   (CNAME flattening) para Railway y ofrece Email Routing.
2. **Cloudflare Email Routing (gratis)**: una direccion por persona
   (`nombre.apellido@escuelanuevaaustral.com.ar`) que reenvia al correo que ya
   usa. Sin limite de direcciones. La Suite usa el correo como usuario.
3. **Zoho Mail Forever Free (hasta 5 casillas)** para cuentas institucionales
   que leen y escriben: direccion, secretaria, administracion, etc. Si se usan,
   van en un subdominio (por ejemplo `mail.escuelanuevaaustral.com.ar`) porque
   sus MX no conviven con los de Email Routing en el mismo nombre.
4. **Brevo (gratis, 300 correos/dia)** para la Suite: SMTP desde
   `no-responder@escuelanuevaaustral.com.ar`, cargado en Configuracion > Correo
   (clave cifrada en `mail_settings`). Staging sigue con `MAIL_DRIVER=log`.

Pasos:

1. Crear la cuenta de Cloudflare y agregar el sitio; anotar los dos servidores
   de nombres que asigna.
2. Con el dominio activo en NIC, delegar a esos servidores de nombres (DonWeb >
   DNS/Delegacion, o NIC Argentina > Delegaciones).
3. Cargar en Cloudflare los registros de Railway de la tabla de arriba (los
   CNAME en modo "solo DNS", sin proxy, para que Railway emita el certificado).
4. Activar Email Routing (agrega sus MX y SPF) y crear las reglas por persona.
5. Brevo: dar de alta el dominio, publicar su DKIM y sumar `include:spf.brevo.com`
   al unico registro SPF (por ejemplo
   `v=spf1 include:_spf.mx.cloudflare.net include:spf.brevo.com ~all`).
   DMARC: `_dmarc` -> `v=DMARC1; p=none; rua=mailto:<casilla de direccion>`.
6. Configurar el SMTP de Brevo en la Suite y probar el envio de prueba.

Responder desde la direccion institucional con solo reenvio exige configurar
"enviar como" en el correo personal con el SMTP de Brevo; por eso las cuentas
que mas escriben conviene que sean casillas Zoho.

Verificar planes y limites en las paginas oficiales de Cloudflare, Zoho y
Brevo antes del alta: los datos de arriba salen de fuentes de terceros.

## Al activar el dominio

1. Cargar los registros y esperar que Railway marque los cuatro dominios como
   verificados con certificado emitido.
2. En `krater` (production): `APP_URL=https://app.escuelanuevaaustral.com.ar`,
   `LANDING_HOSTS=escuelanuevaaustral.com.ar,www.escuelanuevaaustral.com.ar`,
   y sumar el dominio a `CORS_ALLOWED_ORIGINS` si la PWA lo usa.
3. En `krater-staging`: `APP_URL=https://staging.escuelanuevaaustral.com.ar`.
4. Verificar login, PDF firmado y la landing en ambos dominios; la URL
   `krater-production.up.railway.app` sigue funcionando.
