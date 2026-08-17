# SuiteEna — Plan maestro técnico y operativo

Este documento es la referencia oficial para ordenar el desarrollo de SuiteEna. Ningún módulo se considera terminado porque exista una tabla, una pantalla o porque compile.

## Regla de estado

Cada capacidad debe pasar por cinco estados:

1. **Diseñado** — reglas de negocio, modelo de datos, permisos e invariantes definidos.
2. **Implementado** — backend + frontend funcionales.
3. **Protegido** — RBAC, aislamiento empresa/nivel, validaciones y rutas seguras.
4. **Probado** — regresiones positivas, negativas, escalación de privilegios e integridad.
5. **Operativo** — merge en producción, migraciones ejecutadas, Railway `success` y smoke test real.

Solo **Operativo** equivale a DONE.

---

# BLOQUE 0 — Integridad, continuidad y recuperabilidad de datos

**Prioridad absoluta. Bloquea el crecimiento funcional si no está controlado.**

## Objetivos

- Ningún deploy debe borrar, sobrescribir, ocultar o volver inaccesibles datos históricos sin una migración explícita y revisada.
- Los cambios de `company_id`, `school_level_id`, curso, división o matrícula deben ser transaccionales y trazables.
- Los registros económicos y académicos no se borran para corregir una clasificación; se reubican o se cambian de estado preservando historial.
- Toda migración que reclasifique datos debe tener conteo antes/después y criterio determinista.
- Backups de producción deben ser verificables y restaurables.

## Tareas

- [ ] Auditoría de datos potencialmente ocultos por `school_level_id` nulo o incorrecto en estimates, invoices, payments, expenses, items y students.
- [ ] Herramienta de reconciliación para registros globales/huérfanos por nivel.
- [ ] Backups automáticos y política de retención.
- [ ] Prueba periódica de restauración en ambiente aislado.
- [ ] Soft delete o baja lógica para entidades donde borrar físicamente sea riesgoso.
- [ ] Bloqueo de deletes destructivos en datos académicos/económicos con historial.
- [ ] Smoke test post-deploy con conteos críticos por empresa/nivel.
- [ ] Registro de migraciones de datos con resultado y cantidad afectada.
- [ ] Revisión del incidente: presupuesto cargado el 16/08/2026 que dejó de aparecer después de activar aislamiento por nivel.

## Invariantes

- Un presupuesto existente nunca debe desaparecer por cambiar el nivel activo; si pertenece a otro nivel debe existir una vista de administración total o una herramienta de reubicación.
- Un alumno no se elimina para cambiarlo de curso, división o nivel.
- Una corrección de clasificación no crea una persona/alumno/documento económico duplicado.

---

# BLOQUE 1 — Tenant, jerarquía y RBAC

## Invariantes

- `company_id` = institución.
- `school_level_id` = nivel interno (Primario / Secundario / Terciario).
- Developer / `total_admin` conserva jerarquía total transversal a empresas, niveles y módulos.
- Roles inferiores nunca pueden modificar, degradar ni eliminar al total admin.
- Roles globales institucionales pueden cruzar niveles según permisos.
- Roles de nivel/división/sección no pueden escapar de su scope.

## Tareas

- [x] AccessManager compatible con legacy super admin.
- [x] Total admin transversal a empresas en ValidateTenant.
- [x] Aislamiento transversal básico de módulos económicos.
- [x] Informes protegidos por sesión, permiso y tenant.
- [ ] Administración visual completa de usuarios, roles y scopes.
- [ ] Impedir asignación de roles fuera del scope del actor.
- [ ] Unificar RBAC legacy y nativo.
- [ ] Tests de escalación de privilegios por cada rol.
- [ ] Panel de auditoría de permisos efectivos por usuario.

---

# BLOQUE 2 — Estructura académica canónica

## Fuente de verdad

- Nivel: `school_levels`
- Ciclo lectivo: `academic_years`
- Curso/año: `grade_levels`
- División: `divisions`
- Materias/plan: `subjects` + `study_plans`
- Grupo concreto de materia: `course_sections`

## Tareas

- [x] Ciclos lectivos.
- [x] Cursos/años.
- [x] Divisiones.
- [x] Materias.
- [x] Pantalla Estructura académica.
- [ ] Planes de estudio completos y versionados en UI.
- [ ] Correlatividades terciarias en UI.
- [ ] Course sections por división + materia + ciclo.
- [ ] Asignación docente a course sections.
- [ ] Validaciones para impedir combinaciones curso/nivel inválidas.

---

# BLOQUE 3 — Alumnos, familias y matrículas

## Invariantes

- El alumno es una identidad única por institución.
- El curso/división/nivel actual provienen de estructura académica y matrícula, no de texto libre.
- Familiares son institucionales y pueden vincularse a alumnos de distintos niveles.
- Cambiar de curso/nivel no borra alumno ni familia.
- La matrícula conserva historial anual.

## Tareas

- [x] Legajo de alumnos.
- [x] Familiares múltiples con reutilización por DNI.
- [x] Matrículas básicas.
- [x] Desplegables Ciclo → Curso → División en alta/edición.
- [ ] **Reubicación académica de alumno** entre niveles/curso/división sin borrar legajo.
- [ ] Sincronizar automáticamente matrícula con ubicación seleccionada.
- [ ] Separar “corrección de carga” de “transferencia académica real”.
- [ ] Historial visible de ubicaciones/matrículas por ciclo.
- [ ] Retirar gradualmente campos legacy `level`, `grade`, `division`, `school_year` como fuente de verdad.
- [ ] Unificar `family_member_student` y permisos de portal hoy representados también por `guardian_student`.
- [ ] Tests de hermanos, responsables, reubicación y cross-level.

---

# BLOQUE 4 — Administración económica

## Alcance

- Conceptos y aranceles
- Planes y convenios
- Cuotas y comprobantes
- Cobros
- Gastos
- Numeración institucional
- Informes

## Invariantes

- Documentos económicos se aíslan por empresa y nivel cuando corresponde.
- La numeración es institucional por empresa y no se duplica entre niveles.
- La persona/familia puede ser institucional; el documento económico pertenece a un nivel cuando fue generado en ese contexto.
- Ningún documento existente queda inaccesible por agregar aislamiento posteriormente.

## Tareas

- [x] Items por nivel.
- [x] Estimates / invoices / payments / expenses con tenant activo.
- [x] Controladores auxiliares con tenant.
- [x] Dashboard económico por nivel.
- [x] Numeración por empresa transversal a niveles.
- [x] Informes seguros y aislados.
- [ ] Auditoría y reconciliación de registros previos con `school_level_id` nulo.
- [ ] Vista total-admin “Todos los niveles” para recuperación/reclasificación.
- [ ] URLs firmadas para PDFs de factura/presupuesto/cobro.
- [ ] Revisar deletes físicos y reemplazar por estados/soft delete donde corresponda.
- [ ] Integridad referencial y pruebas de no pérdida en conversiones Estimate → Invoice.
- [ ] Auditoría económica de cambios sensibles.

---

# BLOQUE 4B — Recursos Humanos

## Fuente de verdad

- Personal: `staff_members`
- Cargos y asignaciones: `staff_assignments`
- Períodos: `payroll_periods`
- Liquidaciones: `payroll_slips`
- Pagos de sueldo: `payroll_payments`

## Tareas

- [x] Padrón laboral separado de usuarios.
- [x] Cargos por nivel sin borrar historial.
- [x] Períodos mensuales por nivel.
- [x] Liquidación interna bruto/descuentos/neto.
- [x] Aprobación antes del pago.
- [x] Pagos parciales/totales y reversión sin delete.
- [ ] Conceptos salariales versionados y novedades mensuales.
- [ ] Recibo salarial imprimible.
- [ ] Asiento/impacto económico de liquidaciones aprobadas.
- [ ] Reglas legales/impositivas argentinas solo con normativa vigente verificada.

---

# BLOQUE 5 — Evaluaciones, calificaciones y asistencia

La base de datos existe parcialmente; falta convertirla en producto.

## Tareas

- [ ] Escalas de calificación por nivel.
- [ ] Evaluaciones por course section.
- [ ] Carga de notas.
- [ ] Estados borrador/publicado.
- [ ] Revisiones append-only de notas con motivo y autorización.
- [ ] Notas por período.
- [ ] Nota final.
- [ ] Materias pendientes.
- [ ] Asistencia diaria/por sección según nivel.
- [ ] Permisos docente/preceptor/director.
- [ ] Portal alumno/familia para consulta publicada.
- [ ] Tests de cierre de períodos y edición posterior.

---

# BLOQUE 6 — Campus / aula virtual

## Arquitectura

- Laravel sigue siendo fuente de verdad de usuarios, cursos, permisos, matrículas y calificaciones.
- TypeScript/Node puede usarse para realtime, presencia, eventos y colaboración cuando aporte una ventaja concreta.
- Videoconferencia/pantalla compartida debe apoyarse en BigBlueButton/WebRTC; no construir una plataforma de video desde cero.

## Tareas

- [ ] Campus por `course_section`.
- [ ] Materiales y publicaciones.
- [ ] Tareas.
- [ ] Entregas.
- [ ] Corrección y devolución.
- [ ] Comentarios/avisos.
- [ ] Presencia/realtime.
- [ ] Clases en vivo.
- [ ] Compartir pantalla.
- [ ] Presentaciones grupales.
- [ ] Grabaciones y consentimiento.
- [ ] Integración Moodle/BBB opcional mediante external mappings.

---

# BLOQUE 7 — Promoción, cierre de ciclo y egreso

## Invariantes

- Nunca ejecutar promoción irreversible sin dry-run y vista previa.
- Ciclo cerrado no se modifica por operaciones normales.
- Reglas versionadas y con referencia normativa.
- Excepciones requieren autorización y quedan auditadas.

## Tareas

- [ ] PromotionEngine.
- [ ] Rule sets por nivel.
- [ ] Dry-run con resultados por alumno.
- [ ] Aprobación previa a ejecución.
- [ ] Promovido / promovido con pendientes / retenido / egresado.
- [ ] Creación de matrícula del ciclo siguiente.
- [ ] Ventana de reversión controlada.
- [ ] Cierre definitivo del ciclo.
- [ ] Tests masivos de promoción.

---

# BLOQUE 8 — Boletines, certificados y documentación oficial

## Tareas

- [ ] Boletín interno configurable.
- [ ] Certificados.
- [ ] Constancia de alumno regular.
- [ ] Analítico/transcript interno.
- [ ] Archivo de documento oficial emitido externamente.
- [ ] Versionado y huella/verificación de PDFs.
- [ ] Investigación específica de formatos/integraciones vigentes del Ministerio de Educación de Chubut antes de afirmar compatibilidad oficial.
- [ ] Flujo de importación/vinculación de documentos oficiales.

---

# BLOQUE 9 — Configuración sensible e integraciones

## Tareas

- [ ] `secure_settings` como fuente operativa real.
- [ ] Valores cifrados y previews enmascarados.
- [ ] APIs/tokens/correos bajo Configuración.
- [ ] Cambios sensibles restringidos a total admin o aprobación definida.
- [ ] Rotación y expiración de secretos.
- [ ] Dejar de usar `.env` editable como mecanismo de configuración de usuario.
- [ ] Integraciones Moodle / BBB / correo / almacenamiento.
- [ ] Auditoría de cambios de configuración.

---

# BLOQUE 10 — Auditoría, observabilidad y seguridad

## Tareas

- [x] Bitácora inicial append-only a nivel de aplicación.
- [ ] Expandir auditoría a operaciones académicas y económicas críticas sin guardar PII/secrets innecesarios.
- [ ] Correlation/request ID.
- [ ] Registro de deploy y migraciones.
- [ ] Alertas de errores 5xx.
- [ ] Métricas de jobs/colas.
- [ ] Rate limiting en endpoints sensibles.
- [ ] CSRF/session/API review.
- [ ] Revisión de URLs públicas y recursos por hash.
- [ ] Pruebas de IDOR/tenant escape.
- [ ] Pruebas de permisos negativas por rol.

---

# BLOQUE 11 — Testing y release engineering

## Pipeline obligatorio de una feature

**Especificación → threat model → schema → backend → autorización → frontend → tests → build → diff review → PR → migration → deploy → smoke test**

## Gate mínimo antes de merge

- PHP syntax.
- Tests específicos de la feature.
- Tests de aislamiento empresa/nivel si toca datos tenantizados.
- Tests de permiso si toca rutas protegidas.
- Build Vue si toca frontend.
- Regeneración `build/frontend` porque Railway no compila Vue actualmente.
- Diff revisado sin archivos temporales.

## Gate mínimo después de merge

- Railway apunta al SHA exacto.
- `success` de deployment.
- Migraciones completadas.
- Endpoint `/ping` saludable.
- Smoke test del flujo modificado.
- Conteos críticos antes/después cuando hubo migración de datos.

## Deuda conocida

- La suite Feature heredada no levanta limpiamente en SQLite por migraciones antiguas.
- PHP 7.4 / Laravel 8 / tooling frontend son legacy y deben modernizarse de forma planificada, no mezclado con features críticas.

---

# BLOQUE 12 — Modernización técnica

No es prioridad por encima de integridad y producto, pero debe planificarse.

- [ ] Matriz de compatibilidad para actualización PHP/Laravel.
- [ ] Actualizar test stack.
- [ ] Modernizar build frontend.
- [ ] Evaluar separación de servicios realtime TypeScript/Node.
- [ ] CI reproducible sin workflows temporales.
- [ ] Staging separado de producción.
- [ ] Migraciones y smoke tests automatizados en staging.

---

# Orden de ejecución recomendado

1. **Integridad de datos y recuperación** — incluyendo presupuesto desaparecido.
2. **Reubicación académica sin borrar alumnos** — caso Felipe.
3. **RBAC visual y jerarquía completa**.
4. **Reconciliación económica por niveles + URLs PDF seguras**.
5. **Course sections + asignación docente**.
6. **Campus base + tareas/entregas**.
7. **Evaluaciones/notas/asistencia**.
8. **Portal familia/alumno**.
9. **PromotionEngine + cierre de ciclo**.
10. **Boletines/documentos**.
11. **Integraciones y configuración segura**.
12. **Hardening, carga, modernización y staging**.

---

# Regla de dirección técnica

- No se introduce una tabla nueva si ya existe una fuente de verdad equivalente.
- No se duplica identidad de alumno, familiar, matrícula o documento para resolver un problema de UI.
- No se borra información histórica para “acomodar” una nueva estructura.
- No se mezcla una refactorización de plataforma con una feature crítica salvo necesidad demostrable.
- TypeScript/Node se incorpora solo donde resuelva realtime/concurrencia/integración mejor que Laravel; Laravel conserva la autoridad de negocio.
- Todo incidente de pérdida, invisibilidad o corrupción de datos tiene prioridad sobre nuevas features.

Este documento debe actualizarse en cada PR significativo para reflejar el estado real del producto.