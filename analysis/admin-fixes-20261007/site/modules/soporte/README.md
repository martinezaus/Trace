# Configuraciones — Panel de administración de parámetros

Módulo independiente para ver/editar `systemnegocio`, `systemsmtp` y
`systemusage`, con login de dos niveles (admin / master) contra la misma
tabla `systemusage`, y un visor de logs de solo lectura.

## 1. Instalación

1. Copiar la carpeta `configuraciones/` a la raíz del sistema (al mismo
   nivel que la carpeta `include/`), o donde prefieras — solo ajustá
   `LOG_DIR_RAW` en `includes/config.php` si cambiás la ubicación.
2. Instalar RedBeanORM:
   ```bash
   cd configuraciones
   composer require gabordemooij/redbean
   ```
3. Editar `includes/config.php`:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `LOG_DIR_RAW` si la ruta de logs no es `../include/logs` relativa a esta carpeta
4. Publicar la carpeta detrás de HTTPS. El link "configuraciones" del
   sistema principal debe apuntar a `configuraciones/index.php` (o a
   `configuraciones/` si el servidor sirve `index.php` por defecto).
5. Verificar permisos de archivo: PHP necesita solo lectura sobre
   `include/logs` y lectura/escritura sobre la base de datos.

No hace falta tocar el esquema de las tablas para que funcione: el
módulo se adapta al ancho actual de columnas (`R::freeze(true)` evita
que RedBean intente modificar el esquema).

## 2. Cómo funciona el login

- Usuario fijo: `root` (no editable desde el formulario).
- Se compara la clave ingresada contra `systemusage.pwmaster` primero
  y luego contra `systemusage.password`.
  - Coincide con `pwmaster` → rol **master** (accede a Negocio, SMTP
    y Sistema/licencia).
  - Coincide con `password` → rol **admin** (accede a Negocio y SMTP).
  - No coincide con ninguna → error genérico "Usuario o clave
    incorrectos" (no se revela cuál campo falló).
- Se asume una sola fila de configuración en `systemusage` (la
  primera que RedBean encuentre). Si el sistema maneja varios
  registros, ajustar la consulta en `index.php`.

## 3. Seguridad incluida

- **CSRF**: token por sesión, verificado en cada POST.
- **Sesión**: cookies `HttpOnly`, `SameSite=Strict`, `Secure` si hay
  HTTPS, expiración por inactividad (`SESSION_TIMEOUT`), regeneración
  de ID al iniciar sesión.
- **Fuerza bruta**: bloqueo temporal tras `MAX_LOGIN_ATTEMPTS` intentos
  fallidos, con retardo en cada intento. Para producción de alto
  tráfico, sumar además un límite a nivel de servidor web / fail2ban.
- **Autorización server-side**: cada página y cada endpoint de la API
  vuelve a validar el rol con `require_role()`; el menú solo oculta
  visualmente lo que el usuario no ve, la restricción real está en el
  backend.
- **SQL**: todo el acceso a datos pasa por RedBeanORM (consultas
  parametrizadas), no hay concatenación de SQL en ningún endpoint.
- **Logs**: solo se listan/leen archivos `.log`/`.txt` dentro de
  `LOG_DIR`, usando `realpath()` + comparación de prefijo para impedir
  path traversal (`../../etc/passwd`, etc.). La lectura es por
  "cola" del archivo (tail), evitando cargar archivos enormes en
  memoria.
- **Contraseñas de systemusage**: el esquema original usa
  `varchar(32)`, compatible solo con MD5. El código verifica y guarda
  en MD5 por defecto, pero detecta automáticamente si la columna fue
  ampliada a `varchar(255)+` y en ese caso usa `password_hash()`
  (bcrypt). Ver `sql/migration_security.sql` para migrar sin romper
  las claves existentes.
- Carpetas `includes/`, `vendor/` y `sql/` bloqueadas por `.htaccess`
  (`Require all denied`) para que no sean accesibles vía URL directa.
  Si el servidor es Nginx, replicar esa restricción en la config del
  sitio (ver sección 5).

## 4. Adaptar a otro sistema

Todo lo específico a "tu" sistema está aislado en pocos lugares:

| Qué cambiar | Dónde |
|---|---|
| Conexión a la base | `includes/config.php` |
| Ruta de logs | `includes/config.php` → `LOG_DIR_RAW` |
| Usuario fijo del login | `includes/config.php` → `SYSTEM_USER` |
| Tablas editables y sus campos | `negocio.php`, `smtp.php`, `usage.php` + sus `api/save_*.php` |
| Qué rol ve cada sección | `require_role([...])` al inicio de cada página/endpoint |

Para agregar una tabla nueva (por ejemplo `systemfacturacion`):
1. Crear `facturacion.php` (copiar `negocio.php` como base).
2. Crear `api/save_facturacion.php` (copiar `save_negocio.php`,
   ajustar validaciones a los campos reales).
3. Agregar el link en `partials/header.php` y en `dashboard.php`,
   dentro del `if` del rol que corresponda.

## 5. Ejemplo de bloqueo en Nginx (equivalente al .htaccess)

```nginx
location ~ ^/configuraciones/(includes|vendor|sql)/ {
    deny all;
    return 404;
}
```

## 6. Estructura de archivos

```
configuraciones/
├── index.php              Login
├── logout.php
├── dashboard.php           Panel según rol
├── negocio.php             Editar systemnegocio (admin, master)
├── smtp.php                Editar systemsmtp (admin, master)
├── usage.php                Editar systemusage (solo master)
├── logs.php                 Visor de logs
├── includes/
│   ├── config.php           DB + sesión
│   ├── auth.php              Roles, CSRF, hashing
│   └── functions.php
├── api/
│   ├── save_negocio.php
│   ├── save_smtp.php
│   ├── save_usage.php
│   ├── get_logo.php
│   └── get_log.php
├── partials/header.php
├── assets/css/style.css
├── assets/js/app.js
└── sql/migration_security.sql
```
