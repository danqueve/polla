# Decena de Oro

Plataforma web para gestionar una polla de quiniela sobre la **Quiniela
Nocturna de la provincia de Tucumán**: alta de clientes, carga de
jugadas, carga del extracto y cotejo automático de ganadores.

El jugador elige **10 números distintos entre 00 y 99**; si los 10
salen en el extracto de un mismo sorteo (20 números, de lunes a
viernes), gana el pozo de la semana — sin premios por aciertos
parciales. El detalle funcional completo está en
[`especificacion-decena-de-oro.md`](especificacion-decena-de-oro.md).

## Stack

- **PHP 8.2+**, sin framework ni Composer — autoload propio (`Polla\*`
  → `src/`, ver `config/bootstrap.php`).
- **MySQL / MariaDB** (probado sobre MySQL 8.4), acceso por PDO con
  prepared statements en toda la capa de datos.
- **Bootstrap 5** vía CDN, mobile-first (pensado para cargarse desde
  el celular, tanto el panel de staff como el portal del cliente).
- Sin build tools: no hay `npm`, `webpack` ni paso de compilación.

## Puesta en marcha (WAMP / local)

1. Cloná el repo dentro de tu `www` (por ejemplo `c:\wamp64\www\polla`).
2. Copiá `config/db.example.php` a `config/db.php` y completá las
   credenciales de tu MySQL local, apuntando a la base
   `c2881399_polla` (el nombre real que usan hoy tanto el VPS de
   producción como el entorno local). `config/db.php` está en
   `.gitignore`, así que no se pisa con los `git pull`.

   El repo no versiona un script de instalación limpia (`db/schema.sql`
   y `db/seed.sql` se sacaron por traer datos reales pegados a la
   estructura): para levantar una base desde cero, pedí un volcado sin
   datos de clientes a quien mantiene el proyecto, o reconstruí el
   esquema aplicando en orden lo que documenta cada archivo de
   `db/migrations/`.

3. Entrá a `http://localhost/polla/` — redirige al login del panel
   (`auth/login.php`). El portal del cliente vive aparte, en
   `http://localhost/polla/portal/login.php` (usuario y clave = DNI).

## Estructura del proyecto

```
admin/        Panel de staff (admin/supervisor): clientes, jugadas,
              sorteos, ciclos, solicitudes, promociones, reportes,
              configuración, usuarios.
portal/       Portal del cliente: login, armar jugada, historial.
auth/         Login/logout del panel de staff.
src/Services/ Toda la lógica de negocio (namespace Polla\Services).
src/Support/  Excepciones y helpers compartidos (Polla\Support).
config/       Bootstrap compartido, conexión a PDO, sesión de staff
              (app.php) y sesión de cliente (portal.php), separadas
              por cookie propia.
includes/     Parciales de vista (cabecera, navegación, flash, etc).
assets/       CSS y JS propios, sin build step.
db/           migrations/ (cambios incrementales ya aplicados, en
              orden). Sin script de instalación limpia versionado.
doc/          Material para imprimir/compartir (instructivo del
              jugador en PDF).
```

Los controladores bajo `admin/`, `portal/` y los archivos de ruta en
la raíz son deliberadamente delgados: validan la request y delegan en
un servicio de `src/Services/`, que es donde vive toda la lógica real
(cálculo del pozo, cotejo, reparto de premios, etc).

## Funcionalidad por fase

| Fase | Contenido | Estado |
|---|---|---|
| 1. Núcleo | Usuarios y roles, ABM de clientes, carga de jugadas | Implementada |
| 2. Sorteos y cotejo | Carga del extracto, cotejo automático, cierre y liquidación del pozo | Implementada |
| 3. Portal del cliente | Login DNI/DNI, jugadas y resultados, estado del pozo | Implementada |
| 4. Administración y reportes | Recaudación, historial de ganadores, exportables | Implementada |
| 5. Ampliación | Autorregistro con aprobación, monto configurable, carga múltiple | Implementada |
| 6. Selección propia de jugadas | El cliente arma su jugada desde el portal, con autorización de pago por staff | Implementada |
| 7. Premio base y promociones | Piso garantizado de pozo por ciclo, paquetes promocionales de jugadas | Implementada |
| 8. Carga anticipada | Ciclo "programado" para cargar jugadas de la próxima semana por adelantado | Borrador, en revisión |

Ver [`especificacion-decena-de-oro.md`](especificacion-decena-de-oro.md)
para el detalle funcional y las notas de implementación de cada fase.

## Convenciones del código

- **Un servicio por dominio** (`ClienteService`, `JugadaService`,
  `PozoService`, `SorteoService`, ...), instanciado desde una fábrica
  estática `crearDesde(PDO $db)` que resuelve sus dependencias.
- **Errores de negocio** se señalizan con `Polla\Support\ValidacionException`,
  con `->errores()` devolviendo los mensajes para mostrar al usuario —
  nunca se filtra un mensaje crudo de SQL a una pantalla.
- **Migraciones**: cada cambio de esquema vive en
  `db/migrations/<fecha>_<descripción>.sql`, en orden.
- **Clave del cliente**: siempre igual al DNI, sin excepción y sin
  pantalla de cambio — se regenera sola si un admin le edita el DNI.
  La clave del staff (admin/supervisor) es independiente y sí se
  puede cambiar (`auth/cambiar_clave.php`).
