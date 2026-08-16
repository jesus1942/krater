# Aislamiento de conceptos y aranceles por nivel

Incidente detectado: un concepto creado con Primaria activa aparecía también en Secundario y Terciario.

## Causa

La columna `items.school_level_id` y el trait `BelongsToSchoolLevel` ya existían, pero el controlador heredado de Artículos no inicializaba `TenantContext`. Por eso los conceptos nuevos podían quedar con `school_level_id = NULL`, lo que los hacía visibles en todos los niveles.

## Corrección

- `ItemsController` exige middleware `tenant` para todas sus acciones.
- El scope global existente de `Item` filtra por el nivel activo y el evento de creación fija automáticamente `school_level_id`.
- El único dato histórico cuyo origen fue confirmado, `Cuota nivel`, se reasigna a `primary`. Otros artículos legacy con nivel nulo se dejan sin modificar porque no hay información suficiente para inferir su procedencia.

## Regresión

`tests/Isolation/ItemSchoolLevelIsolationTest.php` verifica:

1. que el controlador inicializa tenant;
2. que un concepto creado bajo Primaria recibe ese nivel y no aparece bajo Terciario;
3. que el backfill histórico solo afecta `Cuota nivel`.

El test es autocontenido porque el banco Feature heredado del proyecto no puede reconstruir actualmente una base SQLite desde cero debido a una migración antigua que consulta `payment_methods` antes de su creación. Esa deuda se debe resolver como trabajo separado.
