# Configuración sensible e integraciones por nivel

## Regla obligatoria

Toda integración operativa que use claves, tokens, credenciales, endpoints privados o webhooks debe pertenecer a un nivel institucional concreto (`school_level_id`). Esto incluye, entre otras, integraciones con Ministerio, Moodle, BigBlueButton, correo, almacenamiento, APIs externas y futuros proveedores.

## Jerarquía

- `total_admin` / developer: lectura y administración total sobre todos los niveles y empresas autorizadas por la jerarquía global.
- roles institucionales globales: solo lo que sus permisos explícitos permitan.
- roles de nivel: solo configuraciones de su propio `school_level_id`.
- roles inferiores nunca pueden modificar credenciales marcadas como `requires_total_admin`.

## Secretos

- El valor real se guarda cifrado.
- La UI solo recibe `masked_preview`.
- Nunca se devuelve el secreto completo por API después de guardarlo.
- Se registra quién actualizó la configuración y cuándo rotó.
- Se admite expiración y rotación.
- Los cambios sensibles deben auditarse sin registrar el secreto.

## Migración de configuraciones legacy

Una configuración existente sin `school_level_id` no se borra ni se copia automáticamente a todos los niveles. Se conserva como `legacy/unassigned` hasta que un `total_admin` la clasifique explícitamente. Esto evita propagar una credencial a un nivel equivocado.

## Fuente de verdad

`secure_settings` será la fuente operativa para secretos configurables desde la aplicación. `.env` queda reservado para secretos de infraestructura/plataforma que no deben administrarse desde la UI.

## Integraciones relacionadas

`external_mappings` y `sync_operations` también deben llevar `school_level_id` para impedir que una sincronización de Primaria termine afectando recursos de Secundaria o Terciaria.
