# Traspaso para Claude: aislamiento multiinstitucion

Fecha: 16 de agosto de 2026  
Rama de trabajo y produccion: `claude/web-app-migration-laf9yy`

## Objetivo

Impedir que un usuario autenticado de una institucion pueda leer o modificar
recursos academicos de otra empresa alterando headers o enviando un ID valido
en una ruta.

## Riesgo encontrado

El middleware `ValidateTenant` validaba correctamente el header `company`
contra `users.company_id`, y `BelongsToSchoolLevel` ya agregaba scopes por
empresa y nivel. Sin embargo, el grupo `api` ejecutaba
`SubstituteBindings` antes del middleware de tenant.

Eso dejaba esta secuencia:

1. Laravel recibia `/academic-years/{academicYear}`.
2. Resolvia el ID con `TenantContext` todavia vacio.
3. El scope global no podia aplicar la empresa.
4. Recién despues se validaba el tenant.
5. Un rol global podia superar la policy aunque el objeto perteneciera a otra
   institucion.

El problema no estaba solamente en el query. Era un problema de orden de
middleware combinado con una policy que confiaba en que el objeto ya venia
aislado.

## Correccion aplicada

### 1. Contexto antes del enlace de modelos

En `app/Http/Kernel.php`, el orden obligatorio queda:

```
Authenticate
ValidateTenant
SubstituteBindings
Authorize
```

Asi, cuando Laravel resuelve `{academicYear}`, los scopes de
`BelongsToSchoolLevel` ya conocen `company_id` y `school_level_id`.

No mover `ValidateTenant` debajo de `SubstituteBindings`.

### 2. Defensa adicional en la policy

`app/Policies/AcademicPolicy.php` compara siempre:

```php
(int) $resource->company_id === (int) $user->company_id
```

La comparacion se ejecuta antes de consultar permisos para:

- ciclos lectivos;
- divisiones;
- secciones de materia;
- cierre y administracion de esos recursos.

Esta capa tiene que conservarse aunque el middleware ya filtre. Protege rutas
nuevas, enlaces personalizados y llamados directos a policies.

### 3. Regresiones automatizadas

`tests/Unit/Access/TenantIsolationTest.php` verifica:

- autenticacion antes del tenant;
- tenant antes de `SubstituteBindings`;
- presencia del filtro `academic_years.company_id` en el query;
- rechazo de objetos de otra empresa antes de invocar `AccessManager`;
- cobertura de ciclos, divisiones y secciones.

Comando focalizado:

```bash
./vendor/bin/pest tests/Unit/Access/TenantIsolationTest.php
```

Comando completo:

```bash
./vendor/bin/pest
```

## Cambios previos que se conservan

El commit `6a1438e` ya habia agregado:

- validacion de empresa en roles y alcances;
- vigencia temporal de roles;
- scope global de empresa en `BelongsToSchoolLevel`;
- proteccion de estados del ciclo lectivo;
- migracion compatible de roles heredados;
- siembra RBAC durante el despliegue de Railway.

Las correcciones de este documento completan ese trabajo; no reemplazan esas
defensas.

## Invariantes para codigo futuro

1. Toda tabla academica con `company_id` tiene que filtrar por empresa.
2. Todo modelo academico enlazado desde una ruta tiene que resolverse con
   `TenantContext` ya establecido.
3. Toda policy de objeto tiene que comparar la empresa del usuario y la del
   recurso antes de evaluar permisos.
4. Los roles globales significan todos los niveles de una empresa, nunca todas
   las empresas.
5. Un header del cliente no es una identidad confiable: se valida contra el
   usuario autenticado.
6. Un test de autorizacion no debe usar solamente IDs de una empresa. Siempre
   tiene que incluir un recurso ajeno y esperar rechazo.
7. No usar `withoutGlobalScope('company')` dentro de controladores.

## Despliegue

Railway sigue la rama `claude/web-app-migration-laf9yy`. Los cambios fueron
escritos directamente en esa rama. La aplicacion del cambio de middleware no
requiere una migracion nueva; el despliegue reinicia el proceso PHP y toma el
nuevo orden.
