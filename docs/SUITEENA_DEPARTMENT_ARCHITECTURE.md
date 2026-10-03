# SuiteEna — Arquitectura por departamentos

Este documento define la estructura funcional de SuiteEna. La aplicación no debe crecer como una lista plana de pestañas: cada capacidad pertenece a un departamento, con permisos, alcance y responsabilidades explícitas.

## Reglas globales

- `total_admin` conserva autoridad transversal sobre toda la institución y todos los departamentos.
- Los demás usuarios trabajan únicamente dentro de los niveles institucionales y departamentos autorizados.
- Una persona puede existir como personal sin tener una cuenta de acceso.
- Una cuenta de acceso (`users`) representa autenticación y permisos, no identidad laboral.
- Las URLs existentes se preservan durante la reorganización para no romper compatibilidad; la navegación y los permisos se migran de forma incremental.
- Ninguna pantalla se considera terminada si no tiene backend, autorización, datos reales, pruebas, build y deploy verificado.

## 1. Gestión académica

Responsabilidad: estructura pedagógica y trayectoria del alumno.

Módulos:
- alumnos y legajos;
- familias/responsables;
- matrículas;
- ciclos lectivos;
- cursos, divisiones y materias;
- planes de estudio;
- secciones de cursada;
- asignación docente;
- evaluaciones y calificaciones;
- asistencia;
- promoción, cierre de ciclo y egreso;
- boletines, certificados y documentación académica.

## 2. Administración económica

Responsabilidad: ingresos, aranceles, cobros y gastos institucionales.

Módulos:
- conceptos y aranceles;
- cuotas y comprobantes;
- cobros;
- convenios/becas/bonificaciones;
- gastos;
- medios de pago;
- conciliación bancaria y proveedores de cobro;
- informes económicos;
- numeración y documentación económica.

Los sueldos NO tienen su fuente de verdad en `expenses`. Administración económica recibe el asiento/impacto económico de una liquidación aprobada desde RR.HH.

## 3. Recursos Humanos

Responsabilidad: padrón laboral, cargos, remuneraciones y vida laboral.

Fuente canónica actual:
- `staff_members`: identidad laboral;
- `staff_assignments`: cargos/asignaciones por nivel.

Módulos requeridos:
- personal;
- cargos y funciones;
- asignaciones por nivel;
- docentes y carga horaria;
- administrativos;
- auxiliares, limpieza, mantenimiento y maestranza;
- legajo laboral;
- contratos y condiciones;
- novedades mensuales;
- liquidaciones de haberes;
- conceptos remunerativos/no remunerativos;
- descuentos y retenciones;
- pagos de sueldo;
- recibos;
- historial salarial;
- informes de masa salarial y costo por nivel.

Cadena obligatoria:

`StaffMember → StaffAssignment → PayrollPeriod → PayrollSlip → PayrollPayment`

Una liquidación aprobada puede generar el impacto contable/económico correspondiente, pero no se reemplaza por un gasto genérico.

## 4. Campus y comunicación educativa

Responsabilidad: interacción pedagógica digital.

Módulos:
- aulas por sección;
- publicaciones y materiales;
- tareas;
- entregas;
- correcciones;
- avisos;
- clases en vivo;
- grabaciones;
- Moodle;
- BigBlueButton;
- notificaciones a alumnos y familias.

Laravel conserva la autoridad de usuarios, matrículas, cursos y permisos. Moodle/BBB son integraciones opcionales, no fuentes paralelas de identidad académica.

## 5. Sistemas y accesos

Responsabilidad: identidad digital, seguridad y operación técnica.

Módulos:
- usuarios;
- roles;
- permisos;
- alcance institucional/nivel;
- sesiones/dispositivos de acceso cuando corresponda;
- auditoría;
- respaldos;
- integraciones;
- secretos;
- correo;
- almacenamiento;
- webhooks y APIs;
- configuración técnica.

`Usuarios y accesos` debe responder quién puede entrar y qué puede hacer. No debe mezclarse con el padrón laboral de RR.HH.

## 6. Integraciones

Responsabilidad: conectar SuiteEna con servicios externos sin mezclar secretos entre niveles.

Proveedores iniciales:
- Moodle;
- BigBlueButton;
- correo SMTP/proveedor transaccional;
- almacenamiento;
- Ministerio/servicios oficiales cuando exista una integración verificada;
- Mercado Pago;
- BIND;
- APIs bancarias futuras;
- otros proveedores mediante adaptadores.

Toda credencial configurable debe pertenecer a un `school_level_id` concreto salvo infraestructura global explícitamente definida. El `total_admin` puede administrar todos los niveles.

Estado mínimo de una integración:
- deshabilitada;
- configurada;
- validada;
- activa;
- error;
- requiere rotación.

Los secretos se cifran, se muestran enmascarados, se rotan y se auditan sin registrar el valor real.

## 7. Informes y Dirección

Responsabilidad: visión transversal para toma de decisiones.

Módulos:
- indicadores académicos;
- morosidad y cobranzas;
- gastos;
- masa salarial;
- asistencia;
- matrícula;
- evolución y retención;
- actividad del campus;
- auditoría;
- comparación entre niveles para `total_admin` o roles institucionales autorizados.

## Orden de construcción inmediato

1. Navegación por departamentos sin romper URLs actuales.
2. RR.HH.: liquidaciones y pagos de sueldo sobre `staff_members`/`staff_assignments`.
3. Sistemas y accesos: administración visual de usuarios/roles/scopes.
4. Integraciones: pantalla única por nivel para Moodle, BBB, correo, almacenamiento y APIs.
5. Course sections + asignación docente.
6. Evaluaciones/notas/asistencia.
7. Campus, tareas y entregas.
8. Promoción y documentos oficiales.
9. Informes departamentales y tablero de Dirección.

## Regla de producto

No mostrar módulos heredados, ambiguos o incompletos como si fueran funcionalidades terminadas. Si una función aún no tiene una responsabilidad institucional clara, se renombra, se reubica, se marca como no operativa o se oculta hasta que tenga backend, permisos y flujo real.
