# Fila 6: backups y smoke de integridad

Estado al 04/10/2026: código probado y restauración real aprobada en staging.
La fila no está cerrada: faltan el pin de los dos MySQL existentes y la
verificación/activación del backup nativo de volumen. No promover esta fila.

## Servicios de staging

| Recurso | ID | Configuración verificada |
|---|---|---|
| Bucket backups-staging | d6bde044-c856-4bfc-ad52-ed0af97bbec9 | privado, región sjc |
| backups-staging-cron | 5bedae2c-4338-4617-aeee-dd1972b252f8 | `php artisan ena:backup`, `0 6 * * *` UTC, restart NEVER |
| restore-test-staging-cron | 55750072-39f6-4dd5-a26c-478b5ea9fb86 | shell terminante para smoke HTTP + restauración, `0 7 1 * *` UTC |
| MySQL-restore-test | dbb16a4d-c3c2-4fb9-8f49-fea15b515435 | `mysql:9.7.2`, red privada, sin dominio público |

Ambos workers usan el Dockerfile del repositorio, sin predeploy de migración
y con credenciales de Railway referenciadas. No se ejecuta el cron dentro de
nginx/PHP-FPM. Las corridas iniciales se forzaron como servicios de una sola
ejecución y luego se repusieron las agendas. Config as Code está deprecado para
servicios nuevos; las opciones se configuran mediante Railway y se comprueban
con get-service-config. `dockerfilePath= Dockerfile` selecciona DOCKERFILE:
redeploy con el builder RAILPACK por defecto falla al interpretar el rango PHP
de Composer. Ese fallo se corrigió antes del cierre de la verificación.

## Referencias, sin copiar claves

```text
BACKUP_ENDPOINT=${{backups-staging.ENDPOINT}}
BACKUP_BUCKET=${{backups-staging.BUCKET}}
BACKUP_REGION=${{backups-staging.REGION}}
BACKUP_ACCESS_KEY_ID=${{backups-staging.ACCESS_KEY_ID}}
BACKUP_SECRET_ACCESS_KEY=${{backups-staging.SECRET_ACCESS_KEY}}
BACKUP_ENCRYPTION_KEY=${{krater-staging.APP_KEY}}
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
La referencia a APP_KEY mantiene la clave en Railway: **no rotar/eliminar esa
clave mientras se necesiten los archivos cifrados con ella**. La recuperación
requiere conservar ese valor en el gestor seguro del operador. Producción
deberá usar su propia clave y el bucket que Jesús creará tras la aprobación.

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

`ena:restore-test` solo admite staging + base fuente krater_staging + un host
MySQL distinto. No admite una opción para restaurar sobre la base operativa.
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
Una caída por reubicación/deletes entre deploys también requiere revisión:
el comando aplica la regla estricta solicitada, no presume que sea legítima.

Después de Railway SUCCESS, correr `sh post-deploy-smoke.sh` desde un worker
del mismo commit. Comprueba conteos, ping, login, APIs protegidas sin sesión y
SHA-256 de JS/CSS servidos. El worker mensual usa shell explícita para que
smoke y restauración se ejecuten y cualquier fallo corte la secuencia.

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
por Jesús. La fila 6 permanece en la rama de trabajo. Gate local: 82 tests / 635
aserciones. Staging: 280 HTTP institucionales y 68 de roles, más backup y
restauración real. Antes de aprobar/promover: completar los pendientes de
infraestructura anteriores y revisar el código/evidencia. Después de aprobar:
crear bucket propio de producción, conectar referencias, configurar cron
productivo y verificar primer backup antes de cerrar la fila como Operativa.
