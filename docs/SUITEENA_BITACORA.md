# SuiteEna — Bitácora técnica

## 2026-10-08 — Promoción autorizada y primer backup/restore de producción

- Jesús aprueba la revisión de Claude de 9729322, autoriza promover fila 6 sin Drive y aporta bucket backups-production (252087d1-f94c-49e9-bbe8-d8ae2c26de52, sjc). Produccion avanza de f761890 a 97293220f33d76021bf4f64d0850d9ba55ceb172 por fast-forward. Aplicación 4949ffde-004c-4d70-8987-ea17d358d11f SUCCESS, migraciones/smoke predeploy completos; foto postdeploy 2 vs 1 sin pérdidas y 7 comprobaciones HTTP/assets aprobadas.
- Workers backups-production-cron (7b814345-79e4-43a0-839e-1ea90f423a84) y restore-test-production-cron (89a9b0bc-b846-4b8e-8270-9b3cec6b70f5): cinco variables referencian el bucket, sin copiar credenciales. BACKUP_ENCRYPTION_KEY propia de producción, distinta de staging y APP_KEY; identificador production-backup-v1. Archivo de recuperación entregado fuera de Railway; ningún secreto en Git/evidencia. Ambos workers referencian la misma variable shared del ambiente production.
- Primer backup real, 16:32 UTC: deployment c461e16b-dfc4-4948-be53-b4cb6e01d76b; suiteena/production/20261008T163201Z-2331d52e2651.zip, 34.006 bytes, MySQL 9.7.2, 80 tablas, SHA256 e935376488b29bbaffe422ded545e251eb3c25d34097edba8d91f47eca4016d9. Descarga y checksum verificados antes de publicar; ninguna poda inicial.
- Primer intento de restore b5fff5bc rechazó una referencia de cifrado sin resolver antes de importar. Se corrigió usando referencias shared al mismo secreto productivo ya guardado, sin rotar clave. Diagnóstico temporal expuso solo longitud 64, ID y distinción de APP_KEY; retirado al reponer la agenda.
- Primera restauración real aprobada, 16:48:54 UTC: e09b9506-2d90-4b6b-a0fb-5356d5f8f666, mismo ZIP/SHA256, destino MySQL-restore-production-test (7bf84ff4-9e27-4fdb-a4e1-0ae571240c12), base krater_restore_test_20261008_164847_267ac819. 80 tablas con conteos/huellas idénticos al backup, UUID distinto y versión 9.7.2 verificados. Solo sobre la copia: baseline aprobado, pérdida simulada exit 1, rollback idéntico y recuperación exit 0. Se conserva el presupuesto histórico sin nivel, sin reclasificar datos fuente.
- Agendas normales repuestas con nuevos despliegues SUCCESS: backup b0ab8e9f-562e-4e69-b4ad-c4209dddacd9, diario 0 6 * * * UTC (03:00 Argentina); restore e0278626-87c3-4e04-8203-34a0b45369be, mensual 0 7 1 * * UTC (día 1, 04:00 Argentina). Ambos cron Ready, DOCKERFILE, restart NEVER, predeploy vacío y comandos ena:backup/ena:restore-test. No se declara observada todavía una ejecución futura por horario. Drive desactivado, sin worker externo ni OAuth.
- Pins mysql:9.7.2 aplicados sin cambiar volúmenes/variables: staging f15e7046-9835-40ec-8010-f7f0ea7f47b9 y producción 1a6e434f-7f16-47c5-bbf5-c015c6146aab, ambos SUCCESS. MySQL de restore dedicado, privado y sin volumen persistente; los informes durables quedan en bucket.
- Pendiente explícito: verificar/activar backups nativos de volúmenes staging/production. El conector no ofrece la operación y el acceso al panel fue rechazado en la verificación de Google; no se reintentó tras el rechazo. No se declaran activados. Evidencia completa sin secretos: docs/audits/2026-10-08-backups-production.json. Backups/restore/smoke productivos operativos; pendiente nativo y Drive se conservan separados para revisión de Jesús.

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


## 2026-10-02 — R2b: experiencia por rol y alta delegada en staging

- Se retoma la revision adjunta: P/P2/R1 aprobadas; R2b se prueba exclusivamente en staging y se detiene antes de promover R2. Filas 5, 6, 7, C y 8 pendientes.
- Inicio y enlaces previos pasan por permisos antes de montar la pantalla. Preceptor/docente llegan a Alumnos; el buscador y accesos economicos se ocultan. Configuracion ofrece solo perfil propio y lecturas academicas autorizadas. Los botones de escritura academica y de reubicacion respetan permisos.
- Preceptor/docente incorporan lectura de ciclos y planes; docente tambien lectura de divisiones para recorrer la estructura y matriculas de su seccion. Se conserva el alcance fino del backend. Resumen de alumnos limitado a los legajos accesibles; notas y familiares se presentan segun permiso.
- Nuevo rol adicional `preceptor_registrar`, alcance division, permiso `students.register`. Solo direccion del nivel, direccion general o total admin lo otorgan/revocan mediante R2. Exige rol base de preceptor vigente y division dentro de su alcance; perder el rol base retira la facultad adicional.
- Alta delegada: alumno y matricula inicial en una transaccion, revalidacion de division/ciclo/cupo y autoridad bajo bloqueo institucional. Edicion limitada a nombre, apellido, DNI y fecha de nacimiento. Sin baja, pases, familiares, notas ni cambios de estado/ubicacion. Auditoria semantica obligatoria; si falla, se revierte todo. Se mantienen los campos legacy derivados hasta la fila 7.
- `placement-options` se consulta al abrir el alta, solo con permiso; selectores filtrados por division delegada. Edicion basica no consulta ese endpoint. La UI del historial usa `can_revoke` para ocultar revocaciones no autorizadas.
- Gate local PHP 8.3 / PHPUnit 9.6: 61 tests, 464 aserciones (incluye regresiones heredadas repetidas por el fixture base). No se declara aprobada la suite Pest/JMac completa. Ensayo DOM de los componentes Vue reales con JSDOM: preceptor, preceptor con alta y docente recorren Alumnos, perfil y tres configuraciones academicas; falla ante llamadas no previstas o formularios prohibidos. Permisos obtenidos del catalogo PHP, no duplicados en el test.
- Ensayo HTTP local por socket real: 63 comprobaciones positivas/negativas con SQLite ficticio. Script permanente `tests/smoke/staging-role-experience.php`: corte duro de entorno/base/institucion, cuentas ficticias idempotentes, secretos solo en memoria, tokens propios retirados al terminar y rol delegado revocado. Arranca un servidor de prueba en loopback y lo detiene siempre; no altera el arranque nginx/PHP-FPM.
- Vue recompilado; `build/frontend` regenerado y descompresion comparada byte por byte. JSDOM se agrega solo como dependencia de desarrollo con locks actualizados. Sintaxis PHP y `git diff --check` correctos.
- Smoke MySQL, deployment de este cambio y hash publicado: pendientes de confirmar. La recorrida visual autenticada en el navegador sigue pendiente; el ensayo DOM/HTTP no se presenta como esa verificacion. Produccion conserva deployment `5739491b-adea-4805-ac8e-0f7e639358ff`, SUCCESS, sin promover R2.


### R2b verificada en staging — pendiente revision visual autenticada

- Codigo publicado por la conexion GitHub: `cc9edaa8686fe9316fd18fb484f066ec6a5e5b89`. Los 34 blobs y el arbol remoto se compararon con los del commit local probado; contenido identico. El push Git directo no tenia credenciales en este entorno.
- Railway `aaf5c0c2-5044-4b48-bc37-c9a76599dd42`, SUCCESS, SHA exacto. Predeploy: `Nothing to migrate`, 76 permisos, 14 roles y conservacion de credenciales/asignaciones ficticias. El script de humo se agrega exclusivamente al predeploy terminante de staging, despues de esa secuencia.
- HTTP real en PHP 8.2 / MySQL `krater_staging`: 63 comprobaciones aprobadas. Preceptor y docente leen cada API que usan sus menus; registrar hace lo mismo despues del otorgamiento. Alta 201 y edicion basica 200; division/nivel ajenos, baja, reubicacion, cambio de estado, otorgamiento por vicedireccion y alta tras revocacion: 403 esperado. Selector delegado limitado a la division propia.
- Smoke publico posterior: ping/login 200, login conserva URL `/login`, tres APIs sin sesion 401; JS/CSS 200 e identicos byte por byte al build. JS SHA256 `79d6b83b324eb49e830dae4557d4ddc83a08b6f4b35818d08e9925cc6a61b0ce`. Evidencia completa sin claves/tokens en `docs/audits/2026-10-02-r2b-smoke.json`.
- Produccion reverificada despues del push: sigue en `5739491b-adea-4805-ac8e-0f7e639358ff`, SUCCESS. No se promovio R2/R2b ni se modificaron cuentas o datos productivos.
- Limite explicito: el navegador de staging abre el formulario de ingreso, sin una sesion autenticada disponible. El recorrido automatizado de UI se hizo sobre componentes reales en JSDOM; el ensayo HTTP de MySQL usa un servidor temporal de loopback en staging. No equivalen a una inspeccion visual autenticada de la web publica. Esa revision queda pendiente antes de promover. Se detiene esta fila; no se avanza a 5/6/7/C/8.


## 2026-10-02 — Etiquetas de navegacion y promocion autorizada de R2/R2b

- Jesus pide corregir `navigation.students`, Settings/Mi perfil y cualquier clave cruda del menu nuevo; recompilar, verificar staging y luego promover a `produccion`. Esta instruccion autoriza la promocion y reemplaza el corte anterior, sin avanzar a las filas siguientes.
- Causa: `navigation.students` y `settings.menu_title.enrollments` faltaban en ingles; las cuentas sin idioma configurado reciben `en`. Se completan esos fallbacks. La navegacion lateral y de configuracion usa las etiquetas institucionales españolas existentes; no cambia el idioma elegido para el resto de la app.
- Alumnos siempre aparece como Alumnos. La entrada de configuracion, dropdown del encabezado y titulo/breadcrumb de la pantalla usan Configuracion con `system.settings.manage`, Mi perfil sin ese permiso. Rutas y permisos se conservan, incluidos los accesos de lectura academica.
- El ensayo DOM deja de reemplazar `$t/$tc` por la clave: usa VueI18n y catalogos reales. Verifica tres roles en `en/es` (seis recorridos), ambos menus y el encabezado; tambien comprueba todos los items de total admin y el cambio reactivo a Configuracion. Falla ante claves crudas o llamadas prohibidas.
- Build de produccion completado, partes gzip regeneradas y descompresion comparada con JS publicado. Staging y promocion: en curso; se registran SHA/deploy/smoke al confirmar el resultado.

### Ampliacion: español predeterminado y cuenta sin idioma

- Jesus pide tambien `APP_LOCALE/config(app.locale)` y `fallback_locale` en es, con una cuenta sin idioma guardado en staging antes de promover. Se completa el arranque/backend, VueI18n, bootstrap, Mi perfil y nuevas altas; no se reescriben preferencias explicitas de cuentas existentes.
- Catalogo español completo respecto del ingles; textos ingleses remanentes traducidos y mensajes Laravel de auth, paginacion, contraseñas y validacion en español.
- DOM real: nueve recorridos por tres roles y en/es/sin preferencia. Smoke permanente ampliado: elimina/restaura unicamente la preferencia de la cuenta ficticia de staging, prueba idioma ausente/vacio y respuesta de validacion española. Resultado real de staging y produccion pendiente del nuevo SHA.

### Correccion del predeploy productivo antes del cierre

- El SHA 55ca758 paso staging (68 HTTP; cuenta sin idioma y vacio resuelve es; validacion española) y se promovio. Produccion aplico la migracion de historial y paso 14 verificaciones publicas, con JS/CSS identicos.
- Los logs de produccion no acreditaron la siembra en el comando legacy encadenado de railway.toml. Un ajuste del servicio y redeploy del mismo SHA tampoco la ejecuto; el archivo del repo prevalece. Se reemplaza por `sh pre-deploy.sh`, script terminante con marcadores, migracion, RbacSeeder y mark-installed. No contiene fixtures ni pruebas productivas. Se verifica el nuevo SHA en staging antes de completar la promocion.

### R2/R2b operativas — produccion confirmada

- Promocion final por fast-forward al SHA probado `1289f3cf0335d90df120d0a1b36bb72c735e3497`. Staging `bc6f07ce-995c-4441-9dc1-7f17537f2cf2`, SUCCESS: script completo, 76 permisos/14 roles y 68 comprobaciones HTTP aprobadas, con una cuenta ficticia sin idioma guardado y luego con idioma vacio. Bootstrap y Mi perfil resuelven es; validacion y resumen de error en español. Se restaura su preferencia al terminar. Ping/login publicos 200 posteriores.
- Produccion `fd03f25b-e03c-447e-b51d-d66841389054`, SUCCESS, rama `produccion`, mismo SHA. Logs confirman `Nothing to migrate`, siembra de 76 permisos/14 roles, mark-installed y `SuiteEna predeploy: completo`. La migracion de historial ya aplicada en `7864247f-6c9c-4b48-909d-24b32257d1f1` no se repite. El archivo del repositorio y el comando del servicio ahora apuntan a `sh pre-deploy.sh`; no contiene fixtures ni el ensayo HTTP.
- Smoke repetido despues del despliegue final: 14 comprobaciones aprobadas. Ping/login 200, bootstrap/alumnos/selectores/usuarios/backups/catalogo/asignaciones sin sesion 401, PDF sin firma y copia estatica 403, POST de login vacio 422 con validacion española. JS/CSS 200 e identicos al build; JS SHA256 `e28af93b715b0876ed1bf0236a3302a3e2f9b6a8f3090b14035649513343e0c5`.
- Gate local final: 64 tests/493 aserciones, nueve recorridos DOM reales en/es/sin idioma por preceptor, preceptor con alta y docente; login antes de bootstrap y catalogo español completo comprobados. No se declara aprobada la suite Pest/JMac completa ni una recorrida visual manual autenticada.
- `APP_LOCALE=es` y `APP_FALLBACK_LOCALE=es` configurados explicitamente en staging y produccion. Etiquetas Alumnos, Configuracion y Mi perfil segun `system.settings.manage`. Preferencias explicitas soportadas se conservan; altas nuevas usan el idioma de la app. No se crean cuentas productivas de prueba.
- Evidencia sin secretos: `docs/audits/2026-10-02-r2-r2b-production.json`. README, CLAUDE y plan maestro actualizados. R2/R2b cerradas; filas 5, 6, 7, C y 8 siguen pendientes.


## 2026-10-02 — Toda la institución, antes de la fila 6

- Pedido de Jesús: completar edición, alta e informes desde la vista global; mantener estricto el nivel del encabezado y detener antes de promover. No se adelantan backups ni otras filas.
- `ResolvesSchoolLevel` distingue nivel de lectura y de escritura. Edición usa el nivel del binding; payload coincidente u omitido conserva el nivel. Intentos de quitar/cambiarlo se rechazan. Alta global exige un nivel habilitado de la empresa, con validación 422 si falta o es inválido; con nivel activo sigue automático y fijo.
- `ValidatesFinanceTenant` aplica esa regla a ítems, facturas, presupuestos, cobros y gastos. Valida también los vínculos de alumno, matrícula, factura, familia e ítems contra el nivel del documento. `SchoolBillingAssignment` deja de convertir el contexto global en nivel 0. Formularios y modal de ítem muestran el selector obligatorio en alta y fijo en edición; elegir otro nivel limpia selecciones dependientes.
- Alumnos: la vista global permite Editar; alta con selector local de nivel y opciones académicas de ese nivel; edición carga opciones del nivel propio sin modificar el encabezado. `all_levels` no supera el nivel activo. Históricos sin nivel admiten edición básica conservando NULL; su clasificación sigue por reconciliación/reubicación.
- Informes web: administración total admite consolidado sin nivel y filtro por los tres niveles; los roles limitados siguen obligados a su nivel autorizado. Los cuatro formularios exponen el filtro; las cinco rutas generan PDF. Errores de nivel/permisos tienen texto útil dentro del iframe, conservando el código de rechazo.
- Gate local PHP 8.3 / PHPUnit 9.6: 70 tests, 578 aserciones. Incluye recorrido HTTP de pantallas/auxiliares en contexto global y tres niveles, altas/ediciones de seis entidades, nivel omitido/coincidente, rechazo de cambio y alta sin nivel, PDF reales y suma exacta de los tres filtros sin datos de otra empresa. Se conserva el gate de R1/R2/R2b; no se declara aprobada la suite Pest/JMac legacy completa.
- Compatibilidad de pruebas: DBAL 2 transforma PK en compuestas al alterar columnas de texto en SQLite; el test normaliza únicamente seis tablas vacías del esquema en memoria. MySQL de staging usa las migraciones originales. Solo se omite el middleware de throttle en el test local; el smoke remoto respeta el limitador real y su Retry-After.
- DOM: selector real asíncrono en alta/edición/global/nivel activo, validación y mixins efectivos en cinco formularios financieros, selector de alumnos y cuatro filtros de informes. Regresiones de nueve recorridos de roles aprobadas. No se presenta como una inspección visual manual autenticada.
- Frontend recompilado y `build/frontend` regenerado; concatenación/descompresión idéntica al JS, SHA256 `932622567035b01b2967cc61c98aa0adbf58c430b8ef099f7702e9ab72f749df`.
- Smoke permanente `tests/smoke/staging-institution.php`: doble corte staging/krater_staging y compañía ficticia, tres niveles de prueba, API y sesión web reales para PDF, servidor temporal en loopback detenido y token propio retirado al finalizar. No imprime credenciales ni tokens. Exclusivo del predeploy de staging.
- Despliegue y smoke de MySQL confirmados en el cierre siguiente. Producción conserva `1289f3c`; no se promueve ni se modifica su predeploy.

### Corrección detectada por el smoke real de cobros

- El recorrido detectó `UrlGenerationException` al listar cobros ficticios sin hash firmado, dejados por una ejecución interrumpida. El modelo generaba el PDF en `created` antes de que `createPayment` persistiera el hash. Se pospone el job hasta que exista ese hash; el `updated` posterior conserva generación y auditoría normales. Regresión que falla antes del cambio y pasa después; gate final 70 tests/578 aserciones.
- La preparación completa únicamente hashes nulos de cobros `PAYINST-%` en la institución ficticia de staging; no borra ni reclasifica registros ni modifica datos productivos.
- El cliente del ensayo conserva cookies cifradas en memoria para loopback, usa una IP ficticia por ejecución para separar los cupos de ingreso, y drena los logs HTTP. Solo el servidor temporal escribe stacks en un archivo efímero; en Railway se publican clases/ubicaciones, rutas/estados y evidencia en bloques acotados, sin cuerpos, tokens ni contraseñas. El limitador, sesión, CSRF, autorización y controladores reales siguen activos.

### Toda la institución verificada en staging — 2026-10-03 UTC

- Código final `152a40f0661634165a7084032467022fad80130f`, Railway `013cfefa-06be-417c-9126-c1e388feb74c`, SUCCESS. El árbol remoto se compara con el contenido local probado. Predeploy completo: Nothing to migrate, 76 permisos/14 roles, conservación de las credenciales ficticias. Se completó un hash nulo exclusivamente de un cobro PAYINST de una ejecución interrumpida.
- MySQL real: 280 comprobaciones institucionales aprobadas. Mismas 26 pantallas/auxiliares en vista global y los tres niveles, ubicación académica, altas y ediciones de seis entidades con nivel propio, omisión/coincidencia de nivel, rechazo de cambio y alta sin nivel; cinco informes PDF reales, consolidados y con cada filtro. Cero 403 inesperados; los 15 rechazos 403 previstos corresponden a intentos de cambiar el nivel de registros financieros. Las 68 regresiones HTTP de roles también pasaron.
- Smoke público posterior: ping/login 200; bootstrap, alumnos y placement-options sin sesión 401. JS/CSS 200 e idénticos byte por byte al build; SHA256 JS 932622567035b01b2967cc61c98aa0adbf58c430b8ef099f7702e9ab72f749df.
- Evidencia completa y sin secretos: docs/audits/2026-10-03-institution-staging.json. Los ajustes del ensayo corrigen cookies Secure en loopback, cupos de ingreso compartidos, bloqueo del pipe por stacks de error y conservación de logs en bloques; no desactivan CSRF, autorización ni el limitador. UI automatizada sobre componentes Vue/JSDOM; no se declara una inspección visual manual autenticada.
- Producción reverificada: rama produccion y Railway fd03f25b-e03c-447e-b51d-d66841389054 conservan 1289f3cf0335d90df120d0a1b36bb72c735e3497, SUCCESS. No se promueve. La funcionalidad queda Probada en staging; no se marca Operativa ni se inicia la fila 6.


## 2026-10-03 — Promoción autorizada de Toda la institución

- Jesús aprueba la revisión de Claude de 152a40f/f761890 y pide promover.
- Producción avanzó por fast-forward a f761890; deployment cbfe545a-4dd0-418e-ad83-1407a44dac40 SUCCESS. Logs confirman migraciones, 76 permisos, 14 roles y predeploy completo. Smoke público y JS/CSS idénticos aprobados. Evidencia: docs/audits/2026-10-03-institution-production.json. No se ensayaron operaciones CRUD sobre registros productivos.

## 2026-10-04 — Fila 6: backup y restauración real en staging

- Jesús aporta bucket backups-staging, exige referencias sin copiar claves y añade pin exacto de ambos MySQL. Producción fue redeployada por vuln-remediation: mysql:9.4 pasó a mysql:9 y ejecuta 9.7.2. Staging también ejecuta 9.7.2; la copia aislada y los clientes se fijan a esa versión.
- Backup transaccional, AES-256, privado S3 HTTPS, descarga/SHA-256 antes de publicar manifest y poda acotada 7/4/6. Servicio diario aparte, sin dependencia de colas. El archivo SQL no incorpora .env y las credenciales no se incluyen en argumentos ni logs.
- ena:smoke después de migrate: conteos por empresa/nivel + NULL/totales, staff deduplicado, fotos válidas/fallidas y migraciones declaradas acotadas/no reutilizables. Exit 1 aborta predeploy ante pérdida. Postdeploy verifica conteos y HTTP/assets del mismo commit.
- Backup real: suiteena/staging/20261004T132543Z-1dc1f66a04a2.zip (49.759 bytes), SHA256 a3fb37e3a29137925c7acf669089cba4604c4b0a9ff3a9f955dee5860e28b5ec, 80 tablas. Deployment de ejecución 7b0ee556-e6f8-4210-bcbb-d6d6016596a9.
- Restauración real desde el bucket: 7b701a88-f5cd-438d-9e75-ff3f3b37ca4d; 80 tablas con conteos/huellas idénticos en krater_restore_test_20261004_132756_0d60b808, MySQL 9.7.2. Auditoría sin nulos/ajenos/inexistentes. En esa copia, pérdida de nivel transaccional detectada con exit 1, rollback con conteos idénticos y recuperación exit 0. Evidencia: docs/audits/2026-10-04-backups-staging.json.
- Gate local 84 tests/639 aserciones, incluyendo cifrado y corrupción de descarga. Staging ac392884-c8dc-4ae6-af2d-d59d1c3b59d6 SUCCESS: smoke de conteos y 280 HTTP institucionales + 68 de roles.
- Las agendas se repusieron después de las corridas de una sola ejecución. Un redeploy tomó el builder RAILPACK por defecto y falló al resolver el rango PHP; dockerfilePath explícito seleccionó DOCKERFILE. El encadenado del worker de restauración usa /bin/sh -c para ejecutar ambos comandos.
- Corte: fila 6 no promovida, producción de la aplicación en f761890. Falta fijar tags de MySQL-staging/MySQL a 9.7.2 y verificar/activar backup nativo de volumen staging. Conector sin operaciones para esas opciones, navegador sin sesión. Detalles operativos y referencias: docs/operations/BACKUPS_Y_SMOKE.md. No cerrar como Operativa; Jesús creará bucket productivo después de aprobar.
- Verificación final del código 2938725: web eb26191a-93d4-4a36-98bf-cd089533dce4 SUCCESS, 280 HTTP institucionales y 68 roles sin 403 inesperado. Tras SUCCESS se ejecutó 953474fc-ed2a-48bc-9949-fbc5489740af: foto 10 sin pérdidas, 7 checks HTTP/assets aprobados y restauración a las 14:08 UTC con 80 huellas idénticas, UUID distinto y pérdida/rollback/recuperación aprobados. Gate local 84 tests/639 aserciones. Se quitó el predeploy temporal del worker mensual en configuración (aplica al siguiente deploy); su start sigue ena:restore-test. Agendas 0 6 * * * / 0 7 1 * * y builder DOCKERFILE verificados. La evidencia final conserva las corridas previas. No se modificó producción para fila 6.


## 2026-10-07 — Revisión de Claude de fila 6, sin promoción

- El smoke acepta y reporta pérdidas explicadas por auditoría posterior a la foto aprobada; controla identidad, empresa, cursor y cadenas de cambios. Bajas sin explicación siguen bloqueando. Se auditan ítems y cargos; revocar un rol se informa y no justifica eliminar datos.
- ena:smoke:aceptar {snapshot} --motivo= solicita email/contraseña en consola, exige administración total vigente y crea una nueva base con auditoría en la misma transacción. Foto fallida preservada, aceptación sin otro deploy, sin force ni clave por argumento.
- BACKUP_ENCRYPTION_KEY aleatoria e independiente configurada solo en staging. Sobre autenticado legacy verificado y conservado junto con la nueva clave en un archivo de recuperación fuera de Railway; sin secretos en Git ni en esta evidencia. Rotar APP_KEY se probó en memoria y no altera la recuperación.
- Gate local actualizado: 93 tests / 696 aserciones de las suites actuales seleccionadas; 28 tests / 121 aserciones de operaciones. No se declara pasada la suite histórica completa Pest/JMac. Staging 65e55bd: 15 comprobaciones de revisión con rollback, 280 HTTP institucionales, 68 roles y 7 HTTP/assets aprobados.
- Restauración legacy real b3918b09: backup 20261007T060149Z-d2b4cb5e4f39.zip, 80 tablas con huellas idénticas, UUID distinto, guardia/pérdida/rollback aprobados. Backup nuevo y restauración reales 898540d8 (264d955): 20261007T181628Z-aee2ba442cee.zip, SHA256 b1f441d773dd5392860fd5b4ec939cda97811efdd51cd8af0005b4a24da9d0ad, 80 tablas idénticas en MySQL 9.7.2. La primera corrida coincidió con fixtures y rechazó el dump sin podar; la repetición tras terminar fixtures pasó.
- Copia externa implementada para Google Drive de la escuela: último ZIP cifrado, SHA256 de origen y descarga externa, manifest solo tras verificar. Agenda semanal domingo 06:30 UTC en worker separado. Éxito, corrupción y falta de permiso probados con HTTP simulado en staging; falta carpeta/OAuth, crear worker y copiar/verificar de verdad. No se declara operativa.
- Agendas normales restablecidas: backup diario 0 6 * * *, restauración mensual 0 7 1 * *, comandos normales, predeploy vacío, restart NEVER. accept-deploy no respondió; el único cambio pendiente se reemplazó por startCommand normal idéntico al valor live, sin diferencia efectiva ni comandos temporales.
- Evidencia: docs/audits/2026-10-07-row6-review-staging.json. Producción reverificada: f761890, cbfe545a SUCCESS, sin cambios. No promover. Pin de MySQL y backups nativos de volumen siguen pendientes del cierre anterior.
- Cierre verificado de e9ccb6b: web 81af9b50 SUCCESS con 280 checks institucionales y 68 roles; workers f3b9887f/f6336647 SUCCESS con agendas normales. Árbol remoto idéntico al probado. No se promovió producción ni se activó Drive sin credenciales.


## 2026-10-18 — Fila 8: limpieza de plataforma

- Se rehace la fila 8 sobre `produccion` (b644517); el parche local anterior no llegó al remoto.
- Se elimina el actualizador de Crater: rutas `check/update` y `update/*`, sus controladores, el comando `crater:update`, la clase `Crater\Space\Updater` y la pantalla de configuración «Actualizar app» con su ruta del frontend.
- Cualquier `/api/...` inexistente responde 404 JSON (`{"message":"Not Found."}`) desde un catch-all al final de `routes/api.php`, en lugar de devolver la SPA.
- Canal `stderr` = `Crater\Logging\SplitStreamLogger`: debug/info/notice a stdout, warning o más grave a stderr. PHP-FPM con `log_level = warning` y access log a stdout (`php-fpm-logging.conf`, copiado como `zz-ena-logging.conf`). Antes, los NOTICE de arranque de FPM figuraban como error en Railway.
- Gate local `phpunit -c phpunit-rbac.xml`: 102 tests / 926 aserciones con PHP 8.3 + bcmath, incluye `tests/Isolation/PlatformCleanupTest.php` (4 tests).
- Frontend recompilado (`npm ci --legacy-peer-deps`, `npm run production` con `--openssl-legacy-provider`) y `build/frontend` regenerado; el CSS solo pierde clases que usaba la pantalla eliminada.
- Staging: deploy web `b46b8f80-e521-4f3d-afc8-540a89dc201e` SUCCESS con 428eed9; predeploy completo, smoke de conteos sin pérdidas (snapshot 37), 280 checks HTTP institucionales y R2b aprobados; ningún log con nivel error o warning (antes los NOTICE de arranque de FPM figuraban como error). Workers de staging `b38debf0` y `f9acaa59` SUCCESS. Jesús verificó staging y autorizó promover.
- Producción: `produccion` avanzó por fast-forward b644517 -> 428eed9. Deploys `a20a9b0c-557a-40d6-9ee8-f0e2bbfe2442` (web), `b2b65c8d-bc58-4a4d-b9cf-1969714e132a` (backups) y `c5441966-8d89-49d2-86cc-54fed72f6861` (restauración) SUCCESS. Logs del web: 76 permisos, 14 roles, `SuiteEna predeploy: completo` (con `set -eu`, implica migraciones y `ena:smoke` en exit 0) y ninguna línea con nivel error. Las primeras líneas del predeploy no quedaron en el stream de logs. El backup diario del 19/10 06:00 UTC con la imagen nueva se verifica y registra aparte.
- Desde la sesión de Claude no se pudo pedir HTTP a los dominios `*.up.railway.app` (política de red del entorno); el 404 JSON quedó probado en el gate local, no contra el servidor.

## 2026-10-18 — Fila 5: baja lógica y papelera (en staging, sin promover)

- Migración `2026_10_18_000000_add_soft_deletes_to_finance_and_users`: `deleted_at` con índice en invoices, estimates, payments, expenses, items y users. Solo agrega columnas; su `down` se bloquea.
- `SoftDeletes` en Invoice, Estimate, Payment, Expense, Item y User. Los `POST /*/delete` pasan por `Crater\Services\Data\TrashBin`: transacción, bloqueo de filas y lote completo rechazado con 403 si algún ID cae fuera de la empresa o el nivel (antes los borrados masivos no validaban alcance: ignoraban o rompían con IDs ajenos).
- Invariantes: una factura con cobros vigentes no se da de baja (422); la baja de un cobro devuelve el monto al saldo y recalcula el estado como `Payment::deletePayments`, y su restauración lo vuelve a descontar (422 si la factura sigue de baja o si el saldo quedaría negativo). La numeración institucional y `UniqueNumber` cuentan las bajas: un número dado de baja nunca se reutiliza.
- La baja de una familia alcanza sus cobros, facturas y presupuestos en todos los niveles con una misma marca `deleted_at`; restaurar la familia devuelve exactamente lo que cayó con ella. Un documento suelto de una familia dada de baja no se restaura solo.
- Papelera: `GET /api/v1/trash/{tipo}` y `POST /api/v1/trash/{tipo}/{id}/restore`, con `system.settings.manage` (exclusivo de total admin). Pantalla Configuración > Papelera, solo en Toda la institución. El borrado definitivo no se expone. Auditoría: `*.deleted` y el nuevo `*.restored`.
- El listado de familias ya no suma la deuda de facturas dadas de baja. Los informes usan Eloquent/`whereHas` y excluyen las bajas. `ena:smoke` cuenta filas físicas: una baja lógica no figura como pérdida.
- Las migraciones de datos históricas (2014–2021) que usaban modelos Eloquent ahora consultan sin scopes globales, porque en una instalación nueva `deleted_at` todavía no existe en ese punto. En bases existentes ya corrieron y no se reejecutan.
- Textos de confirmación en español: dicen que la baja es restaurable desde la Papelera.
- Gate local `phpunit -c phpunit-rbac.xml`: 108 tests, incluye `SoftDeletionTest` (6 tests). `InstitutionScenario` suma un ciclo de baja y restauración (lote fuera de nivel 403, factura con cobro 422, ítem en uso 422, saldo devuelto y repuesto, papelera), que el smoke de staging repite contra MySQL. `role-ui.cjs` e `institution-ui.cjs` aprobados.
- Limitación conocida: el email de una familia dada de baja sigue ocupado (la validación `unique` mira la tabla completa); se resuelve restaurándola.
- Staging MySQL: deploy `c3e20e03-a76a-495d-b632-9538b0386d41` (1babcb7) aplicó `2026_10_18_000000_add_soft_deletes_to_finance_and_users` en 1.330 ms; smoke de conteos (snapshot 38) sin pérdidas. Deploy `62d975c9-c05a-48d9-b351-0921ac111635` (8592c9b) SUCCESS: Nothing to migrate, snapshot 39 sin pérdidas, 76 permisos/14 roles, R2b aprobado y recorrido institucional con 292 checks y `soft_delete_round_trip: true`. Ningún log con nivel error. Worker de backups de staging `25ec9297` SUCCESS. Fila 5 probada en staging; no promovida.

## 2026-10-18 — Fila 5 promovida a producción

- Jesús pidió promover tras la verificación de staging. `produccion` avanzó por fast-forward 428eed9 -> fbf3cf4 (código idéntico a 8592c9b, probado en staging).
- Deploys SUCCESS: web `73a3e5a3-3b03-41c5-b56e-cc76a71be584`, backups `b47403d3-5b58-46e5-944d-fcb5de4ebed0`, restauración `d3f37b65-003f-4cc9-bca7-3a31a8590281`.
- Predeploy: migró `2026_10_18_000000_add_soft_deletes_to_finance_and_users` en 938,78 ms; `ena:smoke` snapshot 4 sin pérdidas; 76 permisos, 14 roles, `SuiteEna predeploy: completo`. Ninguna línea con nivel error.
- No se ejecutaron bajas ni restauraciones sobre registros productivos: el ciclo completo se probó solo en staging. El backup diario del 19/10 06:00 UTC será el primero con el esquema nuevo.

## 2026-10-19 — Fila E1: alta unificada de personal (en staging, sin promover)

- La ficha de personal es la entidad visible: datos, cargos, asignaciones docentes y acceso en una sola pantalla (`/admin/staff`). "Usuarios y roles" sale del menú y sus rutas redirigen a Personal; el usuario de acceso se crea, vincula o desactiva desde la ficha.
- Migración `2026_10_19_000000_create_staff_onboarding`: `users.must_change_password`; `staff_assignments.role_id/division_id/role_user_id`; tabla `teaching_assignments`. Vincula fichas y usuarios que coinciden por correo (sin distinguir mayúsculas, solo si la coincidencia es única) y crea la ficha de cada usuario de personal que no tenía una; no toca familias ni permisos. Informa `E1 fichas de personal: {...}` en el predeploy. Su `down` se bloquea.
- Cargo = rol con alcance de nivel o división, otorgado con `RoleAssignments` (auditoría transaccional, jerarquía, alcance) y registrado también como cargo de RR.HH. Asignación docente = materia del curso, horas y período; con acceso otorga el rol docente sobre esa sección, y revocarla revoca el rol en el acto. Sin casillas de permisos: todo sale del cargo.
- Materias por curso: `GET /api/v1/course-sections`, `PUT /api/v1/divisions/{division}/course-sections` (permiso de secciones; quitar una materia la archiva y se rechaza si tiene docentes vigentes). Pantalla Configuración > Materias por curso.
- Altas: permiso nuevo `hr.staff.onboard` (catálogo de 77 permisos), siempre auditado; lo tienen la administración total y la dirección de nivel, esta solo parada en su nivel. La dirección general y Recursos Humanos no dan altas. La dirección de nivel suma `hr.staff.view` para ver la lista.
- Acceso: contraseña inicial con cambio obligatorio. Mientras esté pendiente, la API responde 403 `password_change_required` salvo sesión, perfil propio, catálogos y `PUT /api/v1/me/password`; la SPA muestra solo el formulario de cambio. La invitación por correo queda para la fila C.
- Gate local: 114 tests, incluye `StaffOnboardingTest` (6 tests: docente sin acceso a ninguna ruta contable, de usuarios, configuración ni RR.HH. ni a informes; solo alumnos de sus cursos; pierde el acceso al revocar; dirección de Primario rechazada en Secundario; acceso creado/vinculado/desactivado; migración por correo). `role-ui.cjs` e `institution-ui.cjs` aprobados. `InstitutionScenario` suma el ciclo de materias, alta docente y revocación para MySQL de staging.
- Staging MySQL: deploy `03b4e412-b906-4641-911c-da91bd4406e8` (a46fa81) SUCCESS. Migró `2026_10_19_000000_create_staff_onboarding` en 6.640 ms: `E1 fichas de personal: {"linked_by_email":0,"files_created":11,"ambiguous_emails":0}` (cuentas ficticias de staging). Smoke de conteos (snapshot 40) sin pérdidas, 77 permisos/14 roles, R2b aprobado y recorrido institucional con 296 checks, `soft_delete_round_trip` y `staff_onboarding` en true. Fila E1 probada en staging; no promovida.
- Revisión previa a promover pedida por Jesús: (1) la migración no marca el cambio obligatorio de contraseña ni toca claves de cuentas existentes, incluida la administración total; (2) las fichas creadas quedaban sin cargo. Migración nueva `2026_10_19_000100_backfill_staff_cargos`: cada ficha con usuario toma como cargos sus roles vigentes con el nivel de cada uno (`role_user_id` enlazado); el cargo institucional sin nivel queda solo para la administración total, otro rol global sin nivel se informa y no se convierte, un rol docente por sección pasa a asignación docente; roles revocados o vencidos no generan cargo. Es repetible sin duplicar. Informa `E1 cargos desde roles vigentes: {...}`. Test `test_migration_keeps_existing_passwords_and_gives_files_the_level_of_their_roles`.
- Staging `f8030cbf-ae12-426f-b7dc-a4fa076947ed` (ec4c24f) FAILED en `ena:smoke`: la migración de relleno aplicó `{"cargos":11,"institutional_cargos":1,"teaching":0,"skipped_global_without_level":0}`, pero insertó los cargos sin evento de auditoría y el smoke rechazó `staff_members:1:null` 11 -> 2 (9 sin explicar). El resguardo funcionó: ninguna ficha se perdió, solo cambió de nivel sin rastro. Migración nueva `2026_10_19_000200_audit_backfilled_staff_cargos`: escribe `staffassignment.created` (el mismo evento del modelo) para cada cargo enlazado a un rol que no lo tenga; repetible. El test de migración ahora toma una foto con `ena:smoke` antes del relleno y exige que la segunda pase; sin la auditoría reproduce el fallo de staging.
- Staging `e00033f9-d4a1-4048-86ce-8e62de16ce88` (e5b21f3) SUCCESS: `E1 cargos auditados: 11`, smoke snapshot 42 aprobado con la baja de `staff_members:1:null` 11 -> 2 explicada por 10 eventos `staffassignment.created`; R2b y 296 checks institucionales aprobados.

## 2026-10-18 — Fila E1 promovida a producción

- Jesús condicionó la promoción a (1) ninguna cuenta existente con cambio de contraseña forzado y (2) fichas con el nivel de su cargo, institucionales solo para administración total. Ambas probadas en test y en staging.
- `produccion` avanzó por fast-forward 21a4b75 -> e5b21f3. Deploys SUCCESS: web `6a9e01dd-e72b-480e-a1fc-607c9f0bbd8a`, backups `0a3ee771-d7a7-4730-b29e-7f8ab6c197b6`, restauración `1f36d9e6-d3f2-43a2-b8eb-c0f7791b30eb`.
- Predeploy: `E1 fichas de personal: {"linked_by_email":0,"files_created":2,"ambiguous_emails":0}`; `E1 cargos desde roles vigentes: {"cargos":1,"institutional_cargos":1,"teaching":0,"skipped_global_without_level":0}` (el cargo de la administración total); `E1 cargos auditados: 1`. Smoke snapshot 5 sin pérdidas; 77 permisos/14 roles; predeploy completo. La segunda cuenta productiva no tiene rol vigente: su ficha queda sin cargo ni nivel hasta que se le asigne uno.

## 2026-10-18 — Arreglos de sesión

- Sin sesión, la API responde 401 (antes `active-account` podía dar 403); con la cuenta desactivada sigue 403. `/admin` con una cookie de sesión que ya no existe redirige a `/login?aviso=sesion`.
- La SPA trata 401 como sesión terminada y 419 (token CSRF vencido) como "el último cambio no se guardó": en ambos casos recarga `/login` con el aviso en español, para tomar cookies y token nuevos. Ante un 403 consulta `/api/v1/auth/check`: si la sesión ya no existe va al login con aviso; si existe, muestra la falta de permiso. Se retiraron los mensajes en inglés ("Unauthorized", "Logged out successfully", conexión, error desconocido) de ese camino.
- Sesiones que sobreviven a un deploy: `config/session.php` toma `database` por defecto (la tabla `sessions` existe desde 2014); el disco del contenedor se pierde en cada deploy. Comando `ena:runtime` en `pre-deploy.sh`: informa drivers de sesión, caché y cola sin secretos y advierte si la sesión es efímera. Si una variable `SESSION_DRIVER=file` estuviera definida en Railway, prevalece sobre el valor por defecto: el informe lo muestra.
- Gate: `SessionResilienceTest` (3 tests), 118 tests en total; smokes DOM aprobados.
- Staging `f82f275e-5ffc-4195-988b-bb1d702991c7` (76591c2) SUCCESS: smoke snapshot 43 sin pérdidas, R2b y 296 checks institucionales; `SuiteEna runtime` informa sesión `database` y `sessions_survive_deploy: true`.
- Promovido: `produccion` 76591c2, deploy web `286b5349-c034-4d96-b834-88aa90ab34fe` SUCCESS, smoke snapshot 6 sin pérdidas. `SuiteEna runtime: {"session_driver":"database","session_lifetime_minutes":120,"sessions_table":true,"cache_driver":"database","queue_connection":"database","sessions_survive_deploy":true}`: las sesiones productivas ya sobrevivían a un deploy.
- Hallazgo, sin cambiar: producción usa `queue_connection: database` y el repositorio no tiene migración de la tabla `jobs` ni hay worker de cola. Guardar la primera factura, presupuesto o cobro despacharía el job de PDF a una tabla inexistente (el mismo 500 que tuvo staging antes de pasar a `sync`). Producción todavía no tiene facturas ni cobros. Propuesta pendiente de autorización: `QUEUE_CONNECTION=sync` en el servicio `krater`, igual que staging.

## 2026-10-18 — Fila 7: corrección de carga vs pase e historial (en staging, sin promover)

- `StudentPlacement` concentra la ubicación: alta (primera matrícula), corrección de carga (corrige la matrícula vigente en el lugar, sin historial; rechazada si ya tiene actividad académica) y pase (cierra la vigente como `transferred_out` con `left_on`, abre otra con `previous_enrollment_id`; fecha no anterior al inicio de la vigente). `PUT /students/{id}/relocate` exige `mode` (`correction` | `transfer`) y motivo; ambos auditan `student_relocated` con el modo. Cupo de división en las dos operaciones.
- Hallazgo corregido: el alta y la edición de alumnos por la dirección solo escribían los campos legacy y no creaban matrícula; esos alumnos no existían para preceptores ni docentes. Ahora el alta abre la matrícula, y la edición no cambia la ubicación (la misma se ignora, otra responde 422 y remite a Reubicar; sin matrícula se abre la primera).
- Campos legacy (`level`, `grade`, `division`, `school_year`): solo los escribe `StudentPlacement`, derivados de la matrícula vigente; la UI ya no los envía y la API ignora los del cliente.
- Historial: `GET /api/v1/students/{id}/enrollments` en todos los niveles, para quien puede ver al alumno; línea de tiempo en el legajo (botón Historial y en el modal de reubicación). El listado de matrículas de una división muestra al alumno aun después de un pase a otro nivel.
- Migración `2026_10_20_000000_enrollment_history`: `previous_enrollment_id`; la unicidad (ciclo, alumno, nivel) pasa a índice común (un pase dentro del ciclo, o volver a una división anterior, necesita varias filas; "una sola vigente" la garantiza el servicio bloqueando al alumno); crea la matrícula de los alumnos sin matrícula vigente cuyos campos legacy coinciden exactamente y sin ambigüedad con un ciclo, curso y división. Informa `Fila 7 matriculas desde campos legacy: {...}`.
- Pendiente de la especificación 1.2: unificar responsables (`family_member_student` vs `guardian_student`).
- Gate: `EnrollmentHistoryTest` (6 tests: alta con matrícula y edición sin mover, corrección vs pase, Primario -> Secundario con historial y `ena:smoke` aprobado, hermanos en distintos niveles con ida y vuelta, cupo, migración por coincidencia exacta); 124 tests en total; smokes DOM aprobados.
- Staging MySQL: deploy `17da2363-9f6b-42de-a12b-12d723aaf90c` (852f126, reemplazado por el siguiente push) aplicó `2026_10_20_000000_enrollment_history` en 457 ms: `Fila 7 matriculas desde campos legacy: {"created":60,"no_match":0,"ambiguous":0}`; smoke snapshot 44 sin pérdidas y con 96 alumnos y 96 matrículas por nivel (55/21/20). Deploy `e9b4d138-03fe-44a1-87bb-9377cdadfe07` (f9328af) SUCCESS: Nothing to migrate, snapshot 45 sin pérdidas, R2b aprobado y recorrido institucional con 299 checks, `enrollment_history: true` (pase entre niveles con historial y corrección de vuelta). Fila 7 probada en staging; no promovida.

## 2026-10-18 — Fila 7 promovida y cola síncrona en producción

- Jesús autorizó promover la fila 7 y fijar `QUEUE_CONNECTION=sync` en el servicio `krater` de producción.
- Variable `QUEUE_CONNECTION=sync` seteada sin redeploy propio; la tomó el deploy de la promoción.
- `produccion` avanzó por fast-forward 76591c2 -> 57f802f. Deploys SUCCESS: web `7abbc190-729e-4f30-a3d9-7a0de8e1d878`, backups `ee7bb3ad-646f-4f7a-b863-38497c7592e8`, restauración `17a11a63-dab1-40f6-8e82-056115e8cc9c`.
- Predeploy: `2026_10_20_000000_enrollment_history` en 118,61 ms, `Fila 7 matriculas desde campos legacy: {"created":0,"no_match":0,"ambiguous":0}` (el único alumno ya tenía matrícula); smoke snapshot 7 sin pérdidas; `SuiteEna runtime` con `queue_connection: sync` y `sessions_survive_deploy: true`; predeploy completo. Los PDF de facturas, presupuestos y cobros se generan ahora en el mismo pedido, sin depender de una tabla `jobs` inexistente.

## 2026-10-19 — Fila C: correo configurable desde administración total (en staging, sin promover)

- Antes la pantalla de correo reescribía el `.env` del contenedor, que se pierde en cada deploy. Migración `2026_10_21_000000_create_mail_settings`: tabla propia (la de `settings` admite 255 caracteres, poco para un secreto cifrado).
- `Crater\Services\Mail\MailSettings`: guarda driver, servidor, puerto, usuario, cifrado, remitente y datos de Mailgun/SES; los secretos (clave SMTP, secreto de Mailgun, claves de SES) se cifran con APP_KEY y la lectura los devuelve enmascarados. Un secreto vacío o enmascarado conserva el guardado. Se aplica en cada pedido sobre `config('mail')` y `config('services')`; sin tabla o sin base se usa el `.env`. Si APP_KEY cambia, el secreto no se descifra, se registra un aviso y hay que volver a cargarlo, sin romper la app.
- Staging nunca envía correo real: conserva `MAIL_DRIVER=log` aunque haya configuración guardada; la pantalla lo avisa.
- Solo administración total (`system.settings.manage`, exclusivo). Auditoría `mail_settings.updated` con los campos cambiados y sin valores secretos.
- Gate: `MailSettingsTest` (4 tests), 128 tests en total; smokes DOM aprobados; el recorrido de staging lee `/api/v1/mail/config`.
- Habilita la invitación por correo de E1, que queda como paso siguiente.
- Staging MySQL: deploy `d8a7e497-962f-4c46-8931-79763ffaa41a` (227dd5b) SUCCESS; `2026_10_21_000000_create_mail_settings` en 1.712 ms, predeploy completo y recorrido institucional con 303 checks (incluye la lectura de `/api/v1/mail/config` en Toda la institución y los tres niveles). Fila C probada en staging; no promovida.

## 19/10/2026 — Fila C, logo institucional, turno e inicio escolar en produccion

- Fila C: `produccion` 4b81620, deployment `3268f628-4197-4c7c-a662-774ac81f11ce` SUCCESS; migracion `2026_10_21_000000_create_mail_settings`, smoke de conteos aprobado, `ena:runtime` con sesiones en base y cola `sync`.
- Logo institucional (16944f1): `ena-logo.svg` es el del terciario (N.° 1831). Nuevo `ena-logo-institucional.svg` (buho, Escuela Nueva Austral, Primario · Secundario · Terciario, texto en trazos) en landing, login, asistente y logo predeterminado de `Company`. Se retiran `edificio.jpg`, su `og:image` y el anillo del login (d7622d6).
- Turno de division: lista Mañana/Tarde/Noche contra `morning|afternoon|evening`, mensaje en español; test en `StaffOnboardingTest`.
- Inicio escolar (303d9db): `GET /dashboard/school` reemplaza el tablero de Crater. Matricula vigente, divisiones y ocupacion, personal activo, cuotas vencidas, cobranza del mes y familias con deuda. Se borran `DashboardStats`, `DashboardChart` y `DashboardTable`; `GET /dashboard` legacy queda sin uso en la vista.
- Direccion de nivel (f74590a): entra al inicio si tiene `academic.enrollment.view` con alcance de nivel completo; sin `finance.view` el servidor devuelve `finance: null`. Preceptores y docentes siguen en Alumnos. Bootstrap informa `school_dashboard` por nivel. Regla pedida por Jesus: quien no tenga permisos de finanzas no ve nada de finanzas.
- Gate 134 tests/1461 aserciones (`SchoolDashboardTest`, 5 tests), smokes DOM aprobados. Staging: 303d9db `99378801-953c-4245-976c-f26aceba3203` y f74590a `5d70b308-bf01-45b2-92f2-f5b160f70904`, SUCCESS, 68 HTTP de roles y 303 institucionales aprobados.
- Produccion: 303d9db `462797e0-0fb5-4e6d-946a-6946c260c70f` SUCCESS (smoke de conteos aprobado, predeploy completo); f74590a promovido con autorizacion de Jesus.
