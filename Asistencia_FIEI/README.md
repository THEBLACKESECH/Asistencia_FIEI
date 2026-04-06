# Asistencia FIEI

Sistema web en PHP para control de asistencia con tres perfiles:

- `superadmin`: CRUD de escuelas, usuarios, alumnos, cursos, asignaciones y auditoría.
- `head`: consulta reportes y auditoría de sus escuelas asignadas.
- `teacher`: registra asistencia diaria de sus cursos y no puede modificarla luego.

## Tecnologías

- `PHP` con `PDO`
- `MySQL` o `MariaDB`
- `HTML`, `CSS` y `JavaScript` responsive

## Estructura

- `index.php`: login
- `setup.php`: creación inicial del primer superadmin
- `db/schema.sql`: estructura de base de datos y escuelas base
- `superadmin/`: panel de administración
- `head/`: panel de jefe de escuela
- `teacher/`: panel de docente

## Configuración rápida

1. Crea una base de datos en MySQL o MariaDB.
2. Importa `db/schema.sql`.
3. Ajusta la conexión en `config/app.php` o mediante variables:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
   - `APP_BASE_URL`
4. Abre `setup.php` para crear el primer superadmin.
5. Ingresa por `index.php`.

## Seguridad implementada

- contraseñas con `password_hash`
- validación por roles en backend
- protección CSRF en formularios
- sesiones seguras con cookies `HttpOnly` y `SameSite`
- consultas preparadas con `PDO`
- auditoría de cambios y accesos

## Reglas funcionales incluidas

- el docente solo visualiza cursos asignados
- la asistencia se registra una sola vez por curso y fecha
- la asistencia registrada queda bloqueada para edición
- el jefe solo consulta datos de sus escuelas
- las alertas se muestran cuando un alumno supera el `30%` de faltas

## Nota

En este entorno no tenía `php` instalado para ejecutar validaciones automáticas de sintaxis, así que dejé el proyecto preparado y documentado para que lo levantes en tu servidor PHP local.
