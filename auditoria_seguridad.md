# Auditoría de seguridad — Decena de Oro (2026-11-08)

Revisión de seguridad de todo el proyecto con la guía OWASP Top 10:2025 /
ASVS 5.0 (skill `owasp-security`), más lo que aplica de las skills
`php-best-practices` y `mysql`. No repite lo que ya figura en
`auditoria.md` ni en `revision_funcionalidades.md`.

Cada hallazgo se verificó contra el código y, cuando se pudo, contra la
base local (MariaDB 10.4) o el Apache local. Severidad según qué tan
explotable es, no según el patrón.

## Lo que está bien

- **SQL**: todas las consultas usan prepared statements, con
  `ATTR_EMULATE_PREPARES = false`. Los fragmentos dinámicos (`WHERE`,
  `IN (...)`, `LIMIT`) se arman con constantes o enteros. No encontré
  inyección.
- **XSS**: las vistas escapan con `e()` de forma consistente. Revisé
  todas las salidas `<?=` de columnas de texto y los sinks de JS
  (`innerHTML` solo recibe literales).
- **CSRF**: está en todos los POST (`requirePost()` / `verifyCsrf()`
  con `hash_equals`).
- **Control de acceso**: todos los puntos de entrada de `admin/`,
  `portal/` y `vendedor/` tienen su guarda. `admin/jugadas/eliminar.php`
  no la tiene, pero delega en `anular.php`, que sí. En el portal, todo lo
  que llega con un id (favoritas, solicitudes, "repetir") se filtra por
  `cliente_id`.
- **Sesiones**: `session_regenerate_id(true)` en los tres logins, cookies
  `HttpOnly` + `SameSite=Lax` (+ `Secure` en producción). El "recordarme"
  guarda solo el sha256 y rota el token en cada uso.
- **Contraseñas**: bcrypt, con rehash automático y un hash señuelo para
  que el tiempo de respuesta no delate qué usuarios existen.
- **Ganancias** (último commit): el bloqueo `FOR UPDATE` sobre `ciclos`
  (InnoDB) cubre bien el doble clic y la liquidación simultánea, y los
  montos se manejan en centavos enteros.
- Todos los `.php` pasan `php -l` con PHP 8.0.

---

## Hallazgos

### [ALTO] Cualquiera que sepa un DNI entra a esa cuenta de cliente, sin límite práctico
*(CWE-307 / CWE-521, OWASP A07:2025 Authentication Failures, A06:2025 Insecure Design)*

- **Dónde**: `auth/login.php:113-160`, `src/Services/LoginThrottle.php:30-54`,
  `src/Services/ClienteAuthService.php:57-92`
- **Recorrido**: `POST /auth/login.php` con `usuario=<DNI>&password=<DNI>`
  → `ClienteAuthService::verificar()` → sesión de cliente abierta.
- **Por qué funciona a escala**: la clave del cliente es siempre el DNI.
  El throttle cuenta intentos por par **(identificador, IP)**, así que
  cada DNI nuevo arranca con su contador en cero. Desde una sola IP se
  puede recorrer un rango de DNIs probando una vez cada uno, y el límite
  nunca salta. `registro.php` además confirma qué DNIs existen (redirige
  con `?ya_registrado=1`).
- **Impacto**: sin autenticarse, un atacante ve nombre, número de
  cliente, historial de jugadas y códigos de pago pendientes de
  cualquier cliente, y puede crear solicitudes a su nombre. Si ese
  cliente es vendedor, también entra a su panel (ver el hallazgo
  siguiente).
- **Arreglo**:
  1. Mínimo, sin cambiar el modelo: un segundo contador **solo por IP**
     (por ejemplo, 20 fallos en 15 minutos sin importar el
     identificador), y normalizar el identificador antes de contar (ver
     el hallazgo de las variantes de DNI).
  2. Decisión de negocio: la regla "clave = DNI, sin excepción" es la
     raíz del problema. Un PIN propio opcional, o un segundo dato que no
     sea público (por ejemplo, los últimos 4 dígitos del teléfono), lo
     resolvería de fondo.
- **Confianza**: confirmado en el código.

### [MEDIO] La contraseña del vendedor se puede saltear entrando como cliente
*(CWE-288, OWASP A07:2025)*

- **Dónde**: `portal/panel_vendedor.php:14-27`
- **Recorrido**: login de cliente con DNI/DNI → `GET /portal/panel_vendedor.php`
  → `VendedorAuthService::abrirSesion()` sin pedir clave.
- **Impacto**: todo vendedor tiene una cuenta de cliente vinculada con
  clave = DNI (`VendedorService::resolverClienteId()`). En la práctica,
  la clave propia del vendedor no protege nada: con su DNI se ve su
  saldo de comisiones y la lista de sus referidos (nombres).
- **Confianza**: confirmado.
- **Estado: arreglado.** `portal/panel_vendedor.php` ahora pide la
  clave del vendedor.
  - Se verifica con `VendedorAuthService::verificar()`, la misma función
    del login.
  - Usa el mismo `LoginThrottle`, con el DNI normalizado, así que
    comparte el contador con el login, y la misma auditoría
    (`login_ok`, `login_fallido`, `login_bloqueado`).
  - El salto inverso (`vendedor/jugar.php`) sigue sin clave, porque va
    de la clave más fuerte a la más débil.
  - Se probó con 9 escenarios dentro de una transacción revertida:
    entrada normal, CSRF inválido, clave vacía, clave como array, **el
    DNI como clave (el ataque)**, clave correcta, límite de intentos
    alcanzado, cliente sin vendedor y vendedor dado de baja. También
    por HTTP sin sesión.

### [MEDIO] El límite de intentos se esquiva escribiendo el DNI con otro formato
*(CWE-307, OWASP A07:2025)*

- **Dónde**: `auth/login.php:114,122` (throttle con el texto crudo)
  contra `ClienteService::normalizarDni()` (`preg_replace('/\D/', '')`).
- **Recorrido**: `12345678`, `12.345.678`, `x12345678`, `12345678-`...
  son filas distintas en `login_intentos` (lo comprobé con la collation
  real), pero todas terminan en la misma cuenta al autenticar.
- **Impacto**: intentos ilimitados contra la clave de un **vendedor**,
  que entra con su DNI. Para el staff no pasa: el usuario se compara con
  `utf8mb4_unicode_ci` en las dos tablas, así que las variantes de
  mayúsculas o tildes caen en el mismo contador.
- **Arreglo**: en `auth/login.php`, pasar al throttle el identificador
  normalizado (`normalizarDni()` si el texto tiene dígitos, si no
  `mb_strtolower(trim())`), y sumar el contador por IP del hallazgo ALTO.
- **Confianza**: confirmado.

### [MEDIO, solo local] El script de demo corre por HTTP y la máquina de desarrollo está expuesta en la red

- **Dónde**: `demo/simulacion_demo.php` (sin guarda de CLI), Apache local
  `Listen 80/443` y MySQL `*:3306` en todas las interfaces.
- **Qué pasa**: un `GET /polla/demo/simulacion_demo.php` ejecuta el
  script. Lo primero que hace es fijar `horario_limite_semanal = 23:59` y
  poner la clave `demo2026` al usuario `admin`, y recién después revisa
  si la base tiene datos. Desde la red local también responden
  `/polla/.git/` (código e historial completos), `demo/datos_demo.sql`,
  el `.patch` y los `.md` de auditoría. La base local tiene una copia de
  los datos de producción.
- **Esta auditoría lo disparó sin querer**: mi sondeo HTTP pidió esa
  URL. El script cambió `horario_limite_semanal` de `21:30` a `23:59` en
  la base local, y ahí se cortó por un error (`STDERR` no existe en modo
  web). El cambio de clave no tocó ninguna fila, porque en la base local
  no hay ningún usuario `admin`. **Hay que volver el parámetro a 21:30**
  (ver el resumen al final).
- **En producción no aplica**: `.cpanel.yml` no copia `demo/`.
- **Arreglo**:
  - Al principio de `demo/simulacion_demo.php` y de los `.php` de
    `db/migrations/`:
    `if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }`
  - Agregar `demo/.htaccess` y `scripts/.htaccess` con `Require all denied`.
  - En XAMPP: `Listen 127.0.0.1:80` y `bind-address = 127.0.0.1` en
    `my.cnf`.

### [BAJO, a verificar] El entorno se decide por el header `Host`
*(CWE-16 / CWE-209, OWASP A02:2025 Security Misconfiguration)*

- **Dónde**: `config/bootstrap.php:32-47`
- **Qué pasa**: con `Host: localhost`, la app se pone en modo
  `development`: `display_errors=1`, cookie de sesión sin `Secure`, y
  `APP_URL` apuntando a localhost. Cualquier `TypeError` muestra rutas y
  la traza de la pila. Por ejemplo, `POST /auth/login.php` con
  `usuario[]=x` llega a `trim(array)` antes del chequeo de CSRF.
- **Cuándo importa**: solo si el servidor de producción atiende pedidos
  con un `Host` cualquiera (vhost por defecto en un VPS de un solo
  sitio). En un cPanel compartido, normalmente no llega.
- **Estado: arreglado.** `config/bootstrap.php` toma `APP_ENV` y
  `APP_URL` de `config/db.php` (que no se versiona), y nunca del pedido:
  - Si falta `APP_ENV`, se asume `production`.
  - Un valor inválido (por ejemplo `prod`, o una URL con barra final)
    corta con un 500 y deja el motivo en el log.
  - Mientras el `db.php` de producción no tenga `APP_URL`, se sigue
    armando con el `Host`, como antes, pero solo si tiene forma de
    nombre de host.
  - Se probó con 8 escenarios aislados y contra el Apache local con el
    `Host` falsificado.
  - **Pendiente en el servidor**: agregar al `config/db.php` de
    producción `define('APP_ENV', 'production');` y
    `define('APP_URL', 'https://<dominio>');`.

### [BAJO] Inyección de fórmulas en los CSV
*(CWE-1236, OWASP A05:2025 Injection)*

- **Dónde**: `admin/reportes/exportar.php`, `admin/ganancias/exportar.php`
- **Recorrido**: el nombre del cliente sale del autorregistro público
  (`registro.php`) → exportes de jugadas, ganadores, clientes y detalle →
  Excel interpreta `=...`, `+...`, `-...`, `@...` como fórmula.
- **Condición previa**: alguien tiene que aprobar el alta con ese nombre
  y abrir el CSV en Excel.
- **Estado: arreglado.** `celdaCsv()` (`config/bootstrap.php`) antepone
  `'` a las celdas que empiezan con `= + - @`, tabulador o retorno de
  carro. Lo que es solo un número (por ejemplo `-150,00`) pasa intacto.
  Los dos exportadores pasan cada celda por ahí. Se probó con 20 casos
  (fórmulas, DDE, números negativos, fechas, números de jugada) y
  corriendo los 6 exportes reales contra la base local.

### [BAJO] El autorregistro no tiene límite
*(OWASP A06:2025)*

- `registro.php` no limita cuántas altas se pueden hacer. Se puede
  llenar la cola de autorregistros, o "ocupar" el DNI de otra persona
  con otro nombre y teléfono (si se rechaza, ese DNI queda bloqueado).
  Se arregla con el mismo contador por IP.

### [INFO] Endurecimiento (no son vulnerabilidades)

- **No hay Content-Security-Policy**, aunque el comentario de
  `bootstrap.php:56` dice que `X-XSS-Protection` se apaga "a favor de
  CSP". **Tampoco hay HSTS.** Como el escape está bien hecho, es una
  defensa extra. Por los `style=""` en línea, la política tendría que
  arrancar con `style-src 'self' 'unsafe-inline'`.
- **No hay manejador global de excepciones**: un parámetro con forma de
  array (`?tipo[]=`, `usuario[]`, una cookie `remember_token[]`)
  devuelve un 500 en blanco. No se puede explotar; con
  `set_exception_handler()` se registraría en el log y se mostraría una
  página genérica.
- **El logout es por GET y sin CSRF**: lo único que permite es cerrarle
  la sesión a alguien.
- **`.cpanel.yml` usa `cp -R`**, que nunca borra: un `.php` que se saca
  del repo sigue publicado en producción.

---

## Operación y entorno

| Tema | Estado | Acción |
|---|---|---|
| `jugada_numeros`, `sorteos`, `sorteo_numeros` en MyISAM | Sigue pendiente (ver `revision_funcionalidades.md`) | Convertir a InnoDB en una ventana de mantenimiento, después del preflight de integridad. |
| Zona horaria de MySQL | Sin confirmar | En producción: `SELECT @@global.time_zone, NOW();` |
| PHP local 8.0.30 (sin soporte desde nov-2023), el README pide 8.2+ | Todo compila en 8.0 | Actualizar XAMPP para que el entorno local se parezca a producción. |
| README y `service-worker.js` dicen "Bootstrap vía CDN" | Desde `8c8b9b0` se sirve desde el propio sitio (Bootstrap 5.3.3, Icons 1.11.3) | Corregir la documentación. |
| `.git/` en producción | No se puede ver desde acá | Confirmar que `https://<dominio>/.git/HEAD` dé 403 o 404. El historial tuvo credenciales y datos reales. |

## Orden sugerido

1. Volver `horario_limite_semanal` a 21:30 en la base local, y agregar
   la guarda de CLI y los `.htaccess` a `demo/` y `scripts/`.
2. Normalizar el identificador del throttle y sumar el contador por IP
   (resuelve el hallazgo ALTO en su forma masiva, el de las variantes de
   DNI y el del autorregistro).
3. ~~Re-autenticación en `portal/panel_vendedor.php`.~~ Hecho.
4. ~~`APP_ENV`/`APP_URL` fijos en `config/db.php`.~~ Hecho (falta
   agregarlos al `config/db.php` del servidor).
5. CSP/HSTS y manejador de excepciones (el escape de fórmulas en los
   CSV ya está hecho).
6. Decidir el modelo de clave del cliente (DNI solo, PIN o un segundo dato).
