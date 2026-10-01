# SuiteEna — Etapa 1: especificacion para Claude Code

Integridad de datos, reubicacion academica y administracion visual de roles.
Corresponde a los pasos 1, 2 y 3 del orden recomendado en
`docs/SUITEENA_MASTER_PLAN.md`. Relevado contra el commit `489b940` de la rama
`claude/web-app-migration-laf9yy` el 30/09/2026.

Cada tarea sale completa (modelo, permiso, API, pantalla, test) y se considera
DONE solo cuando esta **Operativa**: mergeada, migrada, Railway en `success` y
con smoke test real. Es la regla de estado del plan maestro.

---

## Estado real relevado (no lo que dicen los documentos)

Cosas que el codigo muestra hoy y que cambian el plan:

1. **No hay ninguna baja logica.** Ningun modelo usa `SoftDeletes`. Facturas,
   presupuestos, cobros, gastos, items, clientes y usuarios se borran fisicamente
   (`Invoice::destroy($ids)` en `InvoicesController::delete`, `User::deleteCustomers`
   para clientes, que en Crater son `User`, y lo mismo en los otros `POST /*/delete`). La unica excepcion bien resuelta es
   `StudentsController::destroy`, que responde 409.
2. **La auditoria cubre solo la estructura academica.** El trait `Auditable` esta
   en `AcademicYear`, `GradeLevel`, `Division`, `Subject` y `Enrollment`. No esta en
   ningun modelo economico, ni en `Student`, ni en RR.HH. La reubicacion de alumnos
   (`StudentRelocationController`) no deja registro en la bitacora.
3. **Los registros sin nivel solo se ven en "Toda la institucion".**
   `ValidateTenant` deja `school_level_id` en null solo para el total admin sin
   header de nivel; con un nivel elegido, el scope `school_level` filtra por
   igualdad estricta, asi que todo registro con `school_level_id` NULL desaparece.
   Es la explicacion mas probable del presupuesto del 16/08: **hay que confirmarlo
   con datos**, no asumirlo.
4. **Los parches 2 y 3 de la bitacora nunca entraron.** No existe
   `ena:nivel-huerfanos`, ni `$levelScopeIncludesNull`, ni el permiso
   `students.import`, ni `verificar-importacion.php`. La importacion del padron
   no esta en el codigo.
5. **Los backups no existen en la practica.** `config/backup.php` (spatie) apunta
   al disco `local`, que vive dentro del contenedor y se pierde en cada deploy.
   `Console\Kernel` no agenda ningun backup, y ademas no hay ningun proceso que
   corra `schedule:run`.
6. **`php artisan reset:app` hace `migrate:fresh --seed`** y no distingue entorno
   mas alla de `confirmToProceed` (que con `--force` se saltea). En produccion
   borra todo.
7. **No hay API ni pantalla para asignar roles.** Las tablas RBAC, el
   `AccessManager` y `canGrantRole` existen; `views/users/` solo tiene `Index` y
   `Create`, y la UI todavia decide por `currentUser.role == 'super admin'`.
8. **El servidor de produccion es `php artisan serve`.** `railway.toml` tiene un
   `startCommand` que pisa el `start.sh` (nginx + php-fpm) del Dockerfile.

---

## Etapa 1.0 — Prerrequisitos de plataforma (antes de tocar datos)

Chicos, pero sin esto el resto se trabaja a ciegas.

| # | Tarea | Criterio de aceptacion |
|---|-------|------------------------|
| 0.1 | Sacar `startCommand` de `railway.toml` para que arranque `start.sh`. | Los logs de Railway muestran nginx, no "Development Server". `/ping` 200. |
| 0.2 | `reset:app` rechaza correr si `app()->environment('production')`, aun con `--force`. | Test: en entorno production el comando sale con codigo 1 y no llama a `migrate:fresh`. |
| 0.3 | Correo real: SMTP en variables de Railway (`MAIL_DRIVER=smtp`, host, puerto, usuario, clave, `MAIL_FROM_*`). Hoy esta en `MAIL_DRIVER=log` a proposito, para recuperar la clave del superusuario. | Pedir recuperacion de clave llega a la casilla real. |
| 0.4 | Verificar `APP_DEBUG=false` y `APP_ENV=production` en Railway. | Un 500 forzado no muestra stack trace. |
| 0.5 | Cerrar el TCP proxy publico de MySQL si nadie lo usa desde afuera. | `list-tcp-proxies` del servicio MySQL vacio. |

---

## Etapa 1.1 — Integridad y recuperabilidad (Bloque 0)

### 1.1.1 Diagnostico de nivel (solo lectura)

Comando `php artisan ena:auditar-niveles {--company=} {--json}`.

- Recorre los 13 modelos con `BelongsToSchoolLevel` (StudyPlan, CourseSection,
  GradeLevel, Estimate, AcademicYear, Student, Division, Item, Enrollment,
  Payment, Invoice, Expense, Subject) usando `withoutGlobalScopes()`.
- Por empresa reporta: total, con nivel valido, `school_level_id` NULL, nivel
  inexistente, y nivel de otra empresa.
- Para documentos economicos, sugiere el nivel deducible (por el alumno o cliente
  vinculado, o por el nivel del documento de origen en Estimate -> Invoice ->
  Payment) y marca los que no tienen deduccion unica.
- **No escribe nada.** Test que lo garantice: conteo de queries de escritura = 0.

Primera ejecucion en produccion: correrla y registrar el resultado en la
bitacora del proyecto. Con eso se cierra (o se descarta) la hipotesis del
presupuesto del 16/08.

#### Primera ejecucion en produccion — 01/10/2026

**OPERATIVA.** El comando fue desplegado, probado como solo lectura y ejecutado
contra produccion. Resultado completo: `docs/audits/2026-10-01-level-audit.json`.

Hallazgo: existe un unico presupuesto, `PRE-000001` (id 1), fechado
`2026-08-16`, con `school_level_id = NULL`. No tiene una deduccion unica de
nivel (`sin_evidencia`), por lo que no se modifica automaticamente.

La hipotesis del presupuesto del 16/08 queda **confirmada**: el registro existe,
pero desaparece de las vistas con nivel seleccionado porque el scope exige
igualdad estricta de `school_level_id`. No se detectaron niveles inexistentes ni
niveles de otra empresa en los 13 modelos auditados.

### 1.1.2 Reconciliacion

Comando `php artisan ena:reubicar-nivel {modelo} {ids*} --nivel= {--aplicar}`.

- Sin `--aplicar` es simulacion: muestra antes/despues y no escribe.
- Con `--aplicar`: una transaccion, `lockForUpdate`, verifica que el nivel destino
  sea de la misma empresa, y escribe una entrada de auditoria por registro
  (`event = 'level_reassigned'`, valor anterior y nuevo).
- Pantalla para total admin dentro de la vista "Toda la institucion": listado
  "Registros sin nivel" con accion de reubicar uno o varios, que usa el mismo
  servicio (`NivelReconciliador`), no logica duplicada.
- Permiso nuevo `data.reconcile`, solo total admin, en `alwaysAudited()`.

### 1.1.3 Baja logica en lugar de borrado

- Agregar `SoftDeletes` (migracion `deleted_at`) a Invoice, Estimate, Payment,
  Expense, Item y User (los clientes son `User`; no hay modelo `Customer`).
- Los `POST /*/delete` pasan a ser baja logica. **Antes de cambiar, leer cada
  `delete*` del modelo**: Crater recalcula saldos de cliente y estados de factura
  al borrar cobros (`Payment::deletePayments` devuelve el monto al `due_amount` de
  la factura y recalcula `paid_status`); ese efecto tiene que seguir ocurriendo
  igual con la baja logica y revertirse en la restauracion.
- Endpoint y pantalla de "Papelera" para total admin con restaurar. El borrado
  definitivo no se expone.
- Invariante a testear: una factura con cobros no se puede dar de baja sin dar
  de baja (o anular) primero sus cobros, y la numeracion institucional nunca
  reutiliza un numero dado de baja.

### 1.1.4 Auditoria economica y de alumnos

- `Auditable` en Invoice, Estimate, Payment, Expense, Student, StaffMember,
  PayrollSlip y PayrollPayment.
- Revisar que `registrarEnBitacora` no guarde PII innecesaria: para Student,
  registrar ids y campos cambiados, no DNI ni fecha de nacimiento completos.
- `StudentRelocationController::relocate` escribe un evento explicito
  `student_relocated` con origen, destino, motivo y actor.

### 1.1.5 Backups reales

- Backup diario de MySQL con `mysqldump` a un bucket S3 compatible (Railway
  Buckets sirve) y retencion 7 diarios / 4 semanales / 6 mensuales.
- Corre como **servicio cron aparte en Railway** (mismo repo, `cronSchedule`,
  start command `php artisan ena:backup`), no dentro del proceso web.
- Activar tambien los backups nativos del volumen de MySQL en Railway, como
  segunda linea.
- Prueba de restauracion: un entorno `restore-test` en Railway donde se restaura
  el ultimo dump y se corre `ena:auditar-niveles`. Mensual, con resultado anotado.

### 1.1.6 Smoke test post-deploy

Comando `php artisan ena:smoke` que agrega al `preDeployCommand` despues de
`migrate`: conteos por empresa y nivel de alumnos, matriculas, facturas,
presupuestos, cobros y personal. Guarda la foto en una tabla `deploy_snapshots`
y falla (codigo 1, aborta el deploy) si algun conteo baja respecto de la foto
anterior sin una migracion de datos declarada.

---

## Etapa 1.2 — Reubicacion academica (Bloque 3)

La reubicacion ya existe (`StudentRelocationController`, con transaccion y
bloqueos). Falta:

1. **Separar dos operaciones** que hoy son una:
   - *Correccion de carga*: el alumno estaba mal cargado. Corrige la matricula
     vigente, sin crear historial academico. Requiere motivo.
   - *Pase real*: cambio de curso, division o nivel. Cierra la matricula vigente
     con fecha y estado `transferred_out` (ya existe `Enrollment::STATUS_TRANSFERRED_OUT`), abre la nueva, conserva el historial.
2. **Historial visible**: en el legajo, linea de tiempo de matriculas por ciclo.
3. **Campos legacy** (`level`, `grade`, `division`, `school_year`): dejar de
   escribirlos desde la UI; mantenerlos solo como lectura derivada de la
   matricula vigente hasta retirarlos.
4. **Unificar responsables**: `family_member_student` vs `guardian_student`.
   Decidir cual queda como fuente de verdad (propuesta: `family_member_student`)
   y migrar sin perder vinculos.
5. **Tests**: hermanos en distintos niveles, pase Primario -> Secundario,
   correccion vs pase, cupo de division, y que un pase no deje matriculas
   historicas apuntando a un alumno invisible.

---

## Etapa 1.3 — Usuarios, roles y alcances en pantalla (Bloque 1)

### API

- `GET /api/v1/roles` — roles con jerarquia y alcance posible.
- `GET /api/v1/users/{user}/role-assignments` — asignaciones vigentes e historicas.
- `POST /api/v1/users/{user}/role-assignments` — rol, nivel opcional, division
  opcional, `starts_at`, `ends_at`. Pasa por `AccessManager::canGrantRole`.
- `POST /api/v1/role-assignments/{id}/revoke` — revoca con fecha; no borra.
- `GET /api/v1/users/{user}/effective-permissions` — permisos efectivos y de
  donde sale cada uno.
- Todo con `permission:` y `tenant`, y auditado.

### Reglas que los tests tienen que probar

- Nadie asigna un rol de jerarquia igual o superior a la propia.
- Nadie asigna fuera de su alcance (una direccion de Primario no asigna en
  Secundario).
- Nadie modifica, degrada, revoca ni da de baja a un total admin, salvo otro
  total admin, y nunca puede quedar menos de uno vigente.
- Un rol vencido no otorga nada.
- Dar de baja un usuario lo desactiva (sin borrar) y revoca sus sesiones y tokens.

### Pantalla

Siguiendo el patron del modulo de configuraciones (una pantalla por
responsabilidad, guardado propio): "Usuarios y roles" con listado, ficha del
usuario, pestania de asignaciones (alta con selector Rol -> Nivel -> Division y
fechas) y pestania de permisos efectivos. Quitar los chequeos
`role == 'super admin'` de la UI y reemplazarlos por los permisos que llegan en
`bootstrap`.

---

## Orden de trabajo sugerido para Claude Code

Un PR por fila, en este orden. Cada uno: especificacion corta en el PR, tests
positivos y negativos, `build/frontend` regenerado si toca Vue, deploy y smoke.

1. 0.1 + 0.2 (plataforma y `reset:app`).
2. [x] 1.1.1 diagnostico, ejecutado en produccion el 01/10/2026.
3. 1.1.4 auditoria (para que lo que sigue ya quede registrado).
4. 1.1.2 reconciliacion.
5. 1.1.3 baja logica.
6. 1.1.5 backups y 1.1.6 smoke.
7. 1.2 reubicacion.
8. 1.3 roles.

## Prompt de arranque para Claude Code

> Lee `CLAUDE.md`, `docs/SUITEENA_MASTER_PLAN.md` y
> `docs/SUITEENA_ETAPA_1.md`. Trabaja la fila 1 del "Orden de trabajo sugerido":
> saca el `startCommand` de `railway.toml` y hace que `reset:app` se niegue a
> correr en produccion, con su test. Antes de escribir codigo, decime que vas a
> cambiar y que riesgo ves. Commits en espaniol rioplatense, push a
> `claude/web-app-migration-laf9yy`.

Para las filas siguientes, mismo prompt cambiando el numero de fila.

## Decisiones que necesito de la escuela

- Fuente de verdad de responsables: `family_member_student` o `guardian_student`.
- Quienes son total admin (el plan dice dos personas, no mas de tres).
- Si se retoma la importacion del padron (el parche 3 nunca entro) y en que
  momento.
- Que casilla envia los correos de la app.
