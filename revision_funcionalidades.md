# Revisión funcional — Decena de Oro

Fecha: 2026-11-02 · Rama: `claude/festive-rubin-qm04zf`

Esta revisión se enfoca en **funcionalidades** (bugs, estados
inconsistentes, concurrencia, UX), no en seguridad general ni en
diseño/CSS. El código está bien organizado: cada servicio tiene una
responsabilidad clara, el motor de cotejo usa `FOR UPDATE` en los
puntos correctos y los `INSERT ... ON DUPLICATE KEY` tapan la mayoría
de las carreras.

Los hallazgos están ordenados por **impacto en el funcionamiento**.
No se repiten los P0/P1 que ya figuran en `auditoria.md`.

---

## Alto · defectos funcionales con efecto visible

### 1. `UsuarioService::crear()` no es atómico y puede dejar un usuario sin código de referido

`src/Services/UsuarioService.php:58-103`. El `INSERT` del usuario y el
`asignarCodigoReferidoSiFalta()` corren sin transacción. Si los 10
intentos de generar un código único fallan (`ValidacionException`), el
usuario ya quedó insertado — pero el controlador recibe un error y
muestra "no se pudo dar de alta", así que el admin cree que no se
creó y vuelve a intentarlo. El segundo intento falla por nombre de
usuario duplicado.

**Fix sugerido**: envolver ambos pasos en `beginTransaction/commit`.
Mismo criterio en `VendedorService::crear()` si existe el mismo
patrón (vale la pena revisarlo).

### 2. `SolicitudService::crear()` deja solicitudes huérfanas si el cliente cierra la pestaña entre dos pasos

`src/Services/SolicitudService.php:121-202`. El `insertarSolicitudConReintento()`
inserta la fila fuera de transacción; después se abre otra transacción
dentro de `crearPendientes()`. Si el proceso PHP cae entre ambos pasos
(timeout, OOM, restart de PHP-FPM), queda una fila en `solicitudes`
sin ninguna fila en `jugadas`. Esa solicitud **sí aparece** en
`listarPendientes()` y, al hacerle "Confirmar", tira
`"La solicitud no tiene jugadas para confirmar"` sin más explicación —
el staff no tiene cómo deshacerlo sin tocar la base.

**Fix sugerido**: envolver todo `crear()` en una única transacción
(abrir `beginTransaction` en el servicio padre y que
`crearPendientes()` detecte `inTransaction()` para no abrir otra, mismo
patrón que ya usa `JugadaService::actualizarNumeros()` /
`anular()` en `src/Services/JugadaService.php:707-709`).
Como alternativa de bajo costo: un botón "Descartar solicitud sin
jugadas" para el admin.

### 3. Configuración se guarda sin transacción: una falla parcial deja parámetros inconsistentes

`admin/configuracion/guardar.php:35-41`. Siete llamadas sucesivas a
`actualizar*()` que terminan en `ParametroService::actualizar()`
(`src/Services/ParametroService.php:162-219`). Si la cuarta falla —
por ejemplo porque el valor de `horario_limite_sabado` no matchea el
regex `HH:MM` — las tres primeras **ya quedaron grabadas** y las
siguientes no corren.

Caso real posible: cambiar monto a $5000 y horario a "25:00". Se
guarda el monto nuevo pero no el horario; el admin ve el flash de
error, cree que no se guardó nada y vuelve a cambiar a "18:00" sin
notar que el precio del juego ya cambió.

**Fix sugerido**: validar TODOS los valores primero, y recién después
hacer un único `UPDATE ... CASE` o una transacción explícita que
abarque los 7 updates. También conviene centralizar la validación en
el propio `ParametroService` (hoy está repartida entre
`ConfiguracionService` y `ParametroService::actualizar`).

### 4. `RememberTokenService::validarYRotar()` puede dejar al cliente sin "recordarme"

`src/Services/RememberTokenService.php:71-100`. Flujo: DELETE del
token viejo, SELECT del cliente, INSERT del nuevo. No hay transacción.
Si el INSERT falla (deadlock, conexión caída) después del DELETE, el
cliente queda logueado por esta vez pero pierde la cookie para la
próxima apertura. El síntoma del cliente es: "funcionaba y de repente
me pide la clave otra vez" sin explicación.

**Fix sugerido**: envolver todo en `beginTransaction/commit`.

### 5. Confirmar un pago muestra el aviso de éxito aunque la comisión al referido falle

`src/Services/SolicitudService.php:423-495`. El `foreach` sobre las
jugadas llama a `comisiones->acreditarSiCorresponde()` dentro de la
misma transacción. Si eso lanza (por ejemplo, por el UNIQUE sobre
`jugada_id` en `comisiones`, que podría dispararse si un reintento ya
pasó por acá), toda la confirmación revierte y el flash dice sólo
"Pago no confirmado". Es correcto, pero el error crudo del PDO no es
amigable y el usuario del panel no sabe que la causa fue el referido.

**Fix sugerido**: envolver la llamada en try/catch, dejar `al_pozo`
sumado y re-lanzar un `ValidacionException` con mensaje legible
("No se pudo acreditar la comisión del referido N: …").

---

## Medio · consistencia y UX

### 6. `VendedorAuthService::verificar()` no actualiza `ultimo_acceso`

`src/Services/VendedorAuthService.php:37-82`. Las otras dos
autenticaciones (`AuthService:95-96`, `ClienteAuthService:99-100`) sí
lo actualizan. Es un dato que probablemente se use en un reporte y
hoy quedaría siempre en NULL para vendedores.

### 7. `requireVendedor()` no preserva el formulario cuando vence la sesión

`config/vendedor.php:56-72`. `requireLogin()` (`config/app.php:52-61`)
y `requireCliente()` (`config/portal.php:87-123`) llaman a
`guardarFormularioSinSesion()` antes del redirect; en vendedor se
omite. Hoy no tiene mucho impacto porque el panel del vendedor es
casi sólo lectura, pero si se agrega cualquier formulario (editar
perfil, alta rápida de referido), el vendedor va a perder lo tipeado
cada vez que le vence la sesión.

### 8. `JugadaService::anular()` y `anular.php` no muestran lo revertido en pozo

El servicio devuelve `aporte_pozo_revertido` y `comision_revertida`
(`src/Services/JugadaService.php:775-781`), pero el handler
`admin/jugadas/anular.php` sólo arma `$mensaje = 'Jugada anulada.'`.
Un admin que anula una jugada ya pagada no ve el monto que se le
restó al pozo; el único rastro queda en la auditoría. Vale la pena
incluir el monto en el flash de éxito.

### 9. `admin/jugadas/guardar.php` sufre N+1 en el flash de confirmación

`admin/jugadas/guardar.php:43-45`. Para armar el total cobrado hace
un `buscarPorId()` por cada jugada del lote. Para 10 jugadas, son 10
roundtrips extras después de la carga. `JugadaService::crearVarias()`
ya calculó los importes — alcanza con devolverlos (o con una suma
rápida en el servicio) y evitar el loop en el controlador.

### 10. `admin/configuracion/guardar.php` arma el flash con los **inputs crudos**, no con los valores validados

`admin/configuracion/guardar.php:48-55`. Si el admin tipea
"1000.00abc" como monto, la cadena cruda llega al mensaje de éxito
("monto de jugada $1.000,00abc"). `formatPesos()` castea a float y
descarta la basura, pero la lectura queda rara. Mejor usar
`$parametros->get(...)` después del update para construir el mensaje.

### 11. `CotejoService::jugadasGanadoras()` escala mal con el histórico

`src/Services/CotejoService.php:162-187`. La query hace un `IN
(SELECT DISTINCT sn.numero FROM sorteo_numeros sn JOIN sorteos s …)`
con `GROUP BY j.id HAVING COUNT(DISTINCT jn.numero) = (SELECT
COUNT(*) FROM jugada_numeros x WHERE x.jugada_id = j.id)`.
MySQL 8 lo optimiza decente, pero la correlacionada del `HAVING` se
evalúa por fila; en un ciclo con 2000 jugadas el cotejo tarda más de
lo necesario. Como `jugada_numeros` siempre tiene 5 (sábado) o 10
(semanal), se puede sustituir por `HAVING COUNT(DISTINCT jn.numero) =
:cant` pasando la constante por parámetro. No es un bug, pero es un
gane fácil (y evita que un día `jugada_numeros` tenga números extras
pegados por un bug de otra parte y silenciosamente premie jugadas de
menos aciertos).

### 12. `ClienteService::eliminar()` corre tres SELECTs y un UPDATE/DELETE sin transacción

`src/Services/ClienteService.php:295-321`. Si entre el conteo de
jugadas y el DELETE final otro proceso carga una jugada para ese
cliente, el DELETE físico lo deja sin cliente — y además borra sus
favoritas. Muy improbable en producción, pero es exactamente el tipo
de race que empieza a aparecer cuando dos admins trabajan a la vez.
Un `beginTransaction + SELECT ... FOR UPDATE` del cliente al
principio alcanza.

---

## Bajo · pulido

### 13. `CicloService::fechasDelProximoCicloSemanal()` depende de la zona horaria del server

`src/Services/CicloService.php:528-548`. `monday this week` usa el TZ
default, que `bootstrap.php:50` fija a `America/Argentina/Buenos_Aires`.
Está bien, pero si alguna vez se despliega en un host con `cron` o
tareas CLI que crean ciclos sin pasar por `bootstrap.php`, va a
elegir el lunes equivocado. Es un recordatorio más que un bug.

### 14. `portal/index.php` instancia `ParametroService` dos veces seguidas

`portal/index.php:45-47`. Línea por línea es inofensivo porque el
service cachea por request, pero visualmente da la impresión de dos
consultas. Reutilizar la instancia de `$parametros` que ya se
construye más abajo (línea 53).

### 15. `JugadaService::crear()` quedó como un wrapper de un argumento

`src/Services/JugadaService.php:70-77`. Hoy nadie lo usa; `crearVarias()`
cubre los dos casos. Vale la pena verificar con grep antes de borrar,
pero si no hay callers reduce ruido.

### 16. Mensajes mezclan acentos y texto sin acento

`src/Services/AuthService.php:60,74,78,86` y
`src/Services/ClienteService.php:339-343,348-351,355`. "Contrasena"
sin tilde conviviendo con "contraseña" en otras partes del mismo
código. No es funcional pero da la sensación de dos autores. Un pass
de normalización rápido (`contraseña`, `número`, `código`) mejora la
percepción.

---

## Lo que está bien y conviene no tocar

- **Concurrencia**: `FOR UPDATE` sobre el ciclo antes de cotejar
  (`SorteoService::registrar/registrarTurnoSabado`), replay completo
  del ciclo tras una corrección (`CotejoService::recotejarCiclo`), y
  el `ON DUPLICATE KEY UPDATE` del pozo. Esto es lo más difícil de
  hacer bien en el sistema y está sólido.
- **Reparto en centavos sin pérdida**:
  `PozoService::repartirEnPartesIguales()` reparte lo que sobra entre
  los primeros ganadores; evita el clásico "el pozo cerró en $0,01".
- **Validación centralizada en el servicio**: `requirePost()`,
  `exigirAbierto()`, `exigirGestionable()` se llaman desde el service
  mismo, no desde el controlador — así una ruta nueva no puede saltar
  la validación por olvido.
- **Sesiones separadas por cookie**: `PHPSESSID`, `POLLA_CLIENTE`,
  `POLLA_VENDEDOR`. Un cliente no puede terminar colándose en una
  guarda del panel por confusión de cookies.
- **Hash señuelo** en los tres `verificar()` para que un DNI/usuario
  inexistente tarde lo mismo que uno real. Buena práctica.

---

## Recomendaciones de orden de ejecución

Si hay que elegir dos para esta semana: **#2** (solicitudes
huérfanas) y **#3** (configuración sin transacción). Ambos cambian
plata o parámetros del juego y hoy pueden dejar estado inconsistente
sin aviso.

Después: **#1** (atomicidad del alta de usuario) y **#4** (recordarme
no transaccional) son fixes de 1-2 líneas con payoff claro.

El resto se puede atacar en un lote de "pulido" en una sesión aparte.
