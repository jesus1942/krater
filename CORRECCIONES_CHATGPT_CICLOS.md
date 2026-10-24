# Correcciones sobre `pantallaciclosyestructura.patch`

Se aplicaron los dos commits de Claude y se endurecieron antes de produccion.

## Correcciones realizadas
- `academic_year_id`: limitado a empresa y nivel actuales.
- `grade_level_id`: limitado a empresa y nivel actuales.
- `head_teacher_id`: limitado a la empresa actual.
- Unicidad de `Division.name`: incluye empresa y nivel, ademas de ciclo y curso.
- `promotes_to_id`: limitado a empresa y nivel actuales.
- Unicidad de `GradeLevel.position`: usa `TenantContext`, no el header crudo.
- Se conserva el filtro por empresa de `scopedDivisionIds()` agregado por Claude.
- `scopedDivisionIds()` normaliza los IDs a `int`; SQLite puede devolver `pluck()` como strings y eso rompia comparaciones estrictas del banco de pruebas. La normalizacion deja el contrato del metodo estable entre motores.

## Validacion
- Dependencias instaladas con PHP 7.4, compatible con el lock legacy del proyecto.
- `php -l` sobre controladores/request nuevos y `AccessManager.php`.
- Chequeo estatico para impedir `exists:` crudos en estas referencias academicas.
- `php verificar-acceso.php`, incluyendo alcance fino y aislamiento entre instituciones.

## Regla para cambios futuros
En estructura academica multi-tenant, usar `Rule::exists/unique(...)->where(...)` con `company_id` y, cuando corresponda, `school_level_id` obtenidos desde `TenantContext`.
