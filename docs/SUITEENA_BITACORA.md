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
