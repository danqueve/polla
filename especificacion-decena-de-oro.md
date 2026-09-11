# Especificación funcional y técnica
## Decena de Oro

**Fecha:** Septiembre 2026
**Versión:** 1.8 (agrega Fase 11: vendedores y referidos — implementada. Ya estaban implementadas la Fase 9: horario límite de carga, y la Fase 10: juego de sábados. Sigue el borrador de Fase 8: carga anticipada para la próxima semana, en revisión, sin dependencia con las fases nuevas)

---

## 1. Objetivo

Plataforma web para gestionar una polla de quiniela sobre la Quiniela Nocturna de la provincia de Tucumán, con registro de clientes, carga de jugadas, carga de resultados y cotejo automático de ganadores. Uso principal desde celulares y tablets, tanto para la carga de datos (administrador/supervisor) como para la consulta de resultados (clientes).

## 2. Contexto del juego

Este juego es una variante de la modalidad oficial conocida como **Quiniela Poceada** (que usa 8 números de 2 cifras contra los 20 premios del extracto) y de la **Quiniela Plus**, que toma la decena y unidad de los 20 números del extracto de la Nocturna. La variante "Decena de Oro" usa **10 números** y exige que **los 10 estén contenidos en el extracto** para ganar, sin premios por aciertos parciales.

## 3. Reglas del juego

- Cada jugada cuesta un **monto configurable por el Administrador** (valor inicial: $2.000). Los cambios de monto no afectan jugadas ya cargadas — cada jugada guarda el importe vigente al momento de pagarse.
- Reparto de cada jugada pagada: **60% al pozo de premios**, **40% a gastos/ganancias** de Decena de Oro.
- El cliente elige **10 números distintos entre 00 y 99**.
- Los 10 números deben salir **todos en un mismo sorteo** de la Quiniela Nocturna de Tucumán (que sortea 20 números de 2 cifras, lunes a viernes).
- Si los 10 números de una jugada están contenidos entre los 20 del sorteo, esa jugada **gana automáticamente**.
- Al producirse un ganador, la semana se **corta**: se liquida el pozo y se espera hasta el ciclo de la semana siguiente para volver a jugar.
- Si **más de un cliente** gana en el mismo sorteo, el pozo se **divide en partes iguales** entre todos los ganadores.
- Si nadie gana en toda la semana (lunes a viernes), la semana se cierra sin pago. *(Ver punto 3.1 sobre continuidad del pozo.)*
- No hay arrastre de saldo entre jugadas: para volver a jugar, el cliente **paga una jugada nueva**.

### 3.1 Pozo dinámico

El pozo **no es un monto fijo predefinido**: nace en $0 al abrir cada ciclo semanal y crece con el 60% de cada jugada pagada que se va cargando. Cuando aparece un ganador (o varios), el pozo acumulado hasta ese momento se reparte íntegro y el contador vuelve a $0 para el ciclo siguiente.

### 3.2 Corte a mitad de semana (resuelto)

Cuando el corte ocurre a mitad de semana (por ejemplo, gana alguien el martes):
- Los sorteos restantes de esa semana calendario (miércoles a viernes) **no participan** del juego.
- Cualquier jugada nueva que se cargue después del corte pertenece automáticamente al **ciclo de la semana siguiente**, y su 60% empieza a acumular el pozo nuevo desde $0.

### 3.3 Ciclo sin ganador (resuelto)

Si llega el sorteo del viernes sin ganador, el ciclo se cierra como `cerrado_sin_ganador`, pero el pozo **no se resetea**: el monto acumulado arrastra al ciclo siguiente, que abre ya con ese saldo en vez de en $0. En pantalla se muestra como un número único (el pozo vigente), aunque en la base queda registrado por ciclo, de modo que se puede desglosar por semana más adelante sin migrar el esquema.

## 4. Roles y permisos

| Acción | Administrador | Supervisor |
|---|---|---|
| Crear y borrar usuarios (admin/supervisor) | ✅ | ❌ |
| Dar de alta clientes (manual) | ✅ | ✅ |
| Cargar jugadas (una o varias juntas) | ✅ | ✅ |
| Cargar los 20 números del sorteo | ✅ | ✅ |
| Borrar clientes / jugadas / sorteos | ✅ | ❌ |
| Configurar parámetros (monto de jugada, % pozo/gastos) | ✅ | ❌ |
| Ver reportes y recaudación | ✅ | ✅ (limitado, a definir) |
| Aprobar autorregistros de clientes | ✅ | ✅ |
| Rechazar o eliminar solicitudes de autorregistro | ✅ | ❌ |

El cliente **no es un usuario administrativo**: tiene su propio login de solo lectura (ver punto 6).

## 5. Flujo funcional

1. **Alta de cliente**: por dos vías —
   - **Manual**: admin o supervisor carga DNI, nombre y teléfono; queda aprobada al instante. Usuario y clave = DNI, fija.
   - **Autorregistro**: la propia persona completa el formulario público, elige su contraseña, y la cuenta queda **pendiente** hasta que un admin/supervisor la apruebe (ver sección 7.1).
   - En ambos casos el sistema genera un **número de cliente** único (formato año + 6 dígitos aleatorios).
2. **Carga de jugada**: admin o supervisor selecciona cliente, ingresa uno o **varios sets de 10 números** en la misma operación (ver sección 7.3), registra el pago total y confirma. El sistema suma el 60% de cada jugada al pozo del ciclo activo.
3. **Carga del sorteo**: al finalizar cada sorteo nocturno, admin o supervisor carga manualmente los 20 números del extracto oficial.
4. **Cotejo automático**: al guardar el sorteo, el sistema compara los 10 números de cada jugada activa del ciclo contra los 20 cargados. Si hay intersección de 10, marca la jugada como ganadora.
5. **Cierre de ciclo**: si hay uno o más ganadores, el sistema liquida el pozo (dividido en partes iguales si hay más de uno), marca el ciclo como cerrado y abre uno nuevo en $0.
6. **Consulta del cliente**: el cliente entra al portal y ve sus jugadas del ciclo actual e historial, con los números marcados según hayan salido o no en cada sorteo, y el estado del pozo.

## 6. Portal del cliente

- Login: usuario = DNI, clave = DNI — **fija para todos los clientes, sin excepción por origen de alta y sin opción de cambiarla**. No existe pantalla de cambio de clave para clientes.
- Un cliente con cuenta en estado **pendiente** puede iniciar sesión pero solo ve un aviso de que su cuenta está en revisión — no puede ver jugadas ni se le pueden cargar hasta que se apruebe.
- Vista de jugadas activas del ciclo en curso, con los 10 números y cuáles ya salieron en los sorteos corridos de la semana.
- Historial de jugadas y resultados de ciclos anteriores.
- Estado del pozo acumulado del ciclo actual (opcional, para generar expectativa).

## 7. Ampliación post Fase 4 (nuevas funcionalidades)

### 7.1 Autorregistro de clientes

- Formulario público (sin login) donde cualquier persona carga DNI, nombre y teléfono — **sin campo de contraseña**: la clave queda fijada automáticamente como el DNI, igual que en el alta manual.
- Validación de DNI: formato numérico (7-8 dígitos) y no duplicado contra clientes existentes.
- La cuenta queda con estado **pendiente** y no puede operar (ni cargarle jugadas ni ver datos) hasta que un admin o supervisor la **apruebe** desde un listado de solicitudes pendientes.
- Al aprobarse, pasa a estado **aprobado** y el cliente ya puede operar con normalidad.
- El alta manual por staff sigue existiendo en paralelo y queda **aprobada automáticamente**, sin pasar por la cola de aprobación, porque ahí el staff ya validó los datos en persona.

### 7.2 Monto de jugada configurable

- El Administrador puede cambiar el monto de la jugada desde una pantalla de configuración (ver fila "Configurar parámetros" en la sección 4).
- El sistema toma el monto vigente al momento de cargar cada jugada nueva y lo guarda en `jugadas.importe` — los cambios de monto no alteran jugadas ya cargadas ni el cálculo del pozo de ciclos anteriores.

### 7.3 Carga múltiple de jugadas en una sola operación

- En la pantalla de carga de jugada, admin/supervisor pueden agregar **varios sets de 10 números para el mismo cliente en una sola operación** (por ejemplo, 3 jugadas si el cliente paga por 3 de una vez), en vez de repetir el formulario completo cada vez.
- Cada set se guarda como una **jugada independiente** (con su propio importe, tomado del monto vigente), todas asociadas al mismo cliente y al ciclo activo, y todas aportan su 60% por separado al pozo.
- El pago se sigue gestionando en persona o por transferencia por el staff, que confirma el total cobrado (monto vigente × cantidad de jugadas) al cerrar la carga — no requiere integrar un medio de pago online.
- Queda registrado a qué operación de carga conjunta pertenece cada jugada, solo a fines de trazabilidad en reportes (no afecta la lógica de premios ni el cotejo).

## 8. Fase 7: premio base garantizado y promociones de paquete

### 8.1 Premio base garantizado

- El Administrador configura un **premio base** (ej. $25.000) desde la misma pantalla de configuración del monto de jugada.
- El pozo que se muestra y que efectivamente se paga es siempre el **mayor entre el premio base y el monto acumulado real** de ese ciclo: `pozo_mostrado = MAX(premio_base, monto_acumulado_real)`.
- Esta regla se aplica **en cada ciclo nuevo**: apenas arranca una semana (haya o no arrastre de un ciclo anterior sin ganador), el pozo nunca se muestra por debajo del premio base.
- Si aparece un ganador antes de que las ventas reales cubran el premio base, la diferencia la cubre la empresa (sale del 40% o de fondos propios, fuera del alcance del sistema) — el sistema debe **registrar por separado** cuánto se acumuló realmente por ventas y cuánto se terminó pagando, para que quede el dato de cuánto se subsidió.
- Si el Administrador cambia el premio base a mitad de ciclo, se aplica el valor vigente al momento en que se liquida el pozo (no hace falta recalcular retroactivamente ciclos ya cerrados).

### 8.2 Promociones de paquete

- El Administrador puede crear **paquetes promocionales**: una cantidad fija de jugadas por un precio total con descuento (ej. 4 jugadas por $10.000, en vez de 4 × $3.000 = $12.000).
- El descuento sale del **60% de premios**: el pozo suma según lo **realmente cobrado** con la promo aplicada, no según el precio de lista. Cada jugada del paquete guarda como `importe` el precio total de la promo dividido la cantidad (ej. $10.000 / 4 = $2.500 por jugada), para que la suma del 60% de cada una dé exactamente el 60% de lo cobrado.
- Al cargar varias jugadas juntas (carga múltiple del staff, o selección propia del cliente), si la cantidad elegida coincide con una promoción activa, el sistema debe **sugerir aplicarla** (ej. "con 4 jugadas te conviene la promo de $10.000") dejando a quien carga la decisión de aplicarla o cobrar precio de lista.
- Puede haber varias promociones activas a la vez (ej. una de 4 jugadas y otra de 10), cada una con su propio precio.

### 8.3 Notas de implementación

- **`monto_acumulado` no se renombró.** Ya significaba "lo realmente acumulado" desde la Fase 1 y nunca significó otra cosa, así que tocarlo en 9 archivos era un riesgo sin beneficio. Lo nuevo es `monto_piso_aplicado` (snapshot del premio base al momento de liquidar) y `monto_pagado` sigue siendo el resultado final.
- **El MAX de un ciclo abierto se calcula al vuelo, nunca se persiste.** `PozoService::montoAMostrar()` compara el acumulado real contra `ParametroService::premioBase()` leído en caliente en cada pantalla; si el Administrador cambia el premio base, se ve reflejado de inmediato en todos lados sin tocar filas viejas. Recién al liquidar (`PozoService::liquidar()`, llamado desde `SorteoService` cuando el cotejo encuentra un ganador) se graba el piso vigente en `monto_piso_aplicado` y el resultado final en `monto_pagado`, que a partir de ahí quedan fijos para siempre — cambiar el premio base después no altera ciclos ya liquidados.
- **Un ciclo cerrado sin ganador nunca aplicó ningún piso**: no se pagó nada, así que se sigue mostrando el acumulado real tal cual, sin `monto_piso_aplicado`.
- **Desglose "real vs. piso vs. subsidiado" visible solo para el Administrador**, en el tablero y en el detalle de cada ciclo (`admin/ciclos/ver.php`) — el portal del cliente solo muestra el monto final (`MAX(...)`), nunca el desglose ni cuánto se está subsidiando.
- **Una sola promoción activa por cantidad de jugadas**, garantizado a nivel de base de datos con el mismo patrón que `ciclos.abierto_flag`: una columna generada (`cantidad_si_activa`) que solo vale `cantidad_jugadas` cuando `activa=1`, con un índice UNIQUE sobre esa columna. Desactivar una promo libera esa cantidad para una nueva.
- **`resolverImportes()`** (en `JugadaService`, usado tanto por la carga del staff como por `SolicitudService`) valida que la promo elegida siga activa y que su `cantidad_jugadas` coincida exactamente con la cantidad que se está cargando — si el cliente agregó o sacó una jugada después de que se le sugirió la promo, se rechaza en vez de cobrar mal.
- El reparto del precio del paquete entre las jugadas reutiliza el mismo algoritmo centavo-seguro que ya repartía premios entre ganadores empatados (`PozoService::repartirEnPartesIguales()`), así la suma de los importes de un paquete siempre da exactamente el precio total.
- **Pantallas:** `admin/configuracion/index.php` (premio base), `admin/promociones/` (alta/edición/activar-desactivar), y el widget de sugerencia (`assets/js/promociones.js`) compartido entre `admin/jugadas/nueva.php` y `portal/jugar.php`, enganchado al mismo evento `grupos:cambio` que ya disparaba `assets/js/numeros.js` al agregar o quitar una jugada.

## 9. Fase 8: carga anticipada para la próxima semana

- El sistema mantiene **siempre un ciclo "programado"** además del ciclo "abierto" (activo): apenas un ciclo pasa a abierto, se crea automáticamente el siguiente en estado `programado`, listo para recibir jugadas por anticipado.
- Al cargar una jugada (staff, en cualquiera de las pantallas de carga, o el cliente desde el portal), se agrega un selector **"¿Para esta semana o para la próxima?"**. Por defecto es "esta semana" (ciclo abierto); si se elige "próxima semana", la jugada se guarda directamente con el `ciclo_id` del ciclo programado, de forma fija — no se reevalúa después, a diferencia de la regla de la sección 14.2 que sigue aplicando solo cuando el cliente NO elige semana explícitamente.
- El pozo de un ciclo programado ya **acumula normalmente** con las jugadas que se le van cargando por anticipado (mismo cálculo de 60% y de premio base de la sección 8.1), aunque todavía no sea el ciclo activo para el cotejo.
- Cuando el ciclo abierto se cierra (con ganador a mitad de semana, según 3.2, o sin ganador el viernes, según 3.3), el ciclo `programado` pasa a `abierto` automáticamente — con todo lo que ya se le cargó por anticipado — y se crea un nuevo ciclo `programado` para la semana siguiente.
- Esto cubre los dos casos que mencionaste: alguien que quiere anotarse para la semana que viene estando todavía en curso la actual, y alguien que quiere seguir jugando ni bien se corta una semana antes de tiempo, sin esperar a que arranque formalmente la próxima.

## 10. Modelo de datos (propuesta inicial)

**usuarios**
`id, usuario, password_hash, rol (admin | supervisor), activo`

**clientes**
`id, nro_cliente (formato AAAA-NNNNNN, año + 6 dígitos aleatorios, UNIQUE), dni, nombre, telefono, password_hash (siempre = hash del DNI, se regenera si el DNI se edita), estado (pendiente | aprobado | rechazado), origen_alta (manual | autorregistro), fecha_alta`

**ciclos** *(semana de juego, o sábado de juego — Fase 10)*
`id, tipo (semanal | sabado — Fase 10, cada uno con su propia numeración y su propio "un ciclo abierto a la vez"), fecha_inicio, fecha_fin (para un ciclo de sábado, iguales: dura un solo día), estado (programado | abierto | cerrado_con_ganador | cerrado_sin_ganador)`

**jugadas**
`id, cliente_id, tipo_juego (semanal | sabado — Fase 10, para saber a qué caja pertenece mientras esta pendiente_pago sin ciclo_id todavía), ciclo_id (nullable — ver 14.3), importe, pagada, estado_pago (pendiente_pago | confirmada | rechazada — ver 14.3), origen_carga (staff | cliente), solicitud_id (nullable, FK a solicitudes — ver 14.4), grupo_compra (id o UUID, para agrupar jugadas cargadas juntas por el staff), promocion_id (nullable, FK a promociones si el paquete se cargó con descuento — Fase 7, exclusiva del juego semanal), estado (activa | ganadora | perdedora | anulada — veredicto del cotejo, no confundir con estado_pago), fecha_carga, cargado_por (usuario_id, nullable — vacío cuando origen_carga = cliente)`

**jugada_numeros**
`id, jugada_id, numero (00-99)`

**sorteos**
`id, ciclo_id, fecha, turno (1 para el juego semanal — un sorteo por fecha, como siempre; 1 a 5 para un sábado, que reparte sus 5 turnos en la misma fecha — Fase 10), cargado_por (usuario_id)`

**sorteo_numeros**
`id, sorteo_id, numero (00-99)`

**pozo_ciclo**
`ciclo_id, monto_acumulado (suma real del 60% de jugadas confirmadas — no se renombró: ya significaba "lo realmente acumulado" desde la Fase 1), monto_piso_aplicado (premio_base vigente al momento de liquidar, aunque no haya llegado a usarse — Fase 7), monto_pagado (= MAX(monto_acumulado, monto_piso_aplicado) recién al liquidar; mientras el ciclo sigue abierto o programado no se persiste nada, se calcula al vuelo), fecha_liquidacion`

**ganadores**
`id, jugada_id, sorteo_id, monto_premio`

**parametros**
`clave (ej. importe_jugada, premio_base — Fase 7; importe_jugada_sabado, premio_base_sabado, horario_limite_semanal, horario_limite_sabado — Fases 9/10), valor, actualizado_por (usuario_id), actualizado_en` — la tabla real se llama `parametros`, no `configuracion`; `ConfiguracionService` es la capa admin-facing que la edita.

**solicitudes** *(Fase 6 — ver sección 14.4)*
`id, cliente_id, tipo_juego (semanal | sabado — Fase 10, análogo a jugadas.tipo_juego, para que confirmar el pago bloquee el ciclo abierto del tipo correcto), numero_registro (VARCHAR(6), UNIQUE), cantidad_jugadas, monto_total, estado (pendiente | confirmada | rechazada), fecha_creacion, fecha_resolucion, resuelto_por (usuario_id)`

**promociones** *(Fase 7)*
`id, cantidad_jugadas, precio_total, activa, fecha_creacion, actualizado_por`

## 11. Stack tecnológico

- **Backend:** PHP, mismo criterio que `sas_imperio` y `crm_imperio`.
- **Base de datos:** MySQL, mismo servidor y flujo de despliegue (git pull) ya usado en el VPS.
- **Frontend:** Bootstrap 5 vía CDN (sin build tools), con diseño mobile-first: formularios grandes y táctiles para la carga de jugadas y sorteos, vistas simples y legibles en el portal del cliente.
- **Hosting:** VPS de prueba (Ubuntu 24.04) primero, réplica al VPS de producción una vez validado.

## 12. Fases de desarrollo

| Fase | Contenido | Estimación |
|---|---|---|
| 1. Núcleo | Usuarios y roles, ABM de clientes, carga de jugadas con validaciones | 2 semanas |
| 2. Sorteos y cotejo | Carga manual del extracto, motor de cotejo automático, cierre de ciclo y liquidación de pozo | 1 semana |
| 3. Portal del cliente | Login DNI/DNI, vista de jugadas y resultados, estado del pozo | 1 semana |
| 4. Administración y reportes | Recaudación, historial de ganadores, exportables | 1 semana |
| 5. Ampliación | Autorregistro con aprobación, monto configurable, carga múltiple de jugadas | 1 semana |
| 6. Selección propia de jugadas | Selección propia de jugadas por el cliente, con autorización de pago por staff | Implementada |
| 7. Premio base y promociones | Piso garantizado de pozo por ciclo, paquetes promocionales de jugadas | Implementada |
| 8. Carga anticipada | Ciclo "programado" para cargar jugadas de la próxima semana por adelantado | A implementar |
| 9. Horario límite de carga | Corte de carga a las 18:00 (lunes a viernes) y 11:00 (sábados), configurable, aplica a cliente y staff por igual | Implementada |
| 10. Juego de sábados | Mini-ciclo de 5 turnos el mismo sábado, pozo propio, mismo mecanismo de corte y arrastre que el semanal | Implementada |
| 11. Vendedores y referidos | Rol vendedor con link de referido, comisión por % de cada jugada del referido, supervisores también refieren (arrancan en 0%), liquidación manual por admin | Implementada |

## 13. Puntos abiertos antes de programar

1. Definir el nivel de detalle de reportes que verá el Supervisor (¿recaudación total, o solo sus propias cargas?). Resuelto en la Fase 4: admin ve todo, supervisor solo lo que él mismo cargó.
2. Formato de `nro_cliente` (resuelto): **año + 6 dígitos aleatorios**, sin correlatividad — ejemplo `2026-048372`. Se genera al azar y se valida contra un índice `UNIQUE` en la tabla `clientes`; si choca con uno existente, se regenera y reintenta.

## 14. Fase 6: selección propia de jugadas por el cliente (implementada)

*Implementada. Se deja el detalle funcional como referencia.*

### 14.1 Flujo

1. El cliente entra al portal y arma una o varias jugadas (sets de 10 números, con la misma validación de siempre), sin necesidad de que el staff las tipee.
2. Al guardar, cada jugada queda en estado **pendiente_pago** — todavía no pertenece a ningún ciclo ni suma al pozo.
3. El portal le muestra el **monto total a abonar** (monto vigente configurado × cantidad de jugadas armadas).
4. El cliente paga por los medios habituales (en persona o transferencia, sin pasarela online).
5. Admin o supervisor ve una cola de **"jugadas pendientes de confirmación"** (cliente, números elegidos, monto, fecha de selección) y, al recibir el pago, la **confirma**.
6. Al confirmarse, la jugada se asigna al **ciclo activo en ese momento** (no al ciclo vigente cuando el cliente la seleccionó) y recién ahí su 60% se suma al pozo y queda habilitada para el cotejo automático.

### 14.2 Reglas acordadas

- El ciclo se asigna en el momento de la **confirmación del pago**, no en el momento de la selección. Si entre la selección y la confirmación cambió el ciclo activo (por ejemplo, hubo un ganador esa semana), la jugada entra al ciclo que esté abierto al momento de confirmarse.
- Las jugadas pendientes de pago **no vencen automáticamente**: quedan en la cola indefinidamente hasta que el staff las confirme o las rechace manualmente.
- Rechazar una jugada pendiente (por ejemplo, si el cliente nunca pagó) queda reservado a Admin, siguiendo el mismo criterio que el rechazo de autorregistros; Supervisor puede confirmar pagos pero no rechazar.

### 14.3 Cambios de modelo de datos (resuelto)

- `jugadas` suma `estado_pago` (`pendiente_pago` | `confirmada` | `rechazada`) y `origen_carga` (`staff` | `cliente`), análogo a `origen_alta` en `clientes`. La columna `ciclo_id` pasa a completarse recién al confirmar (nula mientras está `pendiente_pago`). Se suma también `solicitud_id` (ver 14.4).
- **`estado_pago` no es lo mismo que `estado`.** `estado` ya existía desde la Fase 2 y es el veredicto del cotejo (`activa` | `ganadora` | `perdedora` | `anulada`); `estado_pago` es el ciclo de vida del cobro. Una jugada puede estar `estado_pago = pendiente_pago` y `estado = activa` a la vez — son dos cosas distintas que conviven en la misma fila, y el código las trata por separado (`SorteoService` lee `estado`, `SolicitudService` lee `estado_pago`).
- La columna `pagada` existente se mantuvo (no se reemplazó por `estado_pago`): sigue funcionando como el flag simple de "se cobró", que usan las pantallas que no necesitan distinguir `rechazada` de `pendiente_pago`.

### 14.4 Número de registro por solicitud

Cuando el cliente arma varias jugadas en una misma sesión, todas comparten **una solicitud** con un código corto que el cliente usa para identificarse al pagar (evita que el staff tenga que buscarlo por nombre).

- Nueva tabla **solicitudes**: `id, cliente_id, numero_registro (UNIQUE), cantidad_jugadas, monto_total, estado (pendiente | confirmada | rechazada), fecha_creacion, fecha_resolucion, resuelto_por`.
- `jugadas` suma la columna `solicitud_id` (nullable — solo se completa para jugadas de origen `cliente`).
- El `numero_registro` es un código de 6 caracteres (mayúsculas + números, sin `0/O/1/I/L` para evitar confusiones), generado al crear la solicitud y validado como único con reintento, igual criterio que `nro_cliente`.
- El staff busca la solicitud por ese código en la pantalla de confirmación, y al confirmar se aplica a **todas** las jugadas de esa solicitud a la vez (mismo ciclo activo, mismo momento) en vez de confirmarlas una por una.

### 14.5 Notas de implementación

- **Tope de 20 jugadas por solicitud.** La carga múltiple del staff (7.3) no tiene límite porque la usa personal de confianza; el formulario del portal lo usa un cliente autenticado pero de cara al público, así que tiene un tope contra un envío accidental o abusivo. Para más de 20 de una vez, se genera otra solicitud.
- **`cargado_por` queda vacío**, incluso después de confirmado el pago: esa columna significa "quién tipeó los números", y el supervisor que confirma verificó un pago, no eligió números. Quién resolvió la solicitud (y cuándo) sí queda registrado, en `solicitudes.resuelto_por` / `fecha_resolucion`.
- **Pantallas:** `portal/jugar.php` (armar jugada), `portal/solicitud.php` (código y monto, revisitable por el cliente hasta que se resuelva) y `admin/solicitudes/` (buscador por código + cola de pendientes, confirmar/rechazar). El formulario reutiliza el mismo widget de carga múltiple de la sección 7.3 (`assets/js/numeros.js`).
- **Pantallas existentes con un filtro adicional** para no mostrar una jugada pendiente de pago mezclada con las reales: el listado de "últimas jugadas" del tablero, el contador de jugadas del historial del cliente, y la auditoría de "quién cargó qué" — las tres exigen `estado_pago = 'confirmada'`. El resto del sistema (cotejo, reportes, pozo) ya queda afuera solo, por filtrar por `ciclo_id`.

## 15. Fase 9: horario límite de carga (implementada)

- **Juego semanal (lunes a viernes)**: las jugadas se pueden cargar solo hasta las **18:00 hora de Argentina**. Después de esa hora, cerrado hasta las 00:00 del día siguiente. Sábados y domingos no tienen este límite (sirve, por ejemplo, para precargar jugadas de la semana que arranca el lunes).
- **Juego de sábados** (ver sección 16): las jugadas se pueden cargar hasta las **11:00 hora de Argentina del mismo sábado**. El resto de la semana no tiene límite para este juego.
- **Aplica por igual a clientes (portal) y a staff (admin/supervisor)**, sin excepciones — no hay una vía "de confianza" que se salte el horario.
- **Los dos horarios son configurables** desde `admin/configuracion/index.php` (parámetros `horario_limite_semanal` y `horario_limite_sabado`, formato `HH:MM`), con el mismo mecanismo de `parametros`/`ParametroService` que ya usaba el monto de la jugada.
- **El corte es autoritativo del lado del servidor**: `JugadaService::crearVarias()` y `SolicitudService::crear()` lo verifican como primer paso, antes de tocar la base — cualquier camino de carga (presente o futuro) lo hereda automáticamente. El portal del cliente además **oculta el formulario completo** fuera de horario, con un aviso de cuándo reabre; el admin usa el mismo criterio visual.
- Implementado en `src/Services/HorarioCargaService.php`.

## 16. Fase 10: juego de sábados (implementada)

- Los sábados hay **5 sorteos** ("turnos"), todos con la misma fecha calendario, en vez de un sorteo por día como el juego semanal — una especie de "semana" comprimida en un solo día.
- **La jugada de sábado es de 5 números, no de 10**: el cliente elige 5 números distintos entre 00 y 99 (en vez de los 10 del juego semanal), y esos 5 tienen que estar, todos, entre los 20 del turno para ganar — mismo criterio de "todos contenidos, sin premio por acierto parcial" que el juego semanal, solo que con menos números a acertar. La cantidad es un parámetro propio (`numeros_por_jugada_sabado`, configurable desde `admin/configuracion/index.php`, independiente de `numeros_por_jugada` del semanal) — bajarla sube la probabilidad de ganar, así que el admin la ajusta sabiendo que también puede convenir revisar `importe_jugada_sabado`/`premio_base_sabado` en consecuencia.
- **Una jugada de sábado aplica automáticamente a los 5 turnos**: el cliente carga sus números una sola vez y esa jugada se coteja contra cada turno a medida que se van cargando, igual mecanismo que hoy usa una jugada semanal contra cada sorteo de lunes a viernes.
- **Ganar corta el día**: si una jugada acierta en el turno N, el pozo se liquida ahí mismo y los turnos restantes de ese sábado no participan — misma regla que el "corte a mitad de semana" (sección 3.2), aplicada a la secuencia de turnos en vez de a fechas calendario.
- **Si ningún turno tiene ganador, el pozo arrastra íntegro al sábado siguiente** — misma mecánica de arrastre que el pozo semanal (sección 3.3), nunca se pierde.
- **Pozo completamente separado del semanal**: acumula solo con jugadas de sábado, con su propio monto de jugada (`importe_jugada_sabado`) y su propio premio base garantizado (`premio_base_sabado`), ambos configurables desde `admin/configuracion/index.php`.
- **Promociones de paquete (Fase 7) exclusivas del juego semanal**: las jugadas de sábado siempre se cargan a precio de lista.
- **Horario límite de carga**: hasta las 11:00 del mismo sábado (ver sección 15).
- **Modelo de datos**: se generalizaron `ciclos` (columna `tipo`: `semanal` | `sabado`, cada uno con su propia numeración y su propio "un ciclo abierto a la vez") y `sorteos` (columna `turno`, 1 para el semanal y 1-5 para sábados, reemplazando a `fecha` como parte de la clave única). `jugadas` y `solicitudes` suman `tipo_juego` para saber a qué caja pertenecen mientras están `pendiente_pago` (sin `ciclo_id` todavía). `PozoService` no necesitó ningún cambio: ya opera sobre `ciclo_id`, que sigue siendo una PK global única sin importar el tipo. La cantidad de números por jugada (`numeros_por_jugada_sabado`, clave nueva en `parametros`) no necesitó ningún cambio de esquema: `jugada_numeros` nunca tuvo una restricción de cantidad de filas a nivel de base, solo se validaba en PHP (`JugadaService::validarNumeros()`) contra este mismo parámetro.
- **Pantallas**: directorio `admin/sabados/` (tablero, historial, detalle, carga de turno — paralelo a `admin/ciclos/` y `admin/sorteos/`), selector de modalidad en `admin/jugadas/nueva.php` y `portal/jugar.php`, tabs "Semana/Sábado" en `portal/index.php` y `portal/historial.php`.
- **Reportes**: `ReporteService`/`FiltroReporte` suman un filtro de tipo de juego (`semanal` por default, para no mezclar de golpe con lo que ya mostraban; `sabado` o `todos` como alternativa) — la caja de sábados no aparece mezclada con la semanal salvo que se pida explícitamente.

## 17. Fase 11: vendedores y referidos

### 17.1 Concepto

Nuevo rol **vendedor**: una persona que no juega ni carga jugadas ni sorteos. Su única función es captar jugadores nuevos mediante un link personal de referido. Gana un **porcentaje del importe de cada jugada confirmada** que cargue un cliente referido por él. Ese porcentaje es **global** (un solo valor configurable por el admin, aplica igual a todos los vendedores y supervisores) y sale del **40% de gastos/ganancias**, nunca del pozo de premios.

Los **supervisores** también reciben un código de referido con el mismo mecanismo, pero arrancan con el porcentaje global en 0% — el admin puede subirlo cuando quiera sin tocar código.

### 17.2 Flujo

1. **Admin crea vendedores** desde el panel (nombre, DNI, teléfono, clave). Al crearse se genera automáticamente un código de referido único y su link personal (`decenadeoro.lol/registro.php?ref=CODIGO`).
2. **Supervisores** reciben también un código de referido (migración sobre la tabla `usuarios`), con la misma mecánica.
3. **Registro con referido**: cuando alguien se registra usando un link con `?ref=CODIGO`, el sistema guarda `cliente.referido_por_tipo` (vendedor o supervisor) y `cliente.referido_por_id`. El formulario de registro funciona igual que hoy, solo viaja el código por detrás.
4. **Acreditación de comisión**: cada vez que un referido carga una jugada **confirmada y pagada** (no pendiente de pago), el sistema calcula el porcentaje global sobre el importe de esa jugada y lo registra como comisión para el vendedor/supervisor que lo refirió. Aplica tanto al juego semanal como al de sábados.
5. **Liquidación manual**: las comisiones se acumulan en un saldo. El admin las liquida cuando decide (botón de "liquidar" que registra el pago, la fecha y quién liquidó, y pone el saldo en cero).

### 17.3 No hay comisión por registro

El vendedor **no gana nada** por el hecho de que alguien se registre — la comisión se genera únicamente cuando el referido juega (jugada confirmada). Un referido que se registra pero nunca juega no le produce comisión al vendedor.

### 17.4 Panel del vendedor

- Su link de referido con botón de copiar (para compartir fácil desde el celular).
- Listado de sus referidos (nombre, cantidad de jugadas cargadas por cada uno).
- Saldo acumulado actual (comisiones pendientes de cobro).
- Historial de liquidaciones ya cobradas.

### 17.5 Panel del supervisor (lo que se agrega)

- Misma vista que el vendedor: sus propios referidos, cantidad de jugadas, saldo acumulado, historial de liquidaciones.
- No cambia nada de lo que ya puede hacer el supervisor (cargar jugadas, sorteos, aprobar clientes, etc.).

### 17.6 Panel del admin (lo nuevo)

- ABM de vendedores (crear, editar, desactivar).
- Configurar el **porcentaje global de comisión por jugada** (`parametros.comision_jugada_porcentaje`), desde la misma pantalla de configuración.
- Vista de **todos los referidos de todos** (vendedores y supervisores): quién refirió a quién, cuántas jugadas generó cada referido, cuánta comisión acumuló cada referidor.
- Liquidar comisiones por vendedor/supervisor individual.

### 17.7 Permisos

| Acción | Admin | Supervisor | Vendedor |
|---|---|---|---|
| Crear/editar/desactivar vendedores | ✅ | ❌ | ❌ |
| Configurar % de comisión | ✅ | ❌ | ❌ |
| Ver referidos de TODOS | ✅ | ❌ | ❌ |
| Liquidar comisiones | ✅ | ❌ | ❌ |
| Ver sus propios referidos y saldo | ❌ | ✅ | ✅ |
| Cargar jugadas / sorteos / clientes | ❌ | ✅ | ❌ |

### 17.8 Cambios de modelo de datos

- **`vendedores`** (nueva): `id, nombre, dni, telefono, password_hash, codigo_referido (VARCHAR UNIQUE), activo, fecha_alta`.
- **`usuarios`**: se agrega `codigo_referido (VARCHAR UNIQUE, nullable)` — se genera para supervisores existentes en la migración.
- **`clientes`**: se agrega `referido_por_tipo (ENUM: vendedor, supervisor, NULL)` y `referido_por_id (INT nullable)`.
- **`comisiones`** (nueva): `id, referidor_tipo (vendedor | supervisor), referidor_id, jugada_id (FK), monto, porcentaje_aplicado (snapshot del % vigente al momento de acreditar), fecha`.
- **`liquidaciones`** (nueva): `id, referidor_tipo, referidor_id, monto, fecha, liquidado_por (FK a usuarios)`.
- **`parametros`**: clave nueva `comision_jugada_porcentaje` (default 0).

### 17.9 Login del vendedor

El vendedor entra por la misma pantalla unificada de login (`auth/login.php`) que ya prueba contra `usuarios` y `clientes`. Se agrega una tercera prueba contra `vendedores`. Al detectar que es vendedor, redirige a su panel propio (`vendedor/index.php`) — sesión separada, sin acceso al panel de admin ni al portal de clientes.

### 17.10 Notas de implementación

- **Tercer "mundo" de sesión**: `config/vendedor.php` (cookie `POLLA_VENDEDOR`), mismo patrón de aislamiento que ya usaba `config/portal.php` para el cliente. `auth/login.php` prueba en cascada `usuarios` → `clientes` → `vendedores`, y cambia de sesión de forma explícita apenas hay un match (con tres mundos en cascada ya no alcanza con asumir "la sesión activa es la correcta").
- **El vendedor entra con su DNI** (no hay un campo "usuario" propio en la tabla `vendedores`), igual criterio que ya usa el cliente — el campo del login ya decía "Usuario o DNI", así que no hizo falta tocar esa pantalla.
- **Código de referido**: mismo alfabeto y largo que `numero_registro` de `solicitudes` (`ABCDEFGHJKMNPQRSTUVWXYZ23456789`, 6 caracteres, reintento ante colisión). `VendedorService` y `UsuarioService` tienen cada uno su propio generador (mismo criterio que ya coexistían `ClienteService::generarNroCliente()` y `SolicitudService::generarCodigo()` sin compartir código).
- **`UsuarioService::asignarCodigoReferidoSiFalta()`** se llama automáticamente desde `crear()` y `actualizar()`: todo supervisor nuevo (o que pase a serlo) saca su código solo, sin depender de que alguien se acuerde de generarlo. Nunca se lo saca si deja de ser supervisor.
- **Relación polimórfica sin FK**: `clientes.referido_por_tipo/referido_por_id` y `comisiones.referidor_tipo/referidor_id` pueden apuntar a `vendedores` o a `usuarios` según el tipo — una sola columna no puede tener una FK a dos tablas distintas, así que la integridad la garantiza la aplicación (`ComisionService::resolverCodigo()` antes de guardar), no la base.
- **El gancho de comisión** vive en `ComisionService::acreditarSiCorresponde()`, y se llama desde los dos puntos exactos donde una jugada pasa a `estado_pago = 'confirmada'`: `JugadaService::crearVarias()` (carga directa del staff) y `SolicitudService::confirmar()` (el cliente arma la jugada, el staff confirma el pago después). Los dos ya corrían dentro de su propia transacción, así que la comisión se acredita atómicamente junto con la jugada — nada nuevo que abrir. `PozoService` no se toca: la comisión es un libro aparte (`comisiones`), no resta nada de `jugadas.aporte_pozo`/`aporte_gastos`.
- **Sin fila si el porcentaje es 0 o el cliente no tiene referidor** — evita ruido en la tabla `comisiones`.
- **Liquidación como libro mayor**: `liquidaciones` no linkea comisiones puntuales. El saldo pendiente de un referidor es `SUM(comisiones.monto) - SUM(liquidaciones.monto)`; liquidar registra el saldo del momento (dentro de su propia transacción, para que leer el saldo e insertar sean atómicos) y lo deja en $0 hasta la próxima comisión.
- **`admin/referidos/mios.php`**: la vista del supervisor sobre sus propios referidos, dentro del panel admin (no un mundo de sesión aparte, porque el supervisor ya vive ahí para todo lo demás). Sin botón de liquidar — eso es exclusivo de `admin/liquidaciones/`.
- **Promociones de paquete no interactúan con comisiones**: la comisión se calcula sobre `jugadas.importe`, que con una promo ya viene prorrateado (Fase 7) — no hizo falta ningún ajuste adicional.
