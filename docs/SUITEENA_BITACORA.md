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
