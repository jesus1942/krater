# Fila 6: backups y smoke de integridad

Estado al 07/10/2026: revisión de fila 6 en curso; no promover.
El smoke acepta cambios auditados, permite una aceptación manual autenticada y los
backups usan clave propia. La copia a Google Drive requiere carpeta y OAuth de la escuela.
La evidencia del 04/10 corresponde a la implementación anterior.
La fila no está cerrada: faltan el pin de los dos MySQL existentes y la
verificación/activación del backup nativo de volumen. No promover esta fila.

## Servicios de staging

| Recurso | ID | Configuración verificada |
|---|---|---|
| Bucket backups-staging | d6bde044-c856-4bfc-ad52-ed0af97bbec9 | privado, región sjc |
| backups-staging-cron | 5bedae2c-4338-4617-aeee-dd1972b252f8 | `php artisan ena:backup`, `0 6 * * *` UTC, restart NEVER |
| restore-test-staging-cron | 55750072-39f6-4dd5-a26c-478b5ea9fb86 | `php artisan ena:restore-test`, `0 7 1 * *` UTC |
| MySQL-restore-test | dbb16a4d-c3c2-4fb9-8f49-fea15b515435 | `mysql:9.7.2`, red privada, sin dominio público |

Ambos workers usan el Dockerfile del repositorio, sin predeploy de migración
y con credenciales de Railway referenciadas. No se ejecuta el cron dentro de
nginx/PHP-FPM. Las corridas iniciales se forzaron como servicios de una sola
ejecución y luego se repusieron las agendas. Config as Code está deprecado para
servicios nuevos; las opciones se configuran mediante Railway y se comprueban
con get-service-config. `dockerfilePath= Dockerfile` selecciona DOCKERFILE:
redeploy con el builder RAILPACK por defecto falla al interpretar el rango PHP
de Composer. Las corridas finales del commit 2938725 tomaron DOCKERFILE y
terminaron SUCCESS con las agendas repuestas. Staging observa cambios de
código, configuración, Docker/frontend y tests; editar solo documentación no
dispara otra corrida de fixtures. El ensayo conjunto de smoke y restauración
se ejecutó como predeploy temporal del worker. La configuración quedó luego
con preDeployCommand vacío y startCommand de restauración; los cambios de
configuración se aplican al siguiente deploy. No reutilizar el snapshot de un
redeploy anterior para asumir que tomó campos nuevos.

## Referencias, sin copiar claves

```text
BACKUP_ENDPOINT=${{backups-staging.ENDPOINT}}
BACKUP_BUCKET=${{backups-staging.BUCKET}}
BACKUP_REGION=${{backups-staging.REGION}}
BACKUP_ACCESS_KEY_ID=${{backups-staging.ACCESS_KEY_ID}}
BACKUP_SECRET_ACCESS_KEY=${{backups-staging.SECRET_ACCESS_KEY}}
BACKUP_ENCRYPTION_KEY=<clave aleatoria propia, conservada fuera de Railway>
BACKUP_ENCRYPTION_KEY_ID=backup-v1
BACKUP_PREFIX=suiteena/staging
APP_KEY=${{krater-staging.APP_KEY}}
DB_HOST=${{krater-staging.DB_HOST}}
DB_PORT=${{krater-staging.DB_PORT}}
DB_DATABASE=${{krater-staging.DB_DATABASE}}
DB_USERNAME=${{krater-staging.DB_USERNAME}}
DB_PASSWORD=${{krater-staging.DB_PASSWORD}}
RESTORE_DB_HOST=mysql-restore-test.railway.internal
RESTORE_DB_USERNAME=root
RESTORE_DB_PASSWORD=${{MySQL-restore-test.MYSQL_ROOT_PASSWORD}}
```

Las variables RESTORE se definen solo en el worker de restauración. Su MySQL
aislado referencia MYSQL_ROOT_PASSWORD de MySQL-staging. APP_ENV=staging,
APP_DEBUG=false, LOG_CHANNEL=stderr, MAIL_DRIVER=log, CACHE_DRIVER=array,
SESSION_DRIVER=array y QUEUE_CONNECTION=sync. No hay dependencia de un worker
de cola para el backup. APP_URL referencia la URL pública de krater-staging.

El archivo SQL y el manifest interno se cifran con ZIP AES-256. Los temporales
se crean en un directorio 0700, las credenciales del cliente en 0600, nunca en
argumentos de proceso ni logs; se eliminan en finally. No se incluye `.env`.
BACKUP_ENCRYPTION_KEY nunca reutiliza APP_KEY ni la toma como fallback. Guardarla
en el gestor seguro de la escuela y una copia offline **fuera de Railway**.
Rotar APP_KEY no cambia el cifrado ni la recuperación de los backups.
Producción usará otra clave y su propio bucket tras aprobación.

Antes de cambiar la APP_KEY antigua o crear nuevos backups, ejecutar una vez
`php artisan ena:backup:separar-clave`. Lee la clave antigua en memoria y conserva
`keyring/legacy.key.enc` privado, cifrado y autenticado con la nueva clave.
Descarga el sobre y verifica su descifrado antes de aprobar. No imprime claves,
no copia credenciales del bucket y las corridas siguientes no sobrescriben el
sobre. El comando requiere que la clave antigua siga siendo la usada por los
ZIP legacy. Si ya se rotó, proporcionar la antigua mediante
BACKUP_PREVIOUS_ENCRYPTION_KEYS (JSON id→clave, protegido).
La nueva clave también debe conservarse antes de configurar Railway. Para
recuperar archivos legacy, conservar el sobre junto con los ZIP y la nueva clave;
el manifest de la copia externa legacy incluye el sobre cifrado.

Cada ZIP nuevo identifica encryption_key_id. Rotar BACKUP_ENCRYPTION_KEY es una
operación distinta: preservar todas las claves históricas y volver a envolver
el keyring con la nueva clave antes de retirarla. Nunca reemplazarla sin escrow.

## Backup y retención

`ena:backup` exige S3 por HTTPS y MySQL exactamente 9.7.2. Usa los clientes
mysql/mysqldump de la misma imagen exacta, incluidos en el Dockerfile. El dump
es transaccional, incluye triggers/routines/events, preserva binarios y evita
modificar GTID del servidor de destino. Comprueba huellas antes/después del
dump; si hubo actividad que cambió datos, falla para reintentar sin podar.

Sube el ZIP privado, lo vuelve a descargar y compara SHA-256. Publica el
manifest remoto solo después de verificarlo. La retención conserva la unión
del último backup disponible de cada uno de los 7 días, 4 semanas ISO y 6 meses
más recientes. Las corridas repetidas del mismo día no ocupan siete lugares.
Solo elimina pares ZIP/manifest reconocidos dentro del prefijo del entorno;
un manifest corrupto cancela la poda. Un fallo queda registrado con exit 1.
El operador debe revisar los logs del cron y los objetos recientes del bucket.

Este backup cubre MySQL. No sustituye el almacenamiento persistente ni el
backup de adjuntos externos que no se guardan en la base.

## Restauración mensual

`ena:restore-test` exige staging + base fuente krater_staging, o un worker con
APP_ENV=restore-test y BACKUP_SOURCE_ENVIRONMENT explícito (staging/production).
El entorno production no puede ejecutarlo. El host de destino debe coincidir
con el MySQL de restauración configurado y ser distinto al origen, comprobado
también mediante @@server_uuid antes de crear la base destino. No admite una
opción para restaurar sobre la base operativa.
Descarga el último backup válido, verifica SHA-256, descifra y exige la misma
versión exacta. Crea una base nueva con nombre único en MySQL-restore-test.
Importa, compara conteos y huellas de **todas** las tablas y ejecuta
`ena:auditar-niveles --json`. La evidencia del bucket omite nombres y datos
personales. La copia se conserva para inspección mientras viva el servidor
de prueba; este servidor no tiene volumen persistente. El resultado durable
queda en `suiteena/staging/restore-tests/` y en la evidencia del repositorio.

Además, sobre esa copia genera una foto de smoke, reclasifica temporalmente
una fila dentro de una transacción, exige exit 1, hace rollback y exige
conteos idénticos + exit 0. No modifica registros operativos para esta prueba.

Corrida real aprobada: backup `20261004T132543Z-1dc1f66a04a2.zip`, restauración
del 04/10/2026 13:28 UTC, 80 tablas idénticas, MySQL 9.7.2. Auditoría: sin niveles
nulos, inexistentes ni ajenos. Evidencia:
`docs/audits/2026-10-04-backups-staging.json`.

Repetida con comprobación de UUID el 04/10/2026 13:43 UTC, deployment
5eeaa3c0-ce14-4953-8f0f-6da2770b1736, mismo archivo y 80 huellas idénticas.

Verificación final de 2938725: web eb26191a-93d4-4a36-98bf-cd089533dce4
SUCCESS, 280 verificaciones institucionales y 68 de roles. Después de ese
SUCCESS, worker 953474fc-ed2a-48bc-9949-fbc5489740af: conteos sin pérdidas,
7 comprobaciones HTTP/archivos aprobadas a las 14:08 UTC y restauración de las
80 tablas en MySQL 9.7.2, UUID distinto, auditoría y pérdida/rollback aprobados.
Backup diario 1db0b1c2-ea3e-43f2-9d6a-f326c3fda420 SUCCESS con agenda repuesta.

## Smoke antes y después del deploy

`pre-deploy.sh` corre migrate y luego `ena:smoke`, con set -eu. Cuenta alumnos,
matrículas, facturas, presupuestos, cobros y personal por empresa/nivel,
incluyendo nivel NULL, más ítems/gastos y totales institucionales. Personal se
deduplica por nivel y se conserva el total de personas aunque tengan varios
cargos. Las tablas faltantes son error. Las fotos, pérdidas y declaraciones
quedan en deploy_snapshots. Una foto fallida nunca reemplaza la última válida.
La primera corrida registra explícitamente una línea de base.

Para una migración de datos revisada, declarar en config/deploy-data-changes.php
el archivo de migración existente, el motivo y la disminución máxima de cada
clave afectada (incluidos totales). Solo vale si la migración está ejecutada y
no figuraba en la foto previa; no puede reutilizarse. No hay --force.
Las bajas explicadas por auditoría desde la foto aprobada se aceptan y se
informan como explained_losses. Se guarda el cursor de auditoría y las identidades
por empresa/nivel: eventos viejos, duplicados, de otra identidad o empresa no
justifican otra pérdida. Se siguen cadenas de level_reassigned, student_relocated,
actualizaciones académicas y bajas de modelos. Los cambios de cargos se auditan.
Las revocaciones de roles se informan, sin habilitar eliminación de datos.
Las tablas faltantes, auditoría inválida y pérdidas sin explicación siguen fallando.
Para fotos anteriores sin cursor/identidades se consideran solo eventos
posteriores a created_at, con identidad y estado final comprobables; la próxima
foto aprobada incorpora el formato completo.

Para revisar una pérdida no explicada sin otro deploy:
`php artisan ena:smoke:aceptar 42 --motivo="Baja revisada" --usuario=admin@escuela`
La contraseña se pide oculta en una consola interactiva. Exige cuenta activa y
rol total_admin global vigente; un rol revocado, expirado o de otra empresa no
autoriza. Solo acepta la última foto fallida del entorno y exige que conteos e
identidades actuales coincidan. Si cambiaron, ejecutar smoke y revisar esa foto.
No muta la foto fallida: crea otra línea de base y deploy_snapshot_accepted en
la misma transacción. Si falla la auditoría, revierte todo. Exige motivo, conserva
autor, pérdidas revisadas y fecha. No tiene --force ni contraseña por argumento.

Después de Railway SUCCESS, correr `sh post-deploy-smoke.sh` desde un worker
del mismo commit. Comprueba conteos, ping, login, APIs protegidas sin sesión y
SHA-256 de JS/CSS servidos. Para la verificación conjunta usar shell explícita
`/bin/sh -c 'sh post-deploy-smoke.sh && php artisan ena:restore-test'`. La agenda
mensual ejecuta solo restauración, así puede probar recuperación aunque la web
esté caída. Su guardia de conteos se prueba sobre la copia, con rollback.


## Copia semanal a Google Drive de la escuela

Worker separado, misma rama/imagen y referencias al bucket y clave del worker
de backup. Inicio: `php artisan ena:backup:externo`. Agenda: `30 6 * * 0` UTC
(domingo 03:30 en Argentina), después del dump diario; restart NEVER, sin
predeploy, sin dominio público. La configuración está en
`railway-backup-external.toml`; aplicar al crear el servicio.

Variables del worker: BACKUP_DRIVE_FOLDER_ID, BACKUP_DRIVE_CLIENT_ID,
BACKUP_DRIVE_CLIENT_SECRET y BACKUP_DRIVE_REFRESH_TOKEN. Cuenta de la escuela,
OAuth con acceso a esa carpeta y permiso de crear/descargar archivos; soporta
unidades compartidas. No usar credenciales del ChatGPT personal ni un token
temporal del navegador. No enviar los secretos por chat ni guardarlos en Git.

Selecciona el último manifest del prefijo del entorno; descarga el ZIP y exige
su SHA-256 antes de subir. Sube exactamente el ZIP AES-256 mediante upload
resumable, lo vuelve a descargar de Drive y compara SHA-256. Solo entonces
publica el manifest de verificación en Drive y evidencia en external-copies/ del
bucket. No descifra el SQL, no elimina el backup fuente ni copias anteriores.
Fallo de autenticación, subida, descarga, checksum o manifest: exit 1 y ninguna
evidencia aprobada. Un fallo de Drive no bloquea el deploy web ni el cron diario.
No se declara operativo hasta una copia real verificada con carpeta/OAuth.

Referencia API oficial: https://developers.google.com/workspace/drive/api/guides/manage-uploads
y https://developers.google.com/workspace/drive/api/guides/manage-downloads.

## Pin y segunda línea pendientes

Los logs del 03/10/2026 confirman 9.7.2 en ambos MySQL. Producción fue cambiada
por vuln-remediation de mysql:9.4 a mysql:9; **no intentar bajar a 9.4**.

| Entorno | Servicio | Imagen observada | Pin solicitado |
|---|---|---|---|
| staging | MySQL-staging (330c15e7-74fe-4836-9420-a1e509d79d0b) | mysql:9 | mysql:9.7.2 |
| production | MySQL (c11c33fb-f0d7-4276-802c-4b72b65d929d) | mysql:9 | mysql:9.7.2 |

Cambiar únicamente el tag de origen, preservando variables, comando de inicio,
volumen y red; verificar versión y /ping después. La restauración ya probó esa
misma versión. El conector no ofrece editar el origen de un servicio existente
ni administrar los backups nativos de volumen; el navegador no tiene sesión.
Estos pasos no se declaran aplicados. Falta verificar/activar los backups del
volumen de MySQL-staging (fd07f2a6-db8e-41a9-874b-2cf87fcd4d3c), ejecutar uno y
registrar su ID. La segunda línea productiva queda para la aprobación de fila 6.

## Corte de promoción

Producción de la aplicación permanece en f761890, cuya promoción fue aprobada
por Jesús. La fila 6 permanece en la rama de trabajo. Gate local: 84 tests / 639
aserciones. Staging: 280 HTTP institucionales y 68 de roles, más backup y
restauración real. Antes de aprobar/promover: completar los pendientes de
infraestructura anteriores y revisar el código/evidencia. Después de aprobar:
crear bucket propio de producción, conectar referencias, configurar cron
productivo y verificar primer backup antes de cerrar la fila como Operativa.
El ensayo de archivos productivos usará un worker APP_ENV=restore-test,
BACKUP_SOURCE_ENVIRONMENT=production, BACKUP_PREFIX=suiteena/production,
referencias al bucket/clave/base fuente productivos y un MySQL destino dedicado.
No se probaron ni se copiaron datos productivos en esta fase.
