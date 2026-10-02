# SuiteEna — Bitácora técnica

## 2026-10-01 — Auditoría inicial de niveles

- Comando: `php artisan ena:auditar-niveles --json`.
- Primera ejecución real en producción: `2026-10-01T02:57:10Z`.
- Empresa: Escuela Nueva Austral (#1).
- El test de aislamiento confirmó que el comando no ejecuta queries de escritura.
- No se detectaron niveles inexistentes ni niveles pertenecientes a otra empresa.
- Hallazgo: el único presupuesto existente, `PRE-000001` (id 1), fechado `2026-08-16`, tiene `school_level_id = NULL`.
- Resultado de inferencia: `sin_evidencia`; no existe una fuente única que permita asignarle un nivel automáticamente.
- Conclusión: el presupuesto del 16/08 no fue borrado. Queda oculto cuando se selecciona un nivel porque el scope exige igualdad estricta de `school_level_id`.
- Acción sobre datos: ninguna.
- Evidencia: `docs/audits/2026-10-01-level-audit.json`.
- Limitación detectada: `payments` conserva `invoice_id`, pero `invoices` no conserva `estimate_id`, por lo que la cadena histórica Estimate → Invoice no siempre puede reconstruirse.

## 2026-10-01 — Auditoría económica, alumnos y RR.HH.

- Deploy operativo: `126691df0d9b47d65b24d10ef1b2fc24a2bf4c82` — Railway `SUCCESS`.
- Se agregó `Auditable` a Invoice, Estimate, Payment, Expense, Student, StaffMember, PayrollSlip y PayrollPayment.
- El auditor conserva qué campo cambió, pero redacta valores sensibles como DNI, fecha de nacimiento, documento laboral, nombre, apellido, email y teléfono.
- La reubicación de alumnos ahora exige un motivo en UI y API.
- Cada reubicación registra el evento semántico `student_relocated` con IDs de origen/destino, motivo, actor y severidad alta.
- Se recompiló Vue y se regeneró `build/frontend` antes de producción.
- El gate específico pasó con PHPUnit puro. El stack Pest heredado mantiene una incompatibilidad conocida de firma en `jasonmccreary/laravel-test-assertions`; no se ocultó ni se modificó esa deuda dentro de esta feature.

## 2026-10-01 — Reconciliación segura de registros sin nivel

- Feature desplegada: `5bd1fa363b2e97cc96e89af2f87a76234a60466f` — Railway `SUCCESS`.
- Nuevo permiso `data.reconcile`, restringido por corte duro a total-admin y auditado siempre.
- Nuevo comando `ena:reubicar-nivel {modelo} {ids*} --nivel= {--aplicar}`.
- Sin `--aplicar`, el comando solo simula y las pruebas verifican cero queries de escritura.
- La aplicación real usa transacción, `lockForUpdate`, valida empresa/nivel y rechaza registros ya correctamente clasificados.
- Los vínculos canónicos impiden reconciliaciones contradictorias; por ejemplo, una factura vinculada a un alumno de Primario no puede enviarse a Secundario.
- El evento `level_reassigned` se inserta dentro de la misma transacción. Una falla de auditoría revierte también la reasignación.
- Nueva pantalla `Configuración > Registros sin nivel`, visible únicamente para total-admin en `Toda la institución`.
- La pantalla permite selección múltiple por tipo, vista previa obligatoria, nivel destino y motivo antes de aplicar.
- El presupuesto histórico `PRE-000001` aparece en esta herramienta, pero no fue reasignado: sigue requiriendo decisión humana sobre su nivel.

## 2026-10-01 — Fila P: staging aislado, cierre pendiente

- El usuario creó el entorno real `staging` (`5df473d4-f69a-45cb-97f8-61cdf77ed474`). Los servicios de prueba que antes estaban en producción ya fueron retirados.
- Web `krater-staging`: `2968f192-9763-4ca9-a631-97aa39fd3b3f`, misma rama y código base `ea3619d`, dominio `krater-staging-staging.up.railway.app`.
- MySQL propio: `330c15e7-74fe-4836-9420-a1e509d79d0b`, base `krater_staging`; volumen `fd07f2a6-db8e-41a9-874b-2cf87fcd4d3c`, 5000 MB, `/var/lib/mysql`. Sólo red privada.
- Se corrigió el arranque inicial de MySQL para ejecutar `docker-entrypoint.sh` y se comprobó el montaje real del volumen. Deploy MySQL `743703ee-40a5-48df-89d0-715f341cbf09`: `SUCCESS`.
- Se configuraron referencias `DB_*` únicamente a `MySQL-staging`, clave de aplicación propia y `MAIL_DRIVER=log`. No se copiaron datos ni credenciales de producción.
- Primer deploy web `66110a12-b0f8-433b-906c-80abe5147b97`: `SUCCESS`; comprobación HTTP real: `/ping` 200 `ok`, `/login` 500. Logs: tabla `krater_staging.company_settings` inexistente. Esto demuestra que el healthcheck no alcanza para verificar migraciones.
- Railway rechazó habilitar `railwayConfigFile=railway.toml` con `INVALID_ARGUMENT`. La documentación de Infrastructure as Code confirma que los servicios nuevos no pueden adoptar Config as Code y establece corte para servicios legacy el 01/12/2026.
- La integración Railway agregó un predeploy de servicio pese a la restricción explícita del prompt. Se retiró inmediatamente con `preDeployCommand=[]`; no se acepta como solución ni se declara que las migraciones estén verificadas.
- Queda para revisión la excepción concreta documentada en `CLAUDE.md`: predeploy terminante sólo en staging, equivalente al comando del repositorio, o migración controlada a Infrastructure as Code. No usar `start.sh` como predeploy.
- Producción conserva sus servicios originales y despliegues `SUCCESS`; comprobación HTTP real: `/ping` y `/login` 200. No se modificaron datos de la escuela ni `PRE-000001`.
- R1 y las filas posteriores siguen pendientes. No se crearon usuarios de prueba para personal. No hubo cambios Vue ni regeneración necesaria de `build/frontend`.
- La rama compartida también despliega producción: las validaciones temporales deben usar un despliegue exclusivamente dirigido a staging o una rama temporal exclusiva, no commits en la rama compartida.

## 2026-10-01 — Fila P operativa: predeploy y esquema nuevo verificados

- El usuario autorizó expresamente configurar el predeploy del servicio sólo en staging, como excepción a la receta inicial por la deprecación de `railway.toml` para servicios nuevos.
- Comando efectivo: `/bin/sh -c 'php artisan migrate --force && php artisan db:seed --class=RbacSeeder --force && php artisan crater:mark-installed'`. El shell explícito ejecuta toda la secuencia y detiene el deploy ante un error. No ejecuta servidores ni `start.sh`.
- La ejecución real detectó una migración legacy fuera de orden: `2014_10_12_000010_seed_base_data` consultaba `payment_methods`, que se crea en 2019. La carga temprana de países tenía el mismo problema.
- Corrección permanente `15a8e4ae80c5f5762e215ef83631f1eaa4a44a4a`: guardar nombres históricos de las migraciones, omitir tablas aún inexistentes, no insertar métodos/unidades para una empresa #1 inexistente y cargar países después de crear su tabla. No se agregaron migraciones que vuelvan a sembrar catálogos existentes en producción.
- Pruebas: `FreshInstallationCatalogTest`, PHPUnit 9.6.37 sobre PHP 8.3 local, 3 tests y 12 assertions. Cubren la carga inicial, repetición sin sobrescribir valores personalizados y ausencia de referencias a una empresa inexistente. Sintaxis PHP y `git diff --check` correctos. Railway mantiene PHP 8.2 y MySQL para la prueba real.
- Deploy staging `69666d8f-3f39-49cd-a37f-97637d4b3476`: esquema completo migrado y `SUCCESS`.
- Deploy posterior `6f87d8d9-f27b-4097-bd76-7e83c67d2f5b`: predeploy observado en logs: `Nothing to migrate`, `Permisos sembrados: 75`, `Database seeding completed successfully`, `Aplicacion marcada como instalada`.
- Navegador real: `/login` muestra campos Email, Password y botón Login, sin redirección a `/on-boarding`. Antes de completar el predeploy se comprobó que un 200 podía corresponder al instalador; se verificó la URL final para evitar ese falso positivo.
- El ensayo negativo de autenticación con email ficticio recibió 422 por credenciales incorrectas, sin token. No se crearon usuarios de prueba para personal ni se copiaron datos de la escuela.
- Producción conserva MySQL y volumen originales; deploy web de la corrección permanente `SUCCESS`. La excepción de configuración de predeploy se aplicó sólo a staging.
- Documentación actualizada en `CLAUDE.md`, `README.md` y plan maestro. No se tocó Vue; no corresponde regenerar `build/frontend`.
- Fila P cerrada para revisión de Claude. R1 y las filas posteriores siguen pendientes. El comando `ena:smoke`, backups y prueba de restauración corresponden a la fila 6 y no se declaran implementados.

## 2026-10-01 — Fila P2: separacion de ramas y cuenta ficticia

- La revision adjunta del usuario aprobo P con condicion P2 antes de R1: separar despliegues para que staging proteja produccion.
- Se creo `produccion` en el SHA productivo comprobado `cc4c3dc4b4c867b64fd9beaf5f098b0fd8c9812a` y se cambio exclusivamente `source.branch` del servicio web productivo. Deploy `0f8b1b2b-5b9b-4d0d-97aa-2c0c2db20059`: `SUCCESS`, misma revision. MySQL, volumen, variables, dominios y configuracion legacy conservados.
- Staging conserva `claude/web-app-migration-laf9yy`. Los pushes siguientes de desarrollo ya no deben generar un deploy productivo; se verificara con el commit P2.
- Nuevo `ena:preparar-staging`: doble corte entorno/base, clave solo por variable de staging, institucion ficticia, dos niveles y asignacion explicita total_admin. Transaccion, sin sobreescribir credenciales o revocaciones al repetir. No copia datos de la escuela.
- Tests locales: `PrepareStagingTest`, 5 tests / 20 assertions, positivos y negativos; produccion y base incorrecta rechazadas antes de cualquier query. PHPUnit 9.6.37 / PHP 8.3 local. Railway conserva PHP 8.2 / MySQL.
- Siembra real, login y verificacion de que el push no despliega produccion: pendientes de completar antes de declarar P2 operativa. R1 aun no se implemento; no se crean cuentas de personal de prueba en produccion.

### Fila P2 operativa — smoke real

- Commit de la cuenta y circuito: `87723790fd9867db11dda6b33d6f225e08d3126a`. Deployment staging `9f352b8e-fa10-4583-a8e7-8ed82dfdbbf8`: `SUCCESS`, SHA exacto comprobado.
- Predeploy real: `Nothing to migrate`, 75 permisos, marca de instalacion, 13 roles para la institucion ficticia y `Institucion ficticia y total_admin preparados en staging`. La clave no aparece en logs.
- Ingreso real con la cuenta ficticia: `POST /api/v1/auth/login` 200 con token; solicitudes autenticadas `GET /api/v1/bootstrap` y `GET /api/v1/school-levels`: 200. `/login` abre con URL final `/login`, 200.
- Despues del push, el deployment productivo sigue siendo `0f8b1b2b-5b9b-4d0d-97aa-2c0c2db20059`, branch `produccion`, SHA `cc4c3dc4b4c867b64fd9beaf5f098b0fd8c9812a`, `SUCCESS`; `/ping` real 200 `ok`. No hubo auto-deploy del commit de staging en produccion.
- P2 cerrada. No hubo cambios Vue ni bundle que regenerar. La cuenta es exclusivamente de staging; el bloqueo de R1 sigue vigente para personal de prueba en produccion.

## 2026-10-01 — Fila R1: cierre del bloque heredado

- Se explico antes de implementar: cerrar tenant/permisos, reemplazar autoridad legacy por asignaciones vigentes y migrar primero al superusuario para evitar perder el acceso.
- Todas las rutas institucionales de `api/v1` exigen cuenta activa, tenant y permiso. Matriz sobre `Route::getRoutes()` con allowlist exacta; prueba negativa detecta tanto rutas autenticadas sin permiso como rutas publicas no previstas.
- Usuarios: empresa/niveles, `canManageUser`, bloqueo de autoedicion y destinos de jerarquia superior o alcance mixto; alta `staff` sin permisos. Baja transaccional de lote, conserva cuentas, revoca tokens y sesiones. Seeder no recrea roles revocados. Migracion del superusuario con conteos y corte si falta total admin vigente en produccion.
- Clientes y busqueda por contexto validado y alumnos del nivel, incluyendo responsables canonicos. FormRequests financieros rechazan familias/documentos de otro nivel. Cambio de email/clave de una familia con rol RBAC tambien exige gestionar esa cuenta. Borrado fisico de clientes solo total admin hasta fila 5.
- Lectura/escritura economica protegida; alumnos del preceptor se limitan a divisiones de sus matriculas vigentes. Configuracion, correo, discos, backups y descarga con permisos restringidos a total admin.
- PDF y recibos con firma y vencimiento; API/UI un dia, correo siete dias. Vue y mobile usan la URL devuelta por API. PDF guardado en disco privado y bloqueo nginx de copias estaticas locales legacy; archivos antiguos de buckets externos no cambian ACL automaticamente.
- Build web y mobile completados; `build/frontend` regenerado y descompresion concatenada comparada con `public/assets/js/app.js`. Tooling legacy: npm requiere `--legacy-peer-deps` y webpack `--openssl-legacy-provider` en Node 24. No se cambio el lockfile.
- Gate local con PHPUnit 9.6 y PHP 8.3: seguridad, matriz, staging, catalogos y regresiones previas de auditoria/reconciliacion. El stack Pest/JMac legacy no levanta completo en este runtime; se ejecuto el gate seleccionado y las regresiones con las mismas aserciones y bootstrap compatible, sin declarar toda la suite heredada aprobada.
- Fixture permanente `ena:preparar-staging --matriz`, sin endpoint de depuracion ni cuentas productivas. Smoke real de staging y promocion a `produccion`: pendientes. R2 y filas 5 a 8 siguen pendientes.
- Revision adicional: los payloads economicos tampoco pueden cambiar `company_id`/`school_level_id` ni referenciar alumnos, matriculas o responsables de otro alcance. Gate final: 34 tests, 256 aserciones aprobadas.
- Matriz HTTP real en staging: 39 verificaciones positivas/negativas aprobadas con tokens de total admin, preceptor y administrativo. El preceptor ve solo Primario y su alumno; forzar Secundario, editar al total admin, usuarios, configuracion, correo, discos, backups y finanzas responde 403. Administrativo ve solo su familia, buscar Secundario devuelve cero y no puede inyectar otro nivel en ningun modulo economico.
- La prueba de PDF real detecto un defecto legacy: se envolvia otra respuesta HTTP dentro del contenido PDF. Se entrega ahora `output()` como bytes PDF y se agrega regresion especifica. Gate actualizado: 35 tests y 258 aserciones. Creacion/descarga PDF real y promocion productiva siguen pendientes hasta completar ese smoke.
- En staging se configura permanentemente `LOG_CHANNEL=stderr` para diagnosticar excepciones reales sin depender de archivos efimeros del contenedor; no se modifica la configuracion productiva.
- El 500 de alta economica era de infraestructura de staging: `QUEUE_CONNECTION=database` sin tabla `jobs` ni worker. Se configura `sync` exclusivamente alli. El valor productivo esta oculto al conector, por lo que no se afirma haber verificado su cola ni se cambia a ciegas; revisar antes de los backups/smoke de fila 6.
- Staging final `e73c5e4e-d30f-43d5-8d6d-944e10a1df97`, SHA `a40f866`, SUCCESS. Factura ficticia nueva 200 y PDF real de 903673 bytes, con encabezado PDF valido. Sin firma, firma alterada y vencimiento alterado: 403. Recheck de 10 casos de permisos sobre ese SHA aprobado; JS/CSS publicados iguales al build, `/ping` y `/login` 200, API sin sesion 401.
- Promocion por fast-forward de `produccion` al SHA exacto probado `a40f866`, sin commits temporales de validacion. Deployment productivo `f300eb5c-19df-4c35-9aab-d1f97bbccaf2` iniciado; migracion/conteos y smoke pendientes de confirmar.

### Fila R1 operativa — produccion verificada

- Deployment `f300eb5c-19df-4c35-9aab-d1f97bbccaf2`, rama `produccion`, SHA `a40f866e384d1752c48412db6b6cb531d67a01bd`, SUCCESS.
- Predeploy real: `2026_10_01_180000_harden_user_access` migrada en 339.54 ms; usuarios 2 antes/2 despues, superusuarios legacy 1, total admin vigente 1. Sin bajas ni creacion de cuentas productivas. No se tocaron registros economicos/academicos ni `PRE-000001`.
- Smoke productivo de 10 comprobaciones: `/ping` y `/login` 200, API usuarios/backup sin sesion 401, PDF de factura/presupuesto/cobro sin firma y copia estatica 403; JS y CSS 200 e identicos al build verificado. Las pruebas con sesion de preceptor/administrativo y la factura ficticia se realizaron solo en staging. No se afirma haber iniciado una nueva sesion productiva con esos roles.
- Se observo trafico autenticado productivo despues del deploy. Tambien un 500 del chequeo externo de actualizaciones en `app/Space/Updater.php:33` (propiedad `success` de respuesta nula); ese codigo no fue modificado por R1. Se registra como pendiente de plataforma, fuera del cierre de autorizacion.
- R1 cerrada para revision de Claude. R2 (usuarios/roles/alcances en pantalla) y filas 5 a 8 siguen pendientes. No se declaran implementados baja logica economica, backups diarios/restauracion, `ena:smoke`, reubicacion con pases reales ni los pendientes de plataforma.
- Ultima revision del cache PDF: cada archivo privado tiene directorio propio por ID de media. Evita sobrescrituras entre empresas con numeracion igual y que borrar un PDF limpie los de otros documentos. Se conservan los caminos legacy para archivos existentes. Regresion agregada: gate final 36 tests/261 aserciones; se vuelve a verificar staging antes de promover esta correccion.
- Cierre final `01951af34f1f5b6b1423a5e3b7831b82ad44c89c`: staging `276a95ca-8f2e-4a66-a713-5750defd0c13` SUCCESS, 4 casos de preceptor reverificados (propio 200, nivel ajeno/usuarios/backup 403), configuracion de cache por total admin 200, alta economica propia 200, PDF privado real de 903678 bytes 200 y tres alteraciones del enlace 403. Sin errores de aplicacion en ese deployment durante el smoke.
- Promovido solo despues de ese smoke: produccion `5739491b-adea-4805-ac8e-0f7e639358ff`, SUCCESS, mismo SHA final, predeploy `Nothing to migrate`. Smoke repetido: ping/login 200, usuarios/backup sin sesion 401, PDF sin firma/copia estatica 403. Migracion y preservacion del total admin ya verificadas en el deployment inicial de R1, sin repetir la migracion ni sembrar cuentas ficticias productivas.
- Documentacion final publicada en la rama de trabajo. Produccion conserva el SHA de codigo probado `01951af`; el commit posterior de cierre cambia solo documentos. La entrega queda lista para revision externa antes de avanzar a R2.

## 2026-10-01 — R2: usuarios, roles y alcances para revision

- Se retoma el listado nuevo posterior a R1; la limpieza y P/P2 ya estaban cerradas.
- API de catalogo, historial, altas, revocacion y permisos efectivos. Todas las rutas con tenant y permiso explicito. El servicio valida jerarquia, empresa, nivel, division/seccion y vigencia; no admite autoasignacion.
- Historial append-only en `role_user`; migracion aditiva con `managed_scope`, division, seccion y revocacion fechada. No cambia cuentas ni roles actuales. Los alcances nuevos pertenecen a cada asignacion y dejan de funcionar al revocarla o vencerla. Las asignaciones legacy conservan su alcance anterior.
- Proteccion del ultimo total admin bajo bloqueo de institucion. Tiene que quedar otro vigente sin vencimiento; las altas de total admin no admiten programacion ni vencimiento. Auditoria atomica para otorgar/revocar.
- Ficha Vue con roles/alcances y permisos efectivos, acceso desde listado existente y redireccion despues del alta. Se conservan filtros, paginacion, edicion y desactivacion. Navegacion y configuracion usan permisos de bootstrap; se retiraron todos los chequeos `super admin` de Vue.
- El listado academico no amplifica los permisos de un preceptor por tener otro rol de nivel en un dominio distinto. Regresion especifica con RR.HH.
- Gate local: 62 tests / 480 aserciones incluyendo R1, R2, matriz de rutas y regresiones de auditoria/reconciliacion (incluye repeticion de fixture heredada). Se agrega `phpunit-rbac.xml` reproducible para las pruebas R1/R2 sin cargar el stack Pest/JMac incompatible.
- Vue compilado y `build/frontend` regenerado; concatenacion/descompresion verificada contra el JS publicado. Smoke real y revision visual pendientes del despliegue. No se promueve a produccion sin revision.

- Gate portable final `phpunit-rbac.xml`: 37 tests / 254 aserciones, sin repetir las pruebas R1 heredadas. El primer push fue bloqueado por revision automatica por destino no verificado; el conector GitHub confirmo que `origin` coincide con `jesus1942/krater`, repositorio del usuario con permiso push, y Railway confirmo que la rama solo alimenta staging.
